<?php

namespace Tests\Feature;

use App\Game\AccountProgression;
use App\Models\GameMatch;
use App\Models\PlayerProfile;
use App\Models\PlayerSeason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeasonalAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-09-12 12:00:00 UTC'));
    }

    private function completed(User $user, string $role, array $extraActions = [], array $ballots = [], string $at = '2026-09-12 12:00:00 UTC'): array
    {
        $match = GameMatch::create(['id' => (string) Str::uuid(), 'rules_version' => 'test', 'player_count' => 3,
            'mission' => 'concord', 'winner' => 'town', 'win_reason' => 'test', 'nights' => 1, 'ritual_goal' => 6,
            'ritual_steps' => 1, 'missed_night_actions' => 0, 'missed_votes' => 0, 'recap_complete' => true,
            'recap' => [], 'started_at' => CarbonImmutable::parse($at)->subHour(), 'finished_at' => $at]);
        $state = ['match_id' => $match->id, 'winner' => 'town', 'players' => [
            'seat' => ['user_id' => $user->id, 'alignment' => in_array($role, ['acolyte', 'veilweaver']) ? 'cult' : 'town', 'role' => $role],
            'town' => ['alignment' => 'town', 'role' => 'vigilante'],
            'cult' => ['alignment' => 'cult', 'role' => 'acolyte'],
        ], 'rounds' => [['night' => ['actions' => [['player_id' => 'seat', 'submitted' => true], ...$extraActions]],
            'vote' => ['ballots' => $ballots ?: [['player_id' => 'seat', 'submitted' => true, 'oath_kept' => null]]]]]];

        return [$match, $state];
    }

    private function award(array $game): array
    {
        return DB::transaction(fn () => app(AccountProgression::class)->award(...$game));
    }

    private function oath(User $user, ?bool $kept, string $at = '2026-09-12 12:00:00 UTC'): array
    {
        return $this->completed($user, 'oathkeeper', ballots: [['player_id' => 'seat', 'submitted' => true, 'oath_kept' => $kept]], at: $at);
    }

    public function test_locked_seasonal_payload_exposes_only_question_marks_and_class_hints(): void
    {
        $user = User::factory()->create();
        $view = app(AccountProgression::class)->view($user->id);
        $this->assertSame('2026-Q3', $view['seasonal_achievements']['season_id']);
        $cards = $view['seasonal_achievements']['achievements'];
        $this->assertSame(['Warden', 'Cultist', 'Oathkeeper'], array_column($cards, 'role_name'));
        foreach ($cards as $card) {
            $this->assertSame(['id', 'name', 'role', 'role_name', 'earned_at', 'character'], array_keys($card));
            $this->assertSame('?', $card['name']);
            $this->assertNull($card['character']);
            $this->assertNull($card['earned_at']);
        }
        $catalog = collect($view['characters']);
        foreach ($cards as $card) {
            $entry = $catalog->firstWhere('id', $card['id']);
            $this->assertSame('?', $entry['name']);
            $this->assertSame($card['role_name'], $entry['requirement']);
            $this->assertTrue($entry['hidden']);
            $this->assertFalse(app(AccountProgression::class)->canUseCharacter($user->id, $entry['id']));
        }
    }

    public function test_warden_needs_a_real_save_and_reward_is_permanent_atomic_and_idempotent(): void
    {
        $user = User::factory()->create();
        $save = ['player_id' => 'seat', 'submitted' => true, 'prevented_curse' => true, 'target_id' => 'town'];
        foreach ([['prevented_curse' => false], ['disrupted' => true], ['target_id' => 'seat']] as $invalid) {
            $reward = $this->award($this->completed($user, 'warden', [array_replace($save, $invalid)]));
            $this->assertNotContains('seasonal_warden', $reward['seat']['characters']);
        }
        $game = $this->completed($user, 'warden', [$save]);
        DB::beginTransaction();
        app(AccountProgression::class)->award(...$game);
        DB::rollBack();
        $this->assertFalse(app(AccountProgression::class)->canUseCharacter($user->id, 'seasonal_warden'));
        $reward = $this->award($game);
        $this->assertContains('seasonal_warden', $reward['seat']['characters']);
        $this->assertSame($reward, $this->award($game));
        $this->travelTo(CarbonImmutable::parse('2027-01-01 12:00:00 UTC'));
        $this->assertTrue(app(AccountProgression::class)->canUseCharacter($user->id, 'seasonal_warden'));
        $entry = collect(app(AccountProgression::class)->view($user->id)['seasonal_achievements']['achievements'])->firstWhere('id', 'seasonal_warden');
        $this->assertSame('The Knight', $entry['character']['name']);
        $this->assertNotNull($entry['earned_at']);
    }

    public function test_cultist_reward_requires_an_attributed_redirected_vigilante_shot(): void
    {
        $user = User::factory()->create();
        $redirect = ['player_id' => 'town', 'submitted' => true, 'shot_fired' => true, 'chosen_target_id' => 'seat', 'target_id' => 'cult', 'misdirection_source_id' => 'seat'];
        foreach ([['misdirection_source_id' => 'cult'], ['misdirection_source_id' => null], ['target_id' => 'seat'],
            ['submitted' => false], ['disrupted' => true], ['player_id' => 'cult'], ['shot_fired' => false]] as $invalid) {
            $reward = $this->award($this->completed($user, 'acolyte', [array_replace($redirect, $invalid)]));
            $this->assertNotContains('seasonal_cultist', $reward['seat']['characters']);
        }
        $reward = $this->award($this->completed($user, 'acolyte', [$redirect]));
        $this->assertContains('seasonal_cultist', $reward['seat']['characters']);
        $other = User::factory()->create();
        $reward = $this->award($this->completed($other, 'veilweaver', ballots: [
            ['player_id' => 'seat', 'submitted' => true], $redirect,
        ]));
        $this->assertNotContains('seasonal_cultist', $reward['seat']['characters']);
        $notVigilante = $this->completed($other, 'veilweaver', [$redirect]);
        $notVigilante[1]['players']['town']['role'] = 'oracle';
        $reward = $this->award($notVigilante);
        $this->assertNotContains('seasonal_cultist', $reward['seat']['characters']);
        $reward = $this->award($this->completed($other, 'veilweaver', [$redirect]));
        $this->assertContains('seasonal_cultist', $reward['seat']['characters']);
    }

    public function test_oath_streak_counts_distinct_games_and_resets_on_broken_or_missing_promises(): void
    {
        $user = User::factory()->create();
        $game = $this->oath($user, true);
        $this->award($game);
        $this->award($game);
        $this->assertSame(1, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
        $this->award($this->completed($user, 'warden'));
        $this->assertSame(1, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
        $this->award($this->oath($user, false));
        $this->assertSame(0, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
        $this->award($this->oath($user, true));
        $this->award($this->oath($user, null));
        $this->assertSame(0, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
        for ($i = 1; $i <= 5; $i++) {
            $reward = $this->award($this->oath($user, true));
            $this->assertSame($i === 5, in_array('seasonal_oathkeeper', $reward['seat']['characters'], true));
        }
        $next = $this->award($this->oath($user, true));
        $this->assertNotContains('seasonal_oathkeeper', $next['seat']['characters']);
    }

    public function test_multiple_promises_in_one_game_cannot_shortcut_streak_and_one_broken_oath_resets_it(): void
    {
        $user = User::factory()->create();
        $game = $this->oath($user, true);
        $game[1]['rounds'] = array_fill(0, 5, $game[1]['rounds'][0]);
        $this->award($game);
        $this->assertSame(1, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
        $game = $this->oath($user, true);
        $game[1]['rounds'][] = ['vote' => ['ballots' => [['player_id' => 'seat', 'submitted' => true, 'oath_kept' => false]]]];
        $this->award($game);
        $this->assertSame(0, PlayerSeason::firstOrFail()->achievement_progress['oath_streak']);
    }

    public function test_new_season_resets_unfinished_streak_and_ineligible_matches_earn_nothing(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 4; $i++) {
            $this->award($this->oath($user, true, '2026-09-30 23:59:59 UTC'));
        }
        $this->award($this->oath($user, true, '2026-10-01 00:00:00 UTC'));
        $this->assertFalse(app(AccountProgression::class)->canUseCharacter($user->id, 'seasonal_oathkeeper'));
        $this->assertSame(1, PlayerSeason::where('season_id', '2026-Q4')->firstOrFail()->achievement_progress['oath_streak']);
        $game = $this->oath($user, true);
        $game[1]['rounds'][0]['night']['actions'][0]['submitted'] = false;
        $this->assertSame([], $this->award($game));
        $this->assertSame(4, PlayerSeason::where('season_id', '2026-Q3')->firstOrFail()->achievement_progress['oath_streak']);
        $other = User::factory()->unverified()->create();
        $this->assertSame([], $this->award($this->oath($other, true)));
        $this->assertNull(PlayerProfile::find($other->id));
    }
}
