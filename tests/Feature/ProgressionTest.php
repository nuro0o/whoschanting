<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Models\GameMatch;
use App\Models\PlayerProfile;
use App\Models\PlayerSeason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgressionTest extends TestCase
{
    use RefreshDatabase;

    private AccountProgression $progression;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
        $this->progression = app(AccountProgression::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-11 12:00:00 UTC'));
    }

    private function archive(string $finishedAt = '2026-09-11 12:00:00 UTC'): GameMatch
    {
        return GameMatch::create(['id' => (string) Str::uuid(), 'rules_version' => 'test', 'player_count' => 3,
            'mission' => 'concord', 'winner' => 'town', 'win_reason' => 'test', 'nights' => 1, 'ritual_goal' => 6,
            'ritual_steps' => 1, 'missed_night_actions' => 0, 'missed_votes' => 0, 'recap_complete' => true,
            'recap' => [], 'started_at' => '2026-09-11 11:00:00 UTC', 'finished_at' => $finishedAt]);
    }

    private function state(GameMatch $match, ?User $user, string $alignment = 'town'): array
    {
        return ['match_id' => $match->id, 'winner' => 'town', 'players' => ['seat' => [
            'user_id' => $user?->id, 'alignment' => $alignment, 'role' => $alignment === 'town' ? 'oathkeeper' : 'veilweaver',
        ]], 'rounds' => [['night' => ['actions' => [['player_id' => 'seat', 'submitted' => true]]],
            'vote' => ['ballots' => [['player_id' => 'seat', 'submitted' => true, 'target_id' => null, 'oath_kept' => true]]]]]];
    }

    private function award(GameMatch $match, array $state): array
    {
        return DB::transaction(fn () => $this->progression->award($match, $state));
    }

    public function test_rewards_are_atomic_idempotent_and_award_achievements_only_once(): void
    {
        $user = User::factory()->create();
        $match = $this->archive();
        $state = $this->state($match, $user);
        // An aborted archive must not leave an XP award behind.
        DB::beginTransaction();
        $this->progression->award($match, $state);
        DB::rollBack();
        $this->assertDatabaseCount('match_rewards', 0);
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertDatabaseCount('coin_transactions', 0);

        $first = $this->award($match, $state);
        $this->assertSame($first, $this->award($match, $state));
        $this->assertSame(140, $first['seat']['xp']);
        $this->assertSame(35, $first['seat']['coins']);
        $this->assertDatabaseHas('player_profiles', ['user_id' => $user->id, 'coins' => 35, 'coins_earned' => 35]);
        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertEqualsCanonicalizing(['first_watch', 'town_victory', 'kept_oath'], $first['seat']['achievements']);
        $this->assertDatabaseHas('player_profiles', ['user_id' => $user->id, 'xp' => 140, 'matches' => 1, 'wins' => 1]);
        $this->assertDatabaseCount('match_rewards', 1);
        $next = $this->archive();
        $second = $this->award($next, $this->state($next, $user));
        $this->assertSame([], $second['seat']['achievements']);
        $this->assertSame(1, $second['seat']['level_before']);
        $this->assertSame(2, $second['seat']['level_after']);
        $this->assertSame(['tidecaller'], $second['seat']['characters']);
        $this->assertSame($second, $this->award($next, $this->state($next, $user)));
        $view = $this->progression->view($user->id);
        $this->assertSame(30, $view['profile']['level_xp']);
        $this->assertSame(500, $view['profile']['next_level_xp']);
        $this->assertSame(280, $view['season']['xp']);
        $this->assertSame(70, $view['store']['balance']);
        $this->assertDatabaseCount('coin_transactions', 2);
        $this->assertCount(2, $view['recent_rewards']);
        $this->assertIsArray($view['recent_rewards'][0]);
    }

    public function test_character_achievement_unlocks_are_awarded_once_and_survive_a_new_season(): void
    {
        $user = User::factory()->create();
        $this->progression->rememberCharacter($user->id, 'archivist');
        PlayerProfile::findOrFail($user->id)->update(['matches' => 24, 'roles_played' => ['oracle', 'warden', 'lamplighter', 'tracker']]);
        $match = $this->archive();
        $state = $this->state($match, $user);
        $reward = $this->award($match, $state);
        $this->assertSame(['maskmaker', 'drowned_regent'], $reward['seat']['characters']);
        $this->assertSame($reward, $this->award($match, $state));
        $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00:00 UTC'));
        $catalog = collect($this->progression->characterCatalog($user->id));
        $this->assertTrue($catalog->firstWhere('id', 'maskmaker')['unlocked']);
        $this->assertTrue($catalog->firstWhere('id', 'drowned_regent')['unlocked']);
        $this->assertSame(0, $this->progression->view($user->id)['season']['xp']);
    }

    public function test_participation_requires_a_submitted_night_and_vote_but_abstaining_counts(): void
    {
        $user = User::factory()->create();
        $match = $this->archive();
        $state = $this->state($match, $user, 'cult');
        $state['rounds'][0]['vote']['ballots'][0]['submitted'] = false;
        $this->assertSame([], $this->award($match, $state));
        $state['rounds'][0]['vote']['ballots'][0]['submitted'] = true;
        $state['rounds'][0]['night']['actions'][0]['submitted'] = false;
        $this->assertSame([], $this->award($match, $state));
        $state['rounds'][0]['night']['actions'][0]['submitted'] = true;
        $state['rounds'][] = ['night' => ['actions' => [['player_id' => 'seat', 'submitted' => false]]]];
        $reward = $this->award($match, $state)['seat'];
        $this->assertSame(80, $reward['xp']); // Loss, with one missed action: no win or attendance bonus.
        $this->assertSame(25, $reward['coins']);
        $this->assertFalse($reward['won']);
    }

    public function test_guests_unverified_accounts_and_incomplete_archives_earn_nothing(): void
    {
        $match = $this->archive();
        $this->assertSame([], $this->award($match, $this->state($match, null)));
        $unverified = User::factory()->unverified()->create();
        $this->assertSame([], $this->award($match, $this->state($match, $unverified)));
        $verified = User::factory()->create();
        $match->update(['recap_complete' => false]);
        $this->assertSame([], $this->award($match, $this->state($match, $verified)));
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertDatabaseCount('match_rewards', 0);
    }

    public function test_seasons_use_completion_time_and_roll_over_without_removing_unlocks(): void
    {
        $user = User::factory()->create();
        $this->progression->rememberCharacter($user->id, 'archivist');
        PlayerProfile::whereKey($user->id)->update(['xp' => 1400]);
        PlayerSeason::create(['user_id' => $user->id, 'season_id' => '2026-Q3', 'xp' => 1400, 'matches' => 9]);
        $match = $this->archive('2026-09-30 23:59:59 UTC');
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:01 UTC'));
        $this->award($match, $this->state($match, $user));
        $view = $this->progression->view($user->id);
        $this->assertSame('2026-Q4', $view['season']['id']);
        $this->assertSame(0, $view['season']['xp']);
        $this->assertSame(1540, $view['profile']['xp']);
        $this->assertSame(1540, $view['season']['history'][0]['xp']);
        $this->assertSame('Golden tide', $view['season']['history'][0]['tier']);
        $this->assertNotNull(collect($view['achievements'])->firstWhere('id', 'season_regular')['earned_at']);
        $saved = $this->progression->customize($user->id, ['title' => 'tidekeeper', 'frame' => 'tidal', 'accent' => 'sea']);
        $this->assertSame('tidal', $saved['profile']['equipped']['frame']);
        $next = $this->archive('2026-10-01 00:00:00 UTC');
        $this->award($next, $this->state($next, $user));
        $this->assertSame(140, $this->progression->view($user->id)['season']['xp']);
        $this->assertSame('2026-Q3', $this->progression->season(CarbonImmutable::parse('2026-10-01 01:59:59 Europe/Amsterdam'))['id']);
        $this->assertSame('2027-Q1', $this->progression->season(CarbonImmutable::parse('2027-01-01 00:00:00 UTC'))['id']);
        foreach ([0 => 1, 249 => 1, 250 => 2, 749 => 2, 750 => 3, 1500 => 4, 2500 => 5] as $xp => $level) {
            $this->assertSame($level, $this->progression->level($xp));
        }
    }

    public function test_customization_is_private_validated_and_persists_across_sessions(): void
    {
        $this->get('/progression')->assertRedirect('/login');
        $this->postJson('/account/customization', [])->assertUnauthorized();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $defaults = ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => 'archivist'];
        $this->actingAs($user)->get('/progression')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Progression')->where('progression.profile.level', 1)->where('progression.profile.xp', 0));
        foreach ([['frame' => 'tidal'], ['title' => 'watchful'], ['accent' => 'ember'], ['character' => 'invented']] as $invalid) {
            $this->postJson('/account/customization', array_replace($defaults, $invalid))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('player_profiles', 0);
        $this->postJson('/account/customization', [...$defaults, 'user_id' => $other->id, 'xp' => 99999])->assertOk()
            ->assertJsonPath('progression.profile.xp', 0)->assertJsonPath('progression.profile.equipped.character', 'archivist');
        $this->assertDatabaseMissing('player_profiles', ['user_id' => $other->id]);
        $this->flushSession();
        $code = $this->actingAs($user)->postJson('/rooms', ['name' => 'Mara'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('me.character', 'archivist')->assertJsonPath('me.account_progression', true);
        $this->actingAs($other)->get('/progression')->assertInertia(fn (Assert $page) => $page->where('progression.profile.equipped.character', null));
        $this->actingAs(User::factory()->unverified()->create())->get('/progression')->assertRedirect('/email/verify');
        $this->postJson('/account/customization', $defaults)->assertForbidden();
    }

    public function test_account_seats_follow_the_account_and_cannot_be_taken_with_a_guest_token(): void
    {
        $engine = app(MatchEngine::class);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = $engine->create('device-one', 'Mara', accountId: $user->id);
        $id = $room->state['host_id'];
        $engine->join($room->code, 'device-two', 'Mara', 'archivist', $user->id);
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->assertSame($id, $engine->playerId($room->fresh()->state, 'device-two', $user->id));
        $this->assertNull($engine->playerId($room->state, 'device-one'));
        $this->assertNull($engine->playerId($room->state, 'device-one', $other->id));
        $this->getJson('/rooms/'.$room->code.'/state')->assertForbidden();
        $public = $engine->access($room->code, 'device-two', accountId: $user->id);
        $this->assertArrayNotHasKey('user_id', $public['players'][0]);
        $this->assertSame(['level', 'title', 'title_name', 'frame', 'accent', 'background'], array_keys($public['players'][0]['customization']));

        $guest = $engine->create('guest', 'Guest');
        $engine->join($guest->code, 'guest', 'Guest', accountId: $other->id);
        $this->assertSame($other->id, $guest->fresh()->state['players'][$guest->state['host_id']]['user_id']);
        $late = $engine->create('late', 'Late');
        $late->update(['state' => [...$late->state, 'phase' => 'finished']]);
        $engine->join($late->code, 'late', 'Late', accountId: $user->id);
        $this->assertNull($late->fresh()->state['players'][$late->state['host_id']]['user_id']);
    }

    public function test_real_match_awards_private_receipts_and_rematches_keep_progress(): void
    {
        $engine = app(MatchEngine::class);
        $users = User::factory()->count(3)->create();
        $room = $engine->create('p0', 'Player 0', setup: ['mode' => 'custom', 'roles' => ['townsperson' => 2, 'veilweaver' => 1]], accountId: $users[0]->id);
        for ($i = 1; $i < 3; $i++) {
            $engine->join($room->code, 'p'.$i, 'Player '.$i, accountId: $users[$i]->id);
        }
        $act = fn (int $i, string $type, array $extra = []) => $engine->access($room->code, 'p'.$i,
            ['phase_id' => $room->fresh()->state['phase_id'], 'type' => $type, ...$extra], $users[$i]->id);
        $expire = function () use ($room, $engine): void {
            $room->refresh()->update(['deadline' => now()->subSecond()]);
            $engine->resolve($room->id);
        };
        for ($i = 0; $i < 3; $i++) {
            $act($i, 'ready');
        }
        $act(0, 'start');
        $this->assertDatabaseCount('match_rewards', 0);
        $expire();
        for ($i = 0; $i < 3; $i++) {
            $act($i, 'night');
        }
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $expire();
        $cult = collect($room->fresh()->state['players'])->firstWhere('alignment', 'cult')['id'];
        foreach ($users as $i => $user) {
            $seat = $engine->playerId($room->fresh()->state, 'p'.$i, $user->id);
            $act($i, 'vote', ['target' => $seat === $cult ? null : $cult]);
        }
        $this->assertSame('finished', $room->fresh()->state['phase']);
        $this->assertDatabaseCount('match_rewards', 3);
        $this->assertDatabaseCount('game_matches', 1);
        foreach ($users as $i => $user) {
            $view = $engine->access($room->code, 'p'.$i, accountId: $user->id);
            $this->assertSame($view['me']['alignment'] === 'town' ? 140 : 100, $view['me']['match_reward']['xp']);
            $this->assertSame($view['me']['alignment'] === 'town' ? 35 : 25, $view['me']['match_reward']['coins']);
            $this->assertArrayNotHasKey('match_rewards', $view);
            $this->assertArrayNotHasKey('user_id', $view['players'][0]);
        }
        $this->assertStringNotContainsString('user_id', json_encode(GameMatch::first()->recap));
        $this->assertNull($engine->playerId($room->fresh()->state, 'spectator'));
        $before = $this->progression->view($users[0]->id)['profile']['xp'];
        $act(0, 'rematch');
        $this->assertArrayNotHasKey('match_rewards', $room->fresh()->state);
        $this->assertSame($before, $this->progression->view($users[0]->id)['profile']['xp']);
        $this->assertDatabaseCount('match_rewards', 3);
        $users[0]->delete();
        $this->assertDatabaseMissing('player_profiles', ['user_id' => $users[0]->id]);
        $this->assertDatabaseMissing('player_seasons', ['user_id' => $users[0]->id]);
        $this->assertDatabaseMissing('match_rewards', ['user_id' => $users[0]->id]);
    }
}
