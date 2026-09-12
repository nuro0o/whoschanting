<?php

namespace Tests\Feature;

use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Models\GameMatch;
use App\Models\PlayerProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrownCooldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-12 12:00:00 UTC'));
    }

    private function archive(int $seconds = 60): GameMatch
    {
        return GameMatch::create(['id' => (string) Str::uuid(), 'rules_version' => 'test', 'player_count' => 3,
            'mission' => 'concord', 'winner' => 'town', 'win_reason' => 'test', 'nights' => 1, 'ritual_goal' => 6,
            'ritual_steps' => 1, 'missed_night_actions' => 0, 'missed_votes' => 0, 'recap_complete' => true,
            'recap' => [], 'started_at' => now()->subSeconds($seconds), 'finished_at' => now()]);
    }

    private function state(GameMatch $match, User $user): array
    {
        return ['match_id' => $match->id, 'winner' => 'town', 'players' => ['seat' => [
            'user_id' => $user->id, 'alignment' => 'town', 'role' => 'townsperson',
        ]], 'rounds' => [['night' => ['actions' => [['player_id' => 'seat', 'submitted' => true]]],
            'vote' => ['ballots' => [['player_id' => 'seat', 'submitted' => true, 'target_id' => null]]]]]];
    }

    private function award(User $user, ?GameMatch $match = null): array
    {
        $match ??= $this->archive();

        return DB::transaction(fn () => app(AccountProgression::class)->award($match, $this->state($match, $user)))['seat'];
    }

    private function threeRapidMatches(User $user): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(8, $this->award($user)['coins']);
            $this->travel(2)->minutes();
        }
    }

    public function test_fourth_rapid_match_pauses_only_crowns_and_playing_does_not_extend_the_cooldown(): void
    {
        $user = User::factory()->create();
        $this->threeRapidMatches($user);
        $match = $this->archive();
        $reward = $this->award($user, $match);
        $until = now()->addMinutes(15)->toISOString();
        $this->assertSame(0, $reward['coins']);
        $this->assertSame(140, $reward['xp']);
        $this->assertSame($until, $reward['crown_cooldown_until']);
        $this->assertSame($reward, $this->award($user, $match));
        $this->assertDatabaseHas('player_profiles', ['user_id' => $user->id, 'coins' => 24, 'coins_earned' => 24, 'matches' => 4, 'xp' => 560]);
        $this->assertDatabaseCount('coin_transactions', 3);
        $this->assertDatabaseCount('match_rewards', 4);
        $this->assertSame($until, app(AccountProgression::class)->view($user->id)['store']['crown_cooldown_until']);

        $this->travel(14)->minutes();
        $during = $this->award($user, $this->archive(600));
        $this->assertSame(0, $during['coins']);
        $this->assertSame($until, $during['crown_cooldown_until']);
        $this->assertSame(140, $during['xp']);

        $this->travel(1)->minutes();
        $this->assertNull(app(AccountProgression::class)->view($user->id)['store']['crown_cooldown_until']);
        $this->assertSame($reward, $this->award($user, $match));
        $resumed = $this->award($user);
        $this->assertSame(8, $resumed['coins']);
        $this->assertNull($resumed['crown_cooldown_until']);
        $this->assertCount(1, PlayerProfile::findOrFail($user->id)->rapid_crown_matches);
        $this->assertDatabaseCount('coin_transactions', 4);
    }

    public function test_regular_matches_and_spaced_short_matches_keep_earning(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 8; $i++) {
            $this->assertSame(8, $this->award($user, $this->archive(120))['coins']);
        }
        $this->assertSame([], PlayerProfile::findOrFail($user->id)->rapid_crown_matches);
        $this->assertSame(8, $this->award($user)['coins']);
        $this->travel(15)->minutes();
        $this->threeRapidMatches($user);
        $this->assertNull(PlayerProfile::findOrFail($user->id)->crown_cooldown_until);
    }

    public function test_cooldown_is_account_wide_and_does_not_affect_other_accounts_or_spending(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->threeRapidMatches($user);
        $this->assertSame(0, $this->award($user)['coins']);
        $this->assertSame(8, $this->award($other)['coins']);
        PlayerProfile::findOrFail($user->id)->update(['coins' => 200]);
        $this->actingAs($user)->postJson('/account/store/purchase', ['item_id' => 'title-night-market'])
            ->assertOk()->assertJsonPath('progression.store.balance', 100);
        $this->assertSame(0, $this->award($user)['coins']);
    }

    public function test_trigger_and_receipt_roll_back_together(): void
    {
        $user = User::factory()->create();
        $this->threeRapidMatches($user);
        $match = $this->archive();
        DB::beginTransaction();
        $this->assertSame(0, $this->award($user, $match)['coins']);
        DB::rollBack();
        $profile = PlayerProfile::findOrFail($user->id);
        $this->assertNull($profile->crown_cooldown_until);
        $this->assertCount(3, $profile->rapid_crown_matches);
        $this->assertDatabaseCount('match_rewards', 3);
        $this->assertDatabaseCount('coin_transactions', 3);
        $this->assertSame(0, $this->award($user, $match)['coins']);
        $this->assertDatabaseCount('match_rewards', 4);
    }

    public function test_ineligible_matches_do_not_count_toward_the_cooldown(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $match = $this->archive();
            $state = $this->state($match, $user);
            $state['rounds'][0]['vote']['ballots'][0]['submitted'] = false;
            $this->assertSame([], DB::transaction(fn () => app(AccountProgression::class)->award($match, $state)));
        }
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertSame(8, $this->award($user)['coins']);
        $this->assertCount(1, PlayerProfile::findOrFail($user->id)->rapid_crown_matches);
    }

    public function test_each_verified_account_earns_once_in_the_same_match(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $match = $this->archive();
        $state = $this->state($match, $user);
        $state['players']['friend'] = [...$state['players']['seat'], 'user_id' => $friend->id];
        // Even a malformed legacy duplicate seat must not duplicate an account reward or strike.
        $state['players']['duplicate'] = $state['players']['seat'];
        foreach (['friend', 'duplicate'] as $id) {
            $state['rounds'][0]['night']['actions'][] = ['player_id' => $id, 'submitted' => true];
            $state['rounds'][0]['vote']['ballots'][] = ['player_id' => $id, 'submitted' => true, 'target_id' => null];
        }
        $award = fn () => DB::transaction(fn () => app(AccountProgression::class)->award($match, $state));
        $rewards = $award();
        $this->assertSame($rewards, $award());
        $this->assertSame(8, $rewards['seat']['coins']);
        $this->assertSame(8, $rewards['friend']['coins']);
        $this->assertArrayNotHasKey('duplicate', $rewards);
        $this->assertDatabaseCount('coin_transactions', 2);
        $this->assertDatabaseCount('match_rewards', 2);
        $this->assertCount(1, PlayerProfile::findOrFail($user->id)->rapid_crown_matches);
    }

    public function test_a_player_on_cooldown_can_still_join_start_and_rematch(): void
    {
        $user = User::factory()->create();
        $this->threeRapidMatches($user);
        $this->assertSame(0, $this->award($user)['coins']);
        $engine = app(MatchEngine::class);
        $room = $engine->create('host', 'Host', accountId: $user->id);
        foreach (['friend', 'guest'] as $identity) {
            $engine->join($room->code, $identity, ucfirst($identity));
            $engine->access($room->code, $identity, ['type' => 'ready', 'phase_id' => 1]);
        }
        $engine->join($room->code, 'new-session', 'Returning host', accountId: $user->id);
        $engine->access($room->code, 'host', ['type' => 'ready', 'phase_id' => 1], $user->id);
        $view = $engine->access($room->code, 'host', ['type' => 'start', 'phase_id' => 1], $user->id);
        $this->assertSame('reveal', $view['phase']);
        $state = $room->fresh()->state;
        $state['phase'] = 'finished';
        $room->update(['state' => $state, 'deadline' => null]);
        $view = $engine->access($room->code, 'host', ['type' => 'rematch', 'phase_id' => $state['phase_id']], $user->id);
        $this->assertSame('lobby', $view['phase']);
        $this->assertTrue(PlayerProfile::findOrFail($user->id)->crown_cooldown_until->isFuture());
    }
}
