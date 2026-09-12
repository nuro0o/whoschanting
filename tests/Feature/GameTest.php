<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\CurseEngine;
use App\Game\MatchEngine;
use App\Game\SeasonalAchievements;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->engine = app(MatchEngine::class);
        Event::fake([RoomUpdated::class]);
    }

    /** @return array{GameRoom, array<string, string>} */
    private function match(?string $mission = 'concord', int $playerCount = 5): array
    {
        $room = $this->engine->create('secret-0', 'Player 0');
        for ($i = 1; $i < $playerCount; $i++) {
            $this->engine->join($room->code, 'secret-'.$i, 'Player '.$i);
        }
        $identities = [];
        foreach ($room->fresh()->state['players'] as $id => $p) {
            $identity = 'secret-'.substr($p['name'], strlen('Player '));
            $identities[$id] = $identity;
            $this->act($room, $identity, 'ready');
        }
        $this->act($room, 'secret-0', 'start');
        $s = $room->fresh()->state;
        if ($mission !== null) {
            $s['mission'] = config('game.missions.'.$mission);
        }
        $room->update(['state' => $s]);

        return [$room, $identities];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, string $identity, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, $identity, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function expire(GameRoom $room): void
    {
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->artisan('game:tick')->assertSuccessful();
        $room->refresh();
    }

    /** @return array<string, string> */
    private function roles(GameRoom $room): array
    {
        $roles = [];
        foreach ($room->fresh()->state['players'] as $id => $p) {
            // Keep both townspeople individually available through state.
            $roles[$p['role']] = $id;
        }

        return $roles;
    }

    /** @return array<string, array{int, int}> */
    public static function rosterSizes(): array
    {
        return [
            'three players' => [3, 1], 'four players' => [4, 1],
            'five players' => [5, 2], 'six players' => [6, 2],
            'seven players' => [7, 3], 'eight players' => [8, 3],
            'nine players' => [9, 4], 'ten players' => [10, 4],
            'eleven players' => [11, 4], 'twelve players' => [12, 4],
            'thirteen players' => [13, 5], 'fourteen players' => [14, 5], 'fifteen players' => [15, 5],
        ];
    }

    #[DataProvider('rosterSizes')]
    public function test_roster_sizes_private_teammates_and_shared_scoring(int $playerCount, int $cultistCount): void
    {
        [$room, $identities] = $this->match(playerCount: $playerCount);
        $players = $room->fresh()->state['players'];
        $cult = array_filter($players, fn (array $p): bool => $p['alignment'] === 'cult');
        $this->assertCount($playerCount, $players);
        $this->assertCount($cultistCount, $cult);
        $roles = array_count_values(array_column($players, 'role'));
        $this->assertSame(1, $roles['veilweaver']);
        $this->assertSame($cultistCount - 1 - (int) ($playerCount >= 7), $roles['acolyte'] ?? 0);
        $this->assertSame($playerCount >= 7 ? 1 : 0, $roles['dreamweaver'] ?? 0);
        $this->assertSame(1, $roles['oracle']);
        $this->assertSame($playerCount >= 5 ? 1 : 0, $roles['warden'] ?? 0);
        $this->assertSame($playerCount >= 7 ? 1 : 0, $roles['lamplighter'] ?? 0);
        $this->assertSame($playerCount >= 8 ? 1 : 0, $roles['medium'] ?? 0);
        $this->assertSame($playerCount >= 10 ? 1 : 0, $roles['bellkeeper'] ?? 0);
        $this->assertSame($playerCount - $cultistCount - 1 - (int) ($playerCount >= 5) - (int) ($playerCount >= 7) - (int) ($playerCount >= 8) - (int) ($playerCount >= 10), $roles['townsperson']);
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            foreach ($view['players'] as $public) {
                $this->assertArrayNotHasKey('role', $public);
                $this->assertArrayNotHasKey('alignment', $public);
            }
            if (isset($cult[$id])) {
                $this->assertEqualsCanonicalizing(array_values(array_diff(array_keys($cult), [$id])), array_column($view['me']['allies'], 'id'));
                $this->assertSame(config('game.missions.concord'), $view['me']['mission']);
            } else {
                $this->assertSame([], $view['me']['allies']);
                $this->assertNull($view['me']['mission']);
            }
        }
        $this->expire($room);
        foreach (array_keys($cult) as $id) {
            $this->act($room, $identities[$id], 'night');
        }
        $this->expire($room);
        $this->assertSame($cultistCount, $room->state['tokens']);
        $this->assertCount($cultistCount, $room->state['awards']);
        $discussionSeconds = 90 + max(0, $playerCount - 10) * 10;
        $this->assertEquals($discussionSeconds, now()->diffInSeconds($room->deadline));
        $this->assertSame($discussionSeconds, $this->engine->access($room->code, 'secret-0')['rules']['seconds']['discussion']);
    }

    public static function protectionOrders(): array
    {
        return ['protection first' => [true], 'protection last' => [false]];
    }

    #[DataProvider('protectionOrders')]
    public function test_protection_blocks_all_new_curses_but_not_veils_visits_or_chanting(bool $protectFirst): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        $target = $r['townsperson'];
        $protection = fn () => $this->act($room, $identities[$r['warden']], 'night', ['target' => $target]);
        if ($protectFirst) {
            $protection();
        }
        $this->act($room, $identities[$r['lamplighter']], 'night', ['target' => $target]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $target]);
        foreach ($room->fresh()->state['players'] as $id => $player) {
            if ($player['alignment'] === 'cult') {
                $this->act($room, $identities[$id], 'night', ['target' => $player['role'] === 'dreamweaver' ? null : $target]);
            }
        }
        if (! $protectFirst) {
            $protection();
        }
        $this->expire($room);
        $this->assertNull($room->state['players'][$target]['curse']);
        $this->assertSame(3, $room->state['tokens']);
        $this->assertSame('cult', $room->state['players'][$r['oracle']]['results'][0]['alignment']);
        $this->assertSame([
            'kind' => 'protection', 'day' => 1, 'target' => $room->state['players'][$target]['name'],
        ], $room->state['players'][$r['warden']]['results'][0]);
        $this->assertSame([
            'kind' => 'visits', 'day' => 1, 'target' => $room->state['players'][$target]['name'], 'visited' => true,
        ], $room->state['players'][$r['lamplighter']]['results'][0]);
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            $this->assertSame($room->state['players'][$id]['results'], $view['me']['results']);
            $this->assertNull($view['recap']);
            $this->assertArrayNotHasKey('rounds', $view);
            $this->assertArrayNotHasKey('last_protection', $view['me']);
            foreach ($view['players'] as $player) {
                $this->assertArrayNotHasKey('results', $player);
                $this->assertArrayNotHasKey('last_protection', $player);
                $this->assertArrayNotHasKey('role', $player);
            }
        }
        // Finish the match and check the archived explanation, then rematch cleanup.
        $s = $room->state;
        $s['tokens'] = $s['threshold'];
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->expire($room);
        $recap = $this->engine->access($room->code, 'secret-0')['recap'];
        $this->assertSame('large-gatherings-v1', $recap['rules_version']);
        $actions = array_column($recap['rounds'][0]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$r['warden']]['prevented_curse']);
        $this->assertTrue($actions[$r['lamplighter']]['visited']);
        $this->assertTrue($actions[$r['veilweaver']]['curse_blocked']);
        $this->assertNull($actions[$r['veilweaver']]['curse_type']);
        $this->assertSame($recap['rounds'], GameMatch::firstOrFail()->getAttribute('recap')['rounds']);
        $this->act($room, 'secret-0', 'rematch');
        foreach ($room->fresh()->state['players'] as $player) {
            $this->assertSame([], $player['results']);
            $this->assertArrayNotHasKey('last_protection', $player);
        }
    }

    public function test_warden_cooldown_survives_refresh_allows_voting_and_resets_after_a_skipped_night(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $warden = $identities[$r['warden']];
        $target = $r['townsperson'];
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, $warden, 'night', ['target' => $r['warden']]));
        $this->act($room, $warden, 'night', ['target' => $target]);
        $this->assertRejected(fn () => $this->act($room, $warden, 'night', ['target' => $r['oracle']]));
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $warden, 'vote', ['target' => $target]);
        $this->expire($room);
        $view = app(MatchEngine::class)->access($room->code, $warden);
        $this->assertSame($target, $view['me']['previous_protection_target']);
        $this->assertNull($this->engine->access($room->code, $identities[$target])['me']['previous_protection_target']);
        $this->assertRejected(fn () => $this->act($room, $warden, 'night', ['target' => $target]));
        $this->act($room, $warden, 'night');
        $this->expire($room);
        $actions = array_column($room->state['rounds'][2]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$r['warden']]['submitted']);
        $this->assertNull($actions[$r['warden']]['target_id']);
        $this->assertCount(1, $room->state['players'][$r['warden']]['results']);
        $this->expire($room);
        $this->expire($room);
        $this->assertNull($this->engine->access($room->code, $warden)['me']['previous_protection_target']);
        $this->act($room, $warden, 'night', ['target' => $target]);
    }

    public static function visitSources(): array
    {
        return [
            'oracle' => ['oracle'], 'warden' => ['warden'],
            'veilweaver' => ['veilweaver'], 'acolyte' => ['acolyte'],
            'own watch only' => [null],
        ];
    }

    #[DataProvider('visitSources')]
    public function test_lamplighter_reports_only_other_actual_visits(?string $visitorRole): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $lamp = $identities[$r['lamplighter']];
        $target = $r['townsperson'];
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, $lamp, 'night'));
        $this->assertRejected(fn () => $this->act($room, $lamp, 'night', ['target' => $r['lamplighter']]));
        $this->act($room, $lamp, 'night', ['target' => $target]);
        $this->assertRejected(fn () => $this->act($room, $lamp, 'night', ['target' => $r['oracle']]));
        if ($visitorRole !== null) {
            $this->act($room, $identities[$r[$visitorRole]], 'night', ['target' => $target]);
        } else {
            // Being visited yourself is unrelated to visits to your watched player.
            $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['lamplighter']]);
        }
        $this->expire($room);
        $this->assertSame([
            ['kind' => 'visits', 'day' => 1, 'target' => $room->state['players'][$target]['name'], 'visited' => $visitorRole !== null],
        ], $this->engine->access($room->code, $lamp)['me']['results']);
        $this->assertSame([], $this->engine->access($room->code, $identities[$r['veilweaver']])['me']['results']);
    }

    public function test_missing_protection_and_observation_do_not_create_results_or_prevent_curses(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['acolyte']], 'night', ['target' => $r['townsperson']]);
        $this->expire($room);
        $this->assertNotNull($room->state['players'][$r['townsperson']]['curse']);
        foreach (['warden', 'lamplighter'] as $role) {
            $this->assertSame([], $this->engine->access($room->code, $identities[$r[$role]])['me']['results']);
        }
        $actions = array_column($room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertFalse($actions[$r['warden']]['submitted']);
        $this->assertFalse($actions[$r['lamplighter']]['submitted']);
        $this->assertNull($actions[$r['lamplighter']]['visited']);
    }

    public function test_misdirection_respects_protection_cooldown_and_records_the_actual_target(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $warden = $identities[$r['warden']];
        $this->expire($room);
        $this->act($room, $warden, 'night', ['target' => $r['townsperson']]);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->afflict($room, $r['warden'], 'misdirection');
        $this->act($room, $warden, 'night', ['target' => $r['oracle']]);
        $actual = $room->fresh()->state['actions'][$r['warden']]['target'];
        $this->assertNotContains($actual, [$r['warden'], $r['oracle'], $r['townsperson']]);
        $this->expire($room);
        $this->assertSame($actual, $room->state['players'][$r['warden']]['last_protection']['target']);
        $actions = array_column($room->state['rounds'][2]['night']['actions'], null, 'player_id');
        $this->assertSame($r['oracle'], $actions[$r['warden']]['chosen_target_id']);
        $this->assertSame($actual, $actions[$r['warden']]['target_id']);
    }

    public function test_lamplighter_observes_the_redirected_watch_target(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        $this->afflict($room, $r['lamplighter'], 'misdirection');
        $this->act($room, $identities[$r['lamplighter']], 'night', ['target' => $r['townsperson']]);
        $actual = $room->fresh()->state['actions'][$r['lamplighter']]['target'];
        $visitor = $actual === $r['oracle'] ? $r['warden'] : $r['oracle'];
        $this->act($room, $identities[$visitor], 'night', ['target' => $actual]);
        $this->expire($room);
        $result = $this->engine->access($room->code, $identities[$r['lamplighter']])['me']['results'][0];
        $this->assertSame($room->state['players'][$actual]['name'], $result['target']);
        $this->assertTrue($result['visited']);
    }

    public function test_lamplighter_counts_a_redirected_visit_only_at_its_actual_destination(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        $this->afflict($room, $r['oracle'], 'misdirection');
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['townsperson']]);
        $actual = $room->fresh()->state['actions'][$r['oracle']]['target'];
        $this->assertNotSame($r['townsperson'], $actual);
        $this->act($room, $identities[$r['lamplighter']], 'night', ['target' => $r['townsperson']]);
        $this->expire($room);
        $this->assertFalse($room->state['players'][$r['lamplighter']]['results'][0]['visited']);
    }

    public static function watchRoles(): array
    {
        return ['warden' => ['warden'], 'lamplighter' => ['lamplighter']];
    }

    #[DataProvider('watchRoles')]
    public function test_watch_roles_obey_curses_living_targets_and_banishment(string $role): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $identity = $identities[$r[$role]];
        $target = $r['townsperson'];
        $this->expire($room);
        $curse = $this->afflict($room, $r[$role], 'puzzle');
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', ['target' => $target]));
        $this->act($room, $identity, 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        $s = $room->fresh()->state;
        $s['players'][$target]['alive'] = false;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', ['target' => $target]));
        $s['players'][$r[$role]]['alive'] = false;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', ['target' => $r['oracle']]));
    }

    public function test_protection_does_not_clear_an_existing_curse_before_dawn(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $curse = $this->afflict($room, $r['oracle'], 'puzzle');
        $this->act($room, $identities[$r['warden']], 'night', ['target' => $r['oracle']]);
        $this->assertSame($curse, $room->fresh()->state['players'][$r['oracle']]['curse']);
        $this->assertRejected(fn () => $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]));
        $this->expire($room);
        $this->assertNull($room->state['players'][$r['oracle']]['curse']);
    }

    public static function disruptionTargets(): array
    {
        $cases = [];
        foreach (['oracle', 'warden', 'lamplighter', 'medium', 'bellkeeper', 'veilweaver', 'acolyte'] as $role) {
            $cases[$role.' first'] = [$role, true];
            $cases[$role.' last'] = [$role, false];
        }

        return $cases;
    }

    #[DataProvider('disruptionTargets')]
    public function test_disruption_precedes_other_abilities_and_chants_without_leaking_the_actor(string $role, bool $first): void
    {
        [$room, $identities] = $this->match(mission: 'shadows', playerCount: 10);
        $r = $this->roles($room);
        $s = $room->fresh()->state;
        $s['players'][$r['townsperson']]['alive'] = false;
        $room->update(['state' => $s]);
        $this->expire($room);
        $target = match ($role) {
            'medium' => $r['townsperson'], 'bellkeeper' => null,
            'oracle' => $r['warden'], default => $r['oracle'],
        };
        $ability = in_array($role, ['oracle', 'medium', 'bellkeeper']);
        $disrupt = fn () => $this->act($room, $identities[$r['dreamweaver']], 'night', ['target' => $r[$role], 'use_ability' => true]);
        if ($first) {
            $disrupt();
        }
        $this->act($room, $identities[$r[$role]], 'night', ['target' => $target, 'use_ability' => $ability]);
        if (! $first) {
            $disrupt();
        }
        // All remaining cultists chant. A disrupted Oracle cannot defeat Shadows.
        foreach ($room->fresh()->state['players'] as $id => $p) {
            if ($p['alignment'] === 'cult' && ! in_array($id, [$r['dreamweaver'], $r[$role]])) {
                $this->act($room, $identities[$id], 'night', ['target' => $role === 'warden' && $p['role'] === 'veilweaver' ? $target : null]);
            }
        }
        $this->expire($room);
        $view = $this->engine->access($room->code, $identities[$r[$role]]);
        $this->assertSame([['kind' => 'disrupted', 'day' => 1, 'target' => $s['players'][$r[$role]]['name']]], $view['me']['results']);
        $this->assertSame($ability, $view['me']['ability_used']);
        $this->assertNull($view['recap']);
        $this->assertSame(in_array($role, ['acolyte', 'veilweaver']) ? 2 : 3, $room->state['tokens']);
        $actions = array_column($room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$r[$role]]['disrupted']);
        $this->assertNull($actions[$r[$role]]['apparent_alignment']);
        $this->assertNull($actions[$r[$role]]['true_alignment']);
        $this->assertNull($actions[$r[$role]]['visited']);
        $this->assertNull($actions[$r[$role]]['curse_type']);
        $this->assertFalse($actions[$r['dreamweaver']]['contributed']);
        $this->assertNull($actions[$r['dreamweaver']]['curse_type']);
        if ($role === 'warden') {
            $this->assertArrayNotHasKey('last_protection', $room->state['players'][$r['warden']]);
            $this->assertNotNull($room->state['players'][$target]['curse']);
        }
        $this->engine->resolve($room->id);
        $this->assertCount(1, $this->engine->access($room->code, $identities[$r[$role]])['me']['results']);
    }

    public function test_disruption_bypasses_protection_and_lamplighter_sees_attempted_visits(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['warden']], 'night', ['target' => $r['oracle']]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['townsperson']]);
        $this->act($room, $identities[$r['lamplighter']], 'night', ['target' => $r['townsperson']]);
        $this->act($room, $identities[$r['dreamweaver']], 'night', ['target' => $r['oracle'], 'use_ability' => true]);
        $this->expire($room);
        $this->assertSame('disrupted', $room->state['players'][$r['oracle']]['results'][0]['kind']);
        $this->assertTrue($room->state['players'][$r['lamplighter']]['results'][0]['visited']);
    }

    public function test_dreamweaver_can_save_ability_and_chant_but_disruption_breaks_concord(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $r = $this->roles($room);
        $this->expire($room);
        foreach ($room->fresh()->state['players'] as $id => $p) {
            if ($p['alignment'] === 'cult') {
                $this->act($room, $identities[$id], 'night');
            }
        }
        $this->expire($room);
        $this->assertSame(3, $room->state['tokens']);
        $this->assertFalse($this->engine->access($room->code, $identities[$r['dreamweaver']])['me']['ability_used']);
        $this->expire($room);
        $this->expire($room);
        foreach ($room->fresh()->state['players'] as $id => $p) {
            if ($p['alignment'] === 'cult') {
                $this->act($room, $identities[$id], 'night', $p['role'] === 'dreamweaver' ? ['use_ability' => true, 'target' => $r['oracle']] : []);
            }
        }
        $this->expire($room);
        $this->assertSame(3, $room->state['tokens']);
        $this->assertSame(0, $room->state['rounds'][2]['night']['gained']);
        // A player who missed their action gets no fabricated failure result.
        $this->assertSame([], $room->state['players'][$r['oracle']]['results']);
    }

    public function test_medium_uses_only_banished_targets_and_misdirection_preserves_that_pool(): void
    {
        [$room, $identities] = $this->match(playerCount: 8);
        $r = $this->roles($room);
        $medium = $identities[$r['medium']];
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, $medium, 'night', ['use_ability' => true]));
        $this->assertRejected(fn () => $this->act($room, $medium, 'night', ['use_ability' => true, 'target' => $r['oracle']]));
        $this->assertFalse($this->engine->access($room->code, $medium)['me']['ability_used']);
        $this->act($room, $medium, 'night');
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['players'][$r['townsperson']]['alive'] = false;
        $s['players'][$r['acolyte']]['alive'] = false;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, $medium, 'night', ['target' => $r['townsperson']]));
        $this->afflict($room, $r['medium'], 'misdirection');
        $used = $this->act($room, $medium, 'night', ['target' => $r['townsperson'], 'use_ability' => true]);
        $this->assertTrue($used['me']['ability_used']);
        $this->assertSame($r['acolyte'], $room->fresh()->state['actions'][$r['medium']]['target']);
        $this->assertRejected(fn () => $this->act($room, $medium, 'night', ['target' => $r['acolyte'], 'use_ability' => true]));
        $this->expire($room);
        $this->assertSame([['kind' => 'spirit', 'day' => 2, 'target' => $s['players'][$r['acolyte']]['name'], 'alignment' => 'cult']], $this->engine->access($room->code, $medium)['me']['results']);
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            if ($id !== $r['medium']) {
                $this->assertSame([], $view['me']['results']);
            }
            $this->assertNull($view['recap']);
            foreach ($view['players'] as $player) {
                $this->assertArrayNotHasKey('ability_used', $player);
            }
        }
        $this->expire($room);
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, $medium, 'night', ['target' => $r['acolyte'], 'use_ability' => true]));
        $this->act($room, $medium, 'night');
    }

    public static function bellNights(): array
    {
        return ['quiet night' => [0], 'one step' => [1], 'several steps' => [3]];
    }

    #[DataProvider('bellNights')]
    public function test_bell_prevents_at_most_one_new_step_and_is_spent_even_on_a_quiet_night(int $chants): void
    {
        [$room, $identities] = $this->match(mission: 'shadows', playerCount: 10);
        $r = $this->roles($room);
        $bell = $identities[$r['bellkeeper']];
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['tokens'] = $s['threshold'] - 1;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, $bell, 'night', ['use_ability' => true, 'target' => $r['oracle']]));
        $this->act($room, $bell, 'night', ['use_ability' => true]);
        $cult = array_keys(array_filter($s['players'], fn ($p) => $p['alignment'] === 'cult'));
        foreach (array_slice($cult, 0, $chants) as $id) {
            $this->act($room, $identities[$id], 'night');
        }
        $this->expire($room);
        $this->assertSame($s['tokens'] + max(0, $chants - 1), $room->state['tokens']);
        $view = $this->engine->access($room->code, $bell);
        $this->assertSame($chants > 1, $view['ritual']['final_vote']);
        $this->assertSame([['kind' => 'bell', 'day' => 1, 'target' => 'The ritual', 'prevented' => min(1, $chants)]], $view['me']['results']);
        $this->assertTrue($view['me']['ability_used']);
        $this->assertSame(max(0, $chants - 1), $room->state['rounds'][1]['night']['gained']);
        $this->assertSame(min(1, $chants), count(array_filter($room->state['rounds'][1]['night']['actions'], fn ($a) => $a['ritual_blocked'])));
    }

    public function test_limited_abilities_validate_http_payloads_and_stale_actions_without_spending_them(): void
    {
        [$room, $identities] = $this->match(playerCount: 10);
        $r = $this->roles($room);
        $this->expire($room);
        $phase = $room->fresh()->state['phase_id'];
        $this->withSession(['chanting.identity' => $identities[$r['bellkeeper']]])
            ->postJson('/rooms/'.$room->code.'/actions', ['type' => 'night', 'phase_id' => $phase, 'use_ability' => 'yes'])->assertUnprocessable();
        $this->assertRejected(fn () => $this->act($room, $identities[$r['warden']], 'night', ['target' => $r['oracle'], 'use_ability' => true]));
        $this->assertRejected(fn () => $this->engine->access($room->code, $identities[$r['bellkeeper']], ['type' => 'night', 'phase_id' => $phase - 1, 'use_ability' => true]));
        foreach (['bellkeeper', 'medium', 'dreamweaver'] as $role) {
            $this->assertFalse($this->engine->access($room->code, $identities[$r[$role]])['me']['ability_used']);
        }
        $this->withSession(['chanting.identity' => $identities[$r['bellkeeper']]])
            ->postJson('/rooms/'.$room->code.'/actions', ['type' => 'night', 'phase_id' => $phase, 'use_ability' => true])->assertOk()->assertJsonPath('me.ability_used', true);
    }

    public static function limitedRoles(): array
    {
        return ['oracle' => ['oracle'], 'medium' => ['medium'], 'dreamweaver' => ['dreamweaver'], 'bellkeeper' => ['bellkeeper']];
    }

    #[DataProvider('limitedRoles')]
    public function test_limited_abilities_obey_curses_and_cannot_be_reused_on_later_nights(string $role): void
    {
        [$room, $identities] = $this->match(playerCount: 10);
        $r = $this->roles($room);
        $identity = $identities[$r[$role]];
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['players'][$r['townsperson']]['alive'] = false;
        $room->update(['state' => $s]);
        $target = match ($role) {
            'oracle' => $r['warden'], 'medium' => $r['townsperson'], 'dreamweaver' => $r['oracle'], default => null
        };
        $ability = ['use_ability' => true, 'target' => $target];
        $curse = $this->afflict($room, $r[$role], 'puzzle');
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', $ability));
        $this->assertFalse($this->engine->access($room->code, $identity)['me']['ability_used']);
        $this->act($room, $identity, 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        $this->act($room, $identity, 'night');
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->assertFalse($this->engine->access($room->code, $identity)['me']['ability_used']);
        $this->act($room, $identity, 'night', $ability);
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', $ability));
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->assertTrue(app(MatchEngine::class)->access($room->code, $identity)['me']['ability_used']);
        $this->assertRejected(fn () => $this->act($room, $identity, 'night', $ability));
        $this->act($room, $identity, 'night');
    }

    public function test_limited_ability_recaps_archive_and_rematches_restore_uses(): void
    {
        [$room, $identities] = $this->match(playerCount: 10);
        $r = $this->roles($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['players'][$r['townsperson']]['alive'] = false;
        $room->update(['state' => $s]);
        $this->act($room, $identities[$r['oracle']], 'night', ['use_ability' => true, 'target' => $r['warden']]);
        $this->act($room, $identities[$r['medium']], 'night', ['use_ability' => true, 'target' => $r['townsperson']]);
        $this->act($room, $identities[$r['bellkeeper']], 'night', ['use_ability' => true]);
        $this->act($room, $identities[$r['dreamweaver']], 'night', ['use_ability' => true, 'target' => $r['bellkeeper']]);
        $this->expire($room);
        $s = $room->state;
        $s['tokens'] = $s['threshold'];
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->expire($room);
        $recap = $this->engine->access($room->code, 'secret-0')['recap'];
        $actions = array_column($recap['rounds'][0]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$r['oracle']]['used_ability']);
        $this->assertSame('town', $actions[$r['medium']]['true_alignment']);
        $this->assertTrue($actions[$r['bellkeeper']]['disrupted']);
        $this->assertTrue($actions[$r['dreamweaver']]['used_ability']);
        $this->assertSame($recap['rounds'], GameMatch::firstOrFail()->getAttribute('recap')['rounds']);
        $this->act($room, 'secret-0', 'rematch');
        foreach ($identities as $identity) {
            $view = $this->engine->access($room->code, $identity);
            $this->assertFalse($view['me']['ability_used']);
            $this->assertSame([], $view['me']['results']);
        }
    }

    public function test_three_player_town_can_win_by_banishing_the_lone_cultist(): void
    {
        [$room, $identities] = $this->match(playerCount: 3);
        $cultist = $this->roles($room)['veilweaver'];
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $cultist);
        $this->assertSame('town', $room->fresh()->state['winner']);
        $this->assertSame('finished', $room->fresh()->state['phase']);
    }

    /** @return array<string, array{int}> */
    public static function smallGatherings(): array
    {
        return ['three players' => [3], 'four players' => [4]];
    }

    #[DataProvider('smallGatherings')]
    public function test_small_gathering_lone_cultist_can_complete_the_ritual(int $playerCount): void
    {
        [$room, $identities] = $this->match(mission: null, playerCount: $playerCount);
        $cultist = $this->roles($room)['veilweaver'];
        $goal = $playerCount === 3 ? 2 : 3;
        $this->assertSame($goal, $room->state['threshold']);
        $this->expire($room);
        for ($night = 1; $night <= $goal; $night++) {
            $this->act($room, $identities[$cultist], 'night');
            $this->expire($room);
            if ($night < $goal) {
                $this->expire($room);
                $this->expire($room);
            }
        }
        $this->assertSame('discussion', $room->state['phase']);
        $this->assertNull($room->state['winner']);
        $this->assertDatabaseCount('game_matches', 0);
        $this->expire($room);
        $this->assertSame('voting', $room->state['phase']);
        $this->expire($room);
        $this->assertSame('cult', $room->state['winner']);
        $this->assertSame('finished', $room->state['phase']);
        $this->assertSame($goal, $room->state['tokens']);
        $this->assertCount($playerCount, array_filter($room->state['players'], fn (array $player): bool => $player['alive']));
        $view = $this->engine->access($room->code, 'secret-0');
        $this->assertCount($goal, $view['recap']['rounds']);
        $this->assertArrayHasKey('vote', $view['recap']['rounds'][$goal - 1]);
        $this->assertFalse($view['ritual']['final_vote']);
    }

    public function test_two_players_cannot_start_and_a_sixteenth_cannot_join(): void
    {
        $room = $this->engine->create('limit-0', 'Neighbor 0');
        $this->engine->join($room->code, 'limit-1', 'Neighbor 1');
        $this->act($room, 'limit-0', 'ready');
        $this->act($room, 'limit-1', 'ready');
        $this->assertRejected(fn () => $this->act($room, 'limit-0', 'start'));
        for ($i = 2; $i < 15; $i++) {
            $this->engine->join($room->code, 'limit-'.$i, 'Neighbor '.$i);
        }
        $this->assertRejected(fn () => $this->engine->join($room->code, 'limit-15', 'Neighbor 15'));
        $this->assertCount(15, $room->fresh()->state['players']);
    }

    public function test_private_views_reveal_only_own_role_and_cult_allies(): void
    {
        [$room, $identities] = $this->match();
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            $p = $room->fresh()->state['players'][$id];
            $this->assertSame($p['role'], $view['me']['role']);
            foreach ($view['players'] as $public) {
                $this->assertSame(['id', 'name', 'alive', 'ready', 'connected', 'afk', 'in_room', 'character', 'elimination_reason', 'oath', 'discussion_ready'], array_keys($public));
            }
            $this->assertStringNotContainsString('identity', json_encode($view));
            $this->assertArrayNotHasKey('actions', $view);
            $this->assertArrayNotHasKey('awards', $view);
            if ($p['alignment'] === 'cult') {
                $this->assertSame(config('game.missions.concord'), $view['me']['mission']);
                $this->assertCount(1, $view['me']['allies']);
            } else {
                $this->assertNull($view['me']['mission']);
                $this->assertSame([], $view['me']['allies']);
            }
        }
        $event = new RoomUpdated($room->id, 42);
        $this->assertSame(['revision' => 42], $event->broadcastWith());
        $this->assertSame('private-room.'.$room->id, $event->broadcastOn()->name);
        $this->assertArrayNotHasKey('state', $room->toArray());
    }

    public function test_guest_http_seat_reconnects_and_outsider_cannot_read_or_act(): void
    {
        $code = $this->postJson('/rooms', ['name' => 'Harbor keeper'])->assertCreated()->json('code');
        $first = $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->json();
        $this->get('/rooms/'.$code)->assertOk();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.id', $first['me']['id']);
        $this->postJson('/rooms/join', ['code' => $code, 'name' => 'Changed name'])->assertOk();
        $this->assertCount(1, GameRoom::first()->state['players']);
        $this->withSession(['chanting.identity' => 'outsider']);
        $this->getJson('/rooms/'.$code.'/state')->assertForbidden();
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'ready', 'phase_id' => 1])->assertForbidden();
    }

    public function test_channel_authorization_is_limited_to_members_and_their_room(): void
    {
        config(['broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        $code = $this->postJson('/rooms', ['name' => 'Harbor keeper'])->json('code');
        $room = GameRoom::first();
        $url = '/rooms/'.$code.'/broadcast-auth';
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.'.$room->id])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.99999'])->assertForbidden();
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'presence-room.'.$room->id])->assertForbidden();
        $this->withSession(['chanting.identity' => 'outsider'])->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.'.$room->id])->assertForbidden();
    }

    public function test_lobby_requires_minimum_players_and_ready_members_and_host(): void
    {
        $room = $this->engine->create('host', 'Host');
        $this->assertRejected(fn () => $this->act($room, 'host', 'start'));
        for ($i = 1; $i < 5; $i++) {
            $this->engine->join($room->code, 'guest'.$i, 'Guest '.$i);
        }
        $this->assertRejected(fn () => $this->act($room, 'guest1', 'start'));
        $this->assertRejected(fn () => $this->act($room, 'host', 'start'));
        $this->assertRejected(fn () => $this->engine->join($room->code, 'another', 'HOST'));
    }

    public function test_players_on_shared_wifi_have_independent_polling_limits(): void
    {
        $room = $this->engine->create('wifi-0', 'Neighbor 0');
        for ($i = 1; $i < 10; $i++) {
            $this->engine->join($room->code, 'wifi-'.$i, 'Neighbor '.$i);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->withSession(['chanting.identity' => 'wifi-'.$i]);
            for ($poll = 0; $poll < 15; $poll++) {
                $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
            }
        }
        for ($poll = 15; $poll < 120; $poll++) {
            $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
        }
        $this->getJson('/rooms/'.$room->code.'/state')->assertTooManyRequests();
    }

    public function test_veil_reverses_the_single_investigation_without_changing_own_truth(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->expire($room);
        $oracle = $this->engine->access($room->code, $identities[$r['oracle']]);
        $this->assertSame('town', $oracle['me']['results'][0]['alignment']);
        $cult = $this->engine->access($room->code, $identities[$r['acolyte']]);
        $this->assertSame('cult', $cult['me']['alignment']);
        $this->assertSame(config('game.missions.concord'), $cult['me']['mission']);
        $this->assertSame([], $cult['me']['results']);
        $this->expire($room); // discussion -> voting
        $this->expire($room); // voting -> night
        $this->assertRejected(fn () => $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]));
        $this->act($room, $identities[$r['oracle']], 'night');
        $this->expire($room);
        $oracle = $this->engine->access($room->code, $identities[$r['oracle']]);
        $this->assertCount(1, $oracle['me']['results']);
        $this->assertTrue($oracle['me']['ability_used']);
        $night = array_column($room->fresh()->state['rounds'][2]['night']['actions'], null, 'player_id');
        $this->assertNull($night[$r['oracle']]['apparent_alignment']);
    }

    public function test_existing_oracle_readings_consume_the_charge_without_exposing_it_to_other_players(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['players'][$r['oracle']]['results'][] = ['day' => 0, 'target' => 'Earlier neighbor', 'alignment' => 'town'];
        unset($s['players'][$r['oracle']]['ability_used']);
        $room->update(['state' => $s]);
        $oracle = $identities[$r['oracle']];
        $this->assertTrue($this->engine->access($room->code, $oracle)['me']['ability_used']);
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['acolyte']]));
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['acolyte'], 'use_ability' => true]));
        $view = $this->engine->access($room->code, $identities[$r['townsperson']]);
        $public = array_column($view['players'], null, 'id')[$r['oracle']];
        $this->assertArrayNotHasKey('ability_used', $public);
        $this->assertArrayNotHasKey('results', $public);
        $this->act($room, $oracle, 'night');
        $this->expire($room);
        $this->assertCount(1, $this->engine->access($room->code, $oracle)['me']['results']);
    }

    public function test_veil_can_make_town_appear_cult(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['townsperson']]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['townsperson']]);
        $this->expire($room);
        $this->assertSame('cult', $this->engine->access($room->code, $identities[$r['oracle']])['me']['results'][0]['alignment']);
    }

    public function test_duplicate_invalid_and_stale_actions_are_rejected_and_deadline_is_committed(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $oracle = $identities[$r['oracle']];
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['use_ability' => true]));
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['oracle']]));
        $this->assertRejected(fn () => $this->act($room, $identities[$r['townsperson']], 'night', ['target' => $r['oracle']]));
        $this->act($room, $oracle, 'night', ['target' => $r['acolyte']]);
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['veilweaver']]));
        $phase = $room->fresh()->state['phase_id'];
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->assertRejected(fn () => $this->engine->access($room->code, $oracle, ['type' => 'night', 'phase_id' => $phase, 'target' => $r['acolyte']]));
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $this->assertCount(1, $this->engine->access($room->code, $oracle)['me']['results']);
        $this->engine->resolve($room->id);
        $this->assertCount(1, $this->engine->access($room->code, $oracle)['me']['results']);
    }

    public function test_all_ready_and_all_night_actions_advance_early_with_single_awards(): void
    {
        [$room, $identities] = $this->match();
        foreach ($identities as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $this->assertSame('night', $room->fresh()->state['phase']);
        $r = $this->roles($room);
        foreach ($identities as $id => $identity) {
            $this->act($room, $identity, 'night', $id === $r['oracle'] ? ['target' => $r['acolyte']] : []);
        }
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $this->assertSame(2, $room->fresh()->state['tokens']);
        $this->engine->resolve($room->id);
        $this->assertSame(2, $room->fresh()->state['tokens']);
        $this->assertCount(2, $room->fresh()->state['awards']);
    }

    public function test_concord_needs_every_surviving_cultist_to_chant(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->expire($room);
        $this->assertSame(0, $room->state['tokens']);
    }

    public function test_shadows_excludes_investigated_cult_even_if_veiled(): void
    {
        [$room, $identities] = $this->match('shadows');
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]);
        $this->expire($room);
        $this->assertSame(1, $room->state['tokens']);
    }

    public function test_patience_scores_first_night_but_not_after_cult_banishment_and_dead_cannot_act(): void
    {
        [$room, $identities] = $this->match('patience');
        $r = $this->roles($room);
        $this->expire($room);
        foreach (['acolyte', 'veilweaver'] as $role) {
            $this->act($room, $identities[$r[$role]], 'night');
        }
        $this->expire($room);
        $this->assertSame(2, $room->state['tokens']);
        $this->expire($room);
        $this->banish($room, $identities, $r['acolyte']);
        $this->assertFalse($room->fresh()->state['players'][$r['acolyte']]['alive']);
        $this->assertRejected(fn () => $this->act($room, $identities[$r['acolyte']], 'night'));
        $this->act($room, $identities[$r['veilweaver']], 'night');
        $this->expire($room);
        $this->assertSame(2, $room->state['tokens']);
        $this->assertRejected(fn () => $this->act($room, $identities[$r['acolyte']], 'chat', ['body' => 'Secret from the grave']));
    }

    /** @param array<string, string> $identities */
    private function banish(GameRoom $room, array $identities, string $target): void
    {
        foreach ($room->fresh()->state['players'] as $id => $player) {
            if ($player['alive'] && ! in_array($player['curse']['type'] ?? null, ['puzzle', 'mist'], true)) {
                $this->act($room, $identities[$id], 'vote', ['target' => $id === $target ? null : $target]);
            }
        }
        if ($room->fresh()->state['phase'] === 'voting') {
            $this->expire($room);
        }
    }

    public function test_town_victory_reveals_roster_and_rematch_preserves_seats_clears_secrets(): void
    {
        [$room, $identities] = $this->match();
        $matchId = $this->engine->access($room->code, 'secret-0')['match_id'];
        $this->assertTrue(Str::isUuid($matchId));
        $this->assertSame($matchId, $this->engine->access($room->code, 'secret-1')['match_id']);
        $r = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $r['acolyte']);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $r['veilweaver']);
        $view = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('town', $view['winner']);
        $this->assertSame('finished', $view['phase']);
        $this->assertSame($matchId, $view['match_id']);
        $this->assertNull($view['deadline']);
        $this->assertArrayHasKey('role', $view['players'][0]);
        $this->assertRejected(fn () => $this->act($room, 'secret-1', 'rematch'));
        $replay = $this->act($room, 'secret-0', 'rematch');
        $this->assertSame('lobby', $replay['phase']);
        $this->assertNull($replay['match_id']);
        $this->assertSame($view['me']['id'], $replay['me']['id']);
        $this->assertNull($replay['me']['role']);
        $this->assertNull($replay['me']['mission']);
        $this->assertSame([], $replay['me']['results']);
        $this->assertSame([], $room->fresh()->state['awards']);
        foreach ($replay['players'] as $p) {
            $this->assertTrue($p['alive']);
            $this->assertFalse($p['ready']);
        }
        foreach ($identities as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $nextMatch = $this->act($room, 'secret-0', 'start');
        $this->assertTrue(Str::isUuid($nextMatch['match_id']));
        $this->assertNotSame($matchId, $nextMatch['match_id']);
    }

    public function test_ritual_victory_waits_for_the_final_vote_after_three_nights(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        for ($night = 1; $night <= 3; $night++) {
            foreach (['acolyte', 'veilweaver'] as $role) {
                $this->act($room, $identities[$r[$role]], 'night');
            }
            $this->expire($room);
            if ($night < 3) {
                $this->expire($room);
                $this->expire($room);
            }
        }
        $this->assertSame('discussion', $room->state['phase']);
        $this->assertNull($room->state['winner']);
        $this->expire($room);
        $this->expire($room);
        $this->assertSame('finished', $room->state['phase']);
        $this->assertSame('cult', $room->state['winner']);
        $this->assertSame(6, $room->state['tokens']);
        $this->assertNull($room->deadline);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function finalRitualVotes(): iterable
    {
        yield 'last cultist banished at 2 of 2' => ['last_cultist', 3, 'town'];
        yield 'town player banished' => ['wrong_player', 3, 'cult'];
        yield 'everyone abstains' => ['abstain', 3, 'cult'];
        yield 'vote is tied' => ['tie', 3, 'cult'];
        yield 'everyone misses the deadline' => ['timeout', 3, 'cult'];
        yield 'one of two cultists banished after overshooting goal' => ['one_cultist', 5, 'cult'];
    }

    #[DataProvider('finalRitualVotes')]
    public function test_full_ritual_grants_exactly_one_final_vote_with_town_victory_taking_precedence(string $outcome, int $playerCount, string $winner): void
    {
        [$room, $identities] = $this->match(mission: null, playerCount: $playerCount);
        $roles = $this->roles($room);
        $this->expire($room);
        $state = $room->fresh()->state;
        $state['tokens'] = $state['threshold'] - 1;
        if ($playerCount === 5) {
            $state['mission'] = config('game.missions.concord');
        }
        $room->update(['state' => $state]);
        $this->assertFalse($this->engine->access($room->code, 'secret-0')['ritual']['final_vote']);

        // Complete the night by submissions, rather than by the deadline.
        foreach ($state['players'] as $id => $player) {
            $this->act($room, $identities[$id], 'night', ['target' => $player['role'] === 'oracle' ? $roles['veilweaver'] : null]);
        }
        $view = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('discussion', $view['phase']);
        $this->assertSame($playerCount === 3 ? 2 : 7, $view['ritual']['tokens']);
        $this->assertTrue($view['ritual']['final_vote']);
        $this->assertNull($view['winner']);
        $this->assertNull($view['recap']);
        foreach ($view['players'] as $player) {
            $this->assertArrayNotHasKey('role', $player);
        }
        $this->assertStringContainsString('One final discussion and vote', implode(' ', $view['log']));
        $this->assertDatabaseCount('game_matches', 0);
        $this->assertSame($view, $this->engine->access($room->code, 'secret-0'));

        foreach ($identities as $identity) {
            $this->act($room, $identity, 'discussion_ready');
        }
        $voting = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('voting', $voting['phase']);
        $this->assertTrue($voting['ritual']['final_vote']);
        $this->assertNull($voting['winner']);
        $this->assertDatabaseCount('game_matches', 0);

        if ($outcome === 'timeout') {
            $this->expire($room);
        } else {
            $ids = array_keys($identities);
            foreach ($ids as $index => $id) {
                $target = match ($outcome) {
                    'last_cultist', 'one_cultist' => $id === $roles['veilweaver'] ? null : $roles['veilweaver'],
                    'wrong_player' => $id === $roles['oracle'] ? null : $roles['oracle'],
                    'tie' => $ids[($index + 1) % count($ids)],
                    default => null,
                };
                $this->act($room, $identities[$id], 'vote', ['target' => $target]);
            }
        }
        $finished = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('finished', $finished['phase']);
        $this->assertSame($winner, $finished['winner']);
        $this->assertFalse($finished['ritual']['final_vote']);
        $this->assertNull($finished['deadline']);
        $this->assertSame(1, $finished['day']);
        $this->assertCount(1, $finished['recap']['rounds']);
        $this->assertCount($playerCount, $finished['recap']['rounds'][0]['vote']['ballots']);
        $this->assertDatabaseCount('game_matches', 1);
        $archive = GameMatch::firstOrFail();
        $this->assertSame($finished['recap']['rounds'], $archive->getAttribute('recap')['rounds']);
        $this->engine->resolve($room->id);
        $this->assertRejected(fn () => $this->engine->access($room->code, 'secret-0', ['type' => 'vote', 'phase_id' => $voting['phase_id']]));
        $this->assertSame($finished, $this->engine->access($room->code, 'secret-0'));
        $this->assertDatabaseCount('game_matches', 1);
    }

    public function test_tied_vote_and_missing_votes_abstain_without_elimination(): void
    {
        [$room, $identities] = $this->match();
        $ids = array_keys($identities);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $targets = [$ids[1], $ids[0], $ids[0], $ids[1], null];
        foreach ($ids as $i => $id) {
            $this->act($room, $identities[$id], 'vote', ['target' => $targets[$i]]);
        }
        $this->assertCount(5, array_filter($room->fresh()->state['players'], fn ($p) => $p['alive']));
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $identities[$ids[0]], 'vote', ['target' => $ids[1]]);
        $this->expire($room);
        $this->assertCount(5, array_filter($room->state['players'], fn ($p) => $p['alive']));
    }

    public function test_chat_is_public_only_in_allowed_phases_and_names_are_validated(): void
    {
        $this->postJson('/rooms', ['name' => ' '])->assertUnprocessable();
        [$room, $identities] = $this->match();
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => 'During reveal']));
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => 'During night']));
        $this->expire($room);
        $this->act($room, 'secret-0', 'chat', ['body' => 'The lighthouse looks suspicious.']);
        $this->assertSame('The lighthouse looks suspicious.', $this->engine->access($room->code, 'secret-1')['messages'][0]['body']);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => str_repeat('a', 281)]));
    }

    public function test_chat_bubble_metadata_uses_the_authenticated_speaker_and_server_time(): void
    {
        $this->freezeTime();
        [$room] = $this->match();
        $this->expire($room);
        $this->expire($room);
        $host = $room->fresh()->state['host_id'];
        $other = array_values(array_diff(array_keys($room->state['players']), [$host]))[0];
        $sentAt = now()->toISOString();
        $this->withSession(['chanting.identity' => 'secret-0'])
            ->postJson('/rooms/'.$room->code.'/actions', [
                'type' => 'chat', 'phase_id' => $room->state['phase_id'],
                'body' => '<b>Look by the lighthouse</b>',
                'player_id' => $other, 'sent_at' => '2000-01-01T00:00:00Z',
            ])->assertOk();
        $message = $this->engine->access($room->code, 'secret-1')['messages'][0];
        $this->assertSame($host, $message['player_id']);
        $this->assertSame('Player 0', $message['name']);
        $this->assertSame($sentAt, $message['sent_at']);
        $this->assertSame('<b>Look by the lighthouse</b>', $message['body']);
        $this->assertSame(['id', 'player_id', 'name', 'body', 'day', 'sent_at'], array_keys($message));
    }

    public function test_guests_receive_stable_random_characters_and_cannot_choose_them(): void
    {
        $this->postJson('/rooms', ['name' => 'Guest', 'character' => 'mariner'])->assertUnprocessable();
        $code = $this->postJson('/rooms', ['name' => 'Guest'])->assertCreated()->json('code');
        $view = $this->getJson('/rooms/'.$code.'/state')->assertOk()->json();
        $character = $view['me']['character'];
        $this->assertContains($character, app(AccountProgression::class)->starterCharacterIds());
        $this->assertSame($character, $view['players'][0]['character']);
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', $character);
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => 'baker', 'phase_id' => 1])->assertUnprocessable();
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => 1])->assertForbidden();
        $this->withSession(['chanting.identity' => 'new-guest']);
        $this->postJson('/rooms/join', ['name' => 'Other guest', 'code' => $code, 'character' => 'baker'])->assertUnprocessable();
        $this->postJson('/rooms/join', ['name' => 'Other guest', 'code' => $code])->assertOk();
        $this->assertCount(2, GameRoom::first()->state['players']);
    }

    public function test_accounts_can_choose_every_starter_character_and_change_only_in_lobby(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/rooms', ['name' => 'Neighbor', 'character' => 'invented'])->assertUnprocessable();
        $code = $this->postJson('/rooms', ['name' => 'Neighbor', 'character' => 'botanist'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', 'botanist');
        $starters = app(AccountProgression::class)->starterCharacterIds();
        foreach ($starters as $character) {
            $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => $character, 'phase_id' => 1])
                ->assertOk()->assertJsonPath('me.character', $character);
        }
        $lastCharacter = $starters[array_key_last($starters)];
        $nextCode = $this->postJson('/rooms', ['name' => 'Neighbor'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$nextCode.'/state')->assertJsonPath('me.character', $lastCharacter);
        $room = GameRoom::where('code', $code)->firstOrFail();
        $s = $room->state;
        $s['phase'] = 'reveal';
        $room->update(['state' => $s]);
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => 'baker', 'phase_id' => 1])->assertUnprocessable();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', $lastCharacter);
        $this->withSession(['chanting.identity' => 'account-join']);
        $this->postJson('/rooms/join', ['name' => 'Joined', 'code' => $nextCode, 'character' => 'archivist'])->assertOk();
        $this->getJson('/rooms/'.$nextCode.'/state')->assertJsonPath('me.character', 'archivist');
    }

    public function test_characters_survive_roles_rematches_and_legacy_rooms(): void
    {
        [$room, $identities] = $this->match();
        $before = array_column($room->fresh()->state['players'], 'character', 'id');
        $s = $room->fresh()->state;
        $s['phase'] = 'finished';
        $room->update(['state' => $s, 'deadline' => null]);
        $this->act($room, 'secret-0', 'rematch');
        $this->assertSame($before, array_column($room->fresh()->state['players'], 'character', 'id'));
        $s = $room->fresh()->state;
        foreach ($s['players'] as &$player) {
            unset($player['character']);
        }
        unset($player);
        $room->update(['state' => $s]);
        $first = $this->engine->access($room->code, 'secret-0');
        $second = $this->engine->access($room->code, 'secret-0');
        $this->assertSame(array_column($first['players'], 'character'), array_column($second['players'], 'character'));
        $this->assertContains($first['me']['character'], app(AccountProgression::class)->starterCharacterIds());
    }

    public function test_discussion_readiness_is_public_and_unanimous_readiness_starts_voting_once(): void
    {
        [$room, $identities] = $this->match();
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->expire($room);
        $phase = $room->fresh()->state['phase_id'];
        $this->withSession(['chanting.identity' => 'secret-0']);
        $view = $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'discussion_ready', 'phase_id' => $phase])->assertOk()->json();
        $this->assertSame('discussion', $view['phase']);
        $this->assertTrue($view['me']['submitted']);
        $other = $this->engine->access($room->code, 'secret-1');
        $this->assertCount(1, array_filter($other['players'], fn (array $p): bool => $p['discussion_ready']));
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->act($room, 'secret-0', 'chat', ['body' => 'Still here if you have questions.']);
        foreach ($identities as $identity) {
            if ($identity !== 'secret-0') {
                $view = $this->act($room, $identity, 'discussion_ready');
            }
        }
        $this->assertSame('voting', $view['phase']);
        $this->assertSame($phase + 1, $view['phase_id']);
        $this->assertFalse($view['me']['submitted']);
        $this->assertCount(0, array_filter($view['players'], fn (array $p): bool => $p['discussion_ready']));
        $this->engine->resolve($room->id);
        $this->assertSame($phase + 1, $room->fresh()->state['phase_id']);
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'discussion_ready', 'phase_id' => $phase])->assertUnprocessable();
    }

    public function test_banished_players_cannot_ready_and_do_not_hold_up_discussion(): void
    {
        [$room, $identities] = $this->match();
        $this->expire($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $deadId = array_search('secret-0', $identities, true);
        $s['players'][$deadId]['alive'] = false;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        foreach ($identities as $identity) {
            if ($identity !== 'secret-0') {
                $this->act($room, $identity, 'discussion_ready');
            }
        }
        $this->assertSame('voting', $room->fresh()->state['phase']);
    }

    public function test_readiness_deadline_still_advances_and_home_goals_match_game_rules(): void
    {
        [$room] = $this->match();
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'secret-0', 'discussion_ready');
        $this->expire($room);
        $this->assertSame('voting', $room->state['phase']);
        $this->assertSame([2, 3, 6, 8, 9, 10, 11, 12, 16, 16, 20, 20, 20], array_column($this->engine->rules()['ritual_goals'], 'steps'));
    }

    #[DataProvider('rosterSizes')]
    public function test_actual_mission_selection_and_goals_match_room_size(int $playerCount, int $cultistCount): void
    {
        [$room, $identities] = $this->match(mission: null, playerCount: $playerCount);
        $s = $room->fresh()->state;
        $this->assertSame([3 => 2, 4 => 3, 5 => 6, 6 => 8, 7 => 9, 8 => 10, 9 => 11, 10 => 12, 11 => 16, 12 => 16, 13 => 20, 14 => 20, 15 => 20][$playerCount], $s['threshold']);
        if ($cultistCount === 1) {
            $this->assertSame('solitary', $s['mission']['id']);
            $this->expire($room);
            $roles = $this->roles($room);
            $this->act($room, $identities[$roles['veilweaver']], 'night');
            $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['veilweaver']]);
            $this->expire($room);
            $this->assertSame(1, $room->state['tokens'], 'Investigation must not block a solitary chant.');
        } else {
            $this->assertContains($s['mission']['id'], ['concord', 'shadows', 'patience']);
        }
    }

    public function test_one_on_one_ends_shadows_stalemate_but_larger_parity_does_not_win(): void
    {
        [$room, $identities] = $this->match('shadows', playerCount: 3);
        $roles = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$roles['veilweaver']], 'night');
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['veilweaver']]);
        $this->expire($room);
        $this->assertSame(0, $room->state['tokens']);
        $this->expire($room);
        $this->banish($room, $identities, $roles['townsperson']);
        $view = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('cult', $view['winner']);
        $this->assertStringContainsString('One cultist and one town player', $view['win_reason']);
        $this->assertNull($view['deadline']);
        $this->assertSame($roles['townsperson'], $view['recap']['rounds'][0]['vote']['banished_id']);

        [$larger, $identities] = $this->match();
        $roles = $this->roles($larger);
        $this->expire($larger);
        $this->expire($larger);
        $this->expire($larger);
        $this->banish($larger, $identities, $roles['townsperson']);
        $this->assertSame('night', $larger->fresh()->state['phase']);
        $this->assertNull($larger->state['winner']);
    }

    public function test_recap_is_private_until_victory_and_archive_survives_rematch_once(): void
    {
        [$room, $identities] = $this->match(mission: null, playerCount: 3);
        $roles = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$roles['veilweaver']], 'night', ['target' => $roles['townsperson']]);
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['townsperson']]);
        $this->expire($room);
        foreach ($identities as $identity) {
            $view = $this->engine->access($room->code, $identity);
            $this->assertNull($view['recap']);
            $this->assertArrayNotHasKey('rounds', $view);
            // The journal uses the match identifier; recap contents stay private.
            $this->assertSame($room->fresh()->state['match_id'], $view['match_id']);
        }
        $this->assertDatabaseCount('game_matches', 0);
        $this->expire($room);
        // An explicit abstention and missing votes are distinct in the final recap.
        while ($curse = $room->fresh()->state['players'][$roles['townsperson']]['curse']) {
            $this->act($room, $identities[$roles['townsperson']], 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        }
        $this->act($room, $identities[$roles['townsperson']], 'vote');
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $roles['veilweaver']);
        $view = $this->engine->access($room->code, 'secret-0');
        $recap = $view['recap'];
        $this->assertTrue($recap['complete']);
        $this->assertSame(3, $recap['player_count']);
        $this->assertSame(2, $recap['nights']);
        $this->assertSame('solitary', $recap['mission']['id']);
        $actions = array_column($recap['rounds'][0]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$roles['veilweaver']]['contributed']);
        $this->assertSame($roles['townsperson'], $actions[$roles['veilweaver']]['target_id']);
        $this->assertTrue($actions[$roles['oracle']]['veiled']);
        $this->assertSame('cult', $actions[$roles['oracle']]['apparent_alignment']);
        $this->assertFalse($actions[$roles['townsperson']]['submitted']);
        $this->assertSame(['night' => 4, 'vote' => 2], $recap['missed_actions']);
        $this->assertGreaterThan(0, $recap['duration_seconds']);
        $ballots = array_column($recap['rounds'][0]['vote']['ballots'], null, 'player_id');
        $this->assertTrue($ballots[$roles['townsperson']]['submitted']);
        $this->assertNull($ballots[$roles['townsperson']]['target_id']);
        $this->assertFalse($ballots[$roles['oracle']]['submitted']);
        $this->assertStringNotContainsString('identity', json_encode($recap));
        $this->assertDatabaseCount('game_matches', 1);
        $archive = GameMatch::firstOrFail();
        $this->assertSame($recap['rounds'], $archive->getAttribute('recap')['rounds']);
        $this->assertArrayNotHasKey('recap', $archive->toArray());
        $this->assertStringNotContainsString('identity', json_encode($archive->getAttribute('recap')));
        $this->engine->resolve($room->id);
        $this->engine->access($room->code, 'secret-1');
        $this->assertDatabaseCount('game_matches', 1);
        $this->act($room, 'secret-0', 'chat', ['body' => 'That veil fooled me.']);
        $this->assertSame($recap, $this->engine->access($room->code, 'secret-0')['recap']);
        $rematch = $this->act($room, 'secret-0', 'rematch');
        $this->assertNull($rematch['recap']);
        $this->assertArrayNotHasKey('rounds', $room->fresh()->state);
        $this->assertDatabaseCount('game_matches', 1);
        foreach ($identities as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $this->act($room, 'secret-0', 'start');
        $this->assertNotSame($archive->getKey(), $room->fresh()->state['match_id']);
        $this->assertSame([], $room->fresh()->state['rounds']);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $this->roles($room)['veilweaver']);
        $this->assertDatabaseCount('game_matches', 2);
        $this->artisan('game:stats --json')->expectsOutputToContain('"matches": 2')->assertSuccessful();
    }

    public function test_in_progress_rules_keep_their_goal_mission_and_phase_lengths(): void
    {
        [$room] = $this->match(mission: null, playerCount: 3);
        config(['game.ritual_goals_by_player_count.3' => 9, 'game.seconds.night' => 300]);
        $this->expire($room);
        $this->assertSame(2, $room->state['threshold']);
        $this->assertSame('solitary', $room->state['mission']['id']);
        $this->assertSame(45, (int) now()->diffInSeconds($room->deadline));
        $this->assertSame(45, $this->engine->access($room->code, 'secret-0')['rules']['seconds']['night']);
    }

    public function test_legacy_matches_finish_with_partial_tracking(): void
    {
        [$room, $identities] = $this->match(playerCount: 3);
        $s = $room->fresh()->state;
        unset($s['match_id'], $s['match_rules'], $s['started_at'], $s['rounds'], $s['missed_actions']);
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $this->roles($room)['veilweaver']);
        $recap = $this->engine->access($room->code, 'secret-0')['recap'];
        $this->assertFalse($recap['complete']);
        $this->assertSame('legacy', $recap['rules_version']);
        $this->assertNull($recap['duration_seconds']);
        $this->assertDatabaseHas('game_matches', ['rules_version' => 'legacy', 'recap_complete' => false]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function chosenCurses(): iterable
    {
        foreach (['veilweaver', 'acolyte'] as $role) {
            foreach (['puzzle', 'mist', 'misdirection'] as $type) {
                yield $role.'-'.$type => [$role, $type];
            }
        }
    }

    #[DataProvider('chosenCurses')]
    public function test_cultists_choose_a_curse_and_still_chant(string $role, string $type): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['tokens'] = $type === 'misdirection' ? 4 : 0;
        $room->update(['state' => $s]);
        $this->withSession(['chanting.identity' => $identities[$roles[$role]]]);
        $this->postJson('/rooms/'.$room->code.'/actions', [
            'type' => 'night', 'phase_id' => $s['phase_id'],
            'target' => $roles['townsperson'], 'curse_type' => $type,
        ])->assertOk();
        $this->assertSame($type, $room->fresh()->state['actions'][$roles[$role]]['curse_type']);
        $this->assertTrue($this->engine->access($room->code, $identities[$roles[$role]])['me']['submitted']);
        $otherRole = $role === 'veilweaver' ? 'acolyte' : 'veilweaver';
        $this->act($room, $identities[$roles[$otherRole]], 'night');
        $this->expire($room);
        $this->assertSame($s['tokens'] + 2, $room->state['tokens']);
        $curse = $this->engine->access($room->code, $identities[$roles['townsperson']])['me']['curse'];
        $this->assertSame($type, $curse['type']);
        $this->assertArrayNotHasKey('solution', $curse);
        $actions = array_column($room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertSame($type, $actions[$roles[$role]]['curse_type']);
        $this->assertTrue($actions[$roles[$role]]['contributed']);
    }

    public function test_curse_selection_rejects_locked_invalid_and_ineligible_choices_without_sealing_an_action(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $roles = $this->roles($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        // Tonight's chants could unlock level 3, but it is not available when choosing.
        $s['tokens'] = (int) ceil($s['threshold'] * 2 / 3) - 1;
        $room->update(['state' => $s]);
        foreach ([
            ['veilweaver', 'misdirection', $roles['townsperson']],
            ['acolyte', 'unknown', $roles['townsperson']],
            ['oracle', 'puzzle', $roles['townsperson']],
            ['dreamweaver', 'mist', $roles['townsperson']],
            ['veilweaver', 'mist', null],
        ] as [$role, $type, $target]) {
            $this->withSession(['chanting.identity' => $identities[$roles[$role]]]);
            $this->postJson('/rooms/'.$room->code.'/actions', [
                'type' => 'night', 'phase_id' => $s['phase_id'],
                'target' => $target, 'curse_type' => $type,
            ])->assertUnprocessable();
            $this->assertSame($s, $room->fresh()->state);
        }
    }

    public function test_veiling_and_acolyte_cursing_apply_one_private_affliction_at_dawn(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $curses = \Mockery::mock(CurseEngine::class)->makePartial();
        $curses->shouldReceive('create')->once()->with(2, 6, 1, 'puzzle')->andReturn((new CurseEngine)->create(2, 6, 1));
        $this->engine = new MatchEngine($curses);
        $this->app->instance(MatchEngine::class, $this->engine);
        $this->expire($room);
        $this->act($room, $identities[$roles['veilweaver']], 'night', ['target' => $roles['townsperson']]);
        $this->act($room, $identities[$roles['acolyte']], 'night', ['target' => $roles['townsperson'], 'curse_type' => 'mist']);
        $this->assertNull($this->engine->access($room->code, $identities[$roles['townsperson']])['me']['curse']);
        $this->expire($room);
        $view = $this->engine->access($room->code, $identities[$roles['townsperson']]);
        $curse = $view['me']['curse'];
        $this->assertSame(2, $curse['level']);
        $this->assertContains($curse['type'], ['puzzle', 'mist']);
        $this->assertArrayNotHasKey('solution', $curse);
        $this->assertSame($curse, $this->engine->access($room->code, $identities[$roles['townsperson']])['me']['curse']);
        foreach ($identities as $id => $identity) {
            $other = $this->engine->access($room->code, $identity);
            $this->assertArrayNotHasKey('curse', $other['players'][0]);
            if ($id !== $roles['townsperson']) {
                $this->assertStringNotContainsString($curse['id'], json_encode($other));
            }
        }
        $this->assertSame(2, $room->fresh()->state['tokens']);
    }

    public function test_acolyte_can_curse_while_chanting_without_reversing_an_oracle_reading(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$roles['acolyte']], 'night', ['target' => $roles['veilweaver']]);
        $this->act($room, $identities[$roles['veilweaver']], 'night');
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['veilweaver']]);
        $this->expire($room);
        $this->assertNotNull($room->state['players'][$roles['veilweaver']]['curse']);
        $this->assertSame('cult', $room->state['players'][$roles['oracle']]['results'][0]['alignment']);
        $this->assertSame(2, $room->state['tokens']);
        $actions = array_column($room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertSame($room->state['players'][$roles['veilweaver']]['curse']['type'], $actions[$roles['acolyte']]['curse_type']);
    }

    /** @return array<string, mixed> */
    private function afflict(GameRoom $room, string $id, string $type): array
    {
        $curse = ['id' => (string) Str::uuid(), 'type' => $type, 'level' => 3, 'day' => $room->fresh()->state['day'],
            ...($type === 'misdirection' ? ['challenge' => null, 'solution' => []] : app(CurseEngine::class)->challenge($type === 'mist' ? 'focus' : 'reverse', 3))];
        $state = $room->fresh()->state;
        $state['players'][$id]['curse'] = $curse;
        $room->update(['state' => $state]);

        return $curse;
    }

    /** @return iterable<string, array{string, string}> */
    public static function selfCurses(): iterable
    {
        foreach (['veilweaver', 'acolyte'] as $role) {
            foreach (['puzzle', 'mist', 'misdirection'] as $type) {
                yield $role.' '.$type => [$role, $type];
            }
        }
    }

    #[DataProvider('selfCurses')]
    public function test_self_curses_chant_apply_privately_and_preserve_role_specific_readings(string $role, string $type): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $id = $roles[$role];
        $this->expire($room);
        $state = $room->state;
        $state['tokens'] = $type === 'misdirection' ? 4 : 0;
        $room->update(['state' => $state]);
        $view = $this->act($room, $identities[$id], 'night', ['target' => $id, 'curse_type' => $type]);
        $this->assertNull($view['me']['curse']);
        $this->act($room, $identities[$roles[$role === 'acolyte' ? 'veilweaver' : 'acolyte']], 'night');
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $id]);
        $this->expire($room);
        $this->assertSame($state['tokens'] + 2, $room->state['tokens']);
        $this->assertSame('cult', $room->state['players'][$id]['alignment']);
        $this->assertSame($role === 'veilweaver' ? 'town' : 'cult', $room->state['players'][$roles['oracle']]['results'][0]['alignment']);
        $curse = $room->state['players'][$id]['curse'];
        $this->assertSame($type, $curse['type']);
        $own = $this->engine->access($room->code, $identities[$id]);
        $this->assertArrayNotHasKey('solution', $own['me']['curse']);
        foreach ($identities as $otherId => $identity) {
            if ($otherId !== $id) {
                $this->assertStringNotContainsString($curse['id'], json_encode($this->engine->access($room->code, $identity)));
            }
        }
        if ($type !== 'misdirection') {
            $this->assertRejected(fn () => $this->act($room, $identities[$id], 'discussion_ready'));
        }
        while ($curse = $room->fresh()->state['players'][$id]['curse']) {
            $this->act($room, $identities[$id], 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        }
        $this->act($room, $identities[$id], 'discussion_ready');
    }

    public function test_protection_blocks_self_curse_but_not_self_veil_or_chant(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $id = $roles['veilweaver'];
        $this->expire($room);
        $this->act($room, $identities[$id], 'night', ['target' => $id]);
        $this->act($room, $identities[$roles['acolyte']], 'night');
        $this->act($room, $identities[$roles['warden']], 'night', ['target' => $id]);
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $id]);
        $this->expire($room);
        $this->assertNull($room->state['players'][$id]['curse']);
        $this->assertSame('town', $room->state['players'][$roles['oracle']]['results'][0]['alignment']);
        $this->assertSame(2, $room->state['tokens']);
    }

    public function test_disruption_stops_self_veil_and_self_curse(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $roles = $this->roles($room);
        $id = $roles['veilweaver'];
        $this->expire($room);
        $this->act($room, $identities[$id], 'night', ['target' => $id]);
        $this->act($room, $identities[$roles['dreamweaver']], 'night', ['target' => $id, 'use_ability' => true]);
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $id]);
        $this->expire($room);
        $this->assertNull($room->state['players'][$id]['curse']);
        $this->assertSame('cult', $room->state['players'][$roles['oracle']]['results'][0]['alignment']);
        $this->assertSame(0, $room->state['tokens']);
    }

    public function test_self_cursing_does_not_allow_other_self_targets_or_self_votes(): void
    {
        [$room, $identities] = $this->match(playerCount: 7);
        $roles = $this->roles($room);
        $this->expire($room);
        foreach (['oracle', 'warden', 'lamplighter', 'dreamweaver'] as $role) {
            $id = $roles[$role];
            $this->assertRejected(fn () => $this->act($room, $identities[$id], 'night', ['target' => $id, 'use_ability' => $role === 'dreamweaver']));
        }
        $this->expire($room);
        $this->expire($room);
        foreach (['veilweaver', 'acolyte'] as $role) {
            $id = $roles[$role];
            $this->assertRejected(fn () => $this->act($room, $identities[$id], 'vote', ['target' => $id]));
        }
    }

    public function test_misdirection_can_redirect_a_curse_to_or_away_from_its_caster(): void
    {
        foreach ([true, false] as $chooseSelf) {
            [$room, $identities] = $this->match();
            $roles = $this->roles($room);
            $id = $roles['acolyte'];
            $other = $roles['oracle'];
            $this->expire($room);
            $s = $room->state;
            foreach ($s['players'] as $pid => &$player) {
                $player['alive'] = in_array($pid, [$id, $other], true);
            }
            unset($player);
            $room->update(['state' => $s]);
            $this->afflict($room, $id, 'misdirection');
            $this->act($room, $identities[$id], 'night', ['target' => $chooseSelf ? $id : $other]);
            $action = $room->fresh()->state['actions'][$id];
            $this->assertSame($chooseSelf ? $id : $other, $action['chosen_target']);
            $this->assertSame($chooseSelf ? $other : $id, $action['target']);
            $this->assertNull($room->fresh()->state['players'][$id]['curse']);
        }
    }

    public function test_puzzle_blocks_targets_and_http_solution_rejects_wrong_stale_and_other_players_answers(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $curse = $this->afflict($room, $roles['oracle'], 'puzzle');
        $oracle = $identities[$roles['oracle']];
        $this->assertRejected(fn () => $this->act($room, $oracle, 'discussion_ready'));
        $payload = ['type' => 'solve_curse', 'phase_id' => $room->fresh()->state['phase_id'], 'curse_id' => $curse['id'], 'answer' => $curse['solution']];
        $this->withSession(['chanting.identity' => $identities[$roles['townsperson']]])
            ->postJson('/rooms/'.$room->code.'/actions', $payload)->assertUnprocessable();
        $this->withSession(['chanting.identity' => $oracle])
            ->postJson('/rooms/'.$room->code.'/actions', [...$payload, 'answer' => []])->assertUnprocessable();
        $this->postJson('/rooms/'.$room->code.'/actions', [...$payload, 'answer' => [(string) Str::uuid()]])->assertUnprocessable();
        $this->assertSame($curse, $room->fresh()->state['players'][$roles['oracle']]['curse']);
        $this->expire($room);
        $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertUnprocessable();
        $this->assertRejected(fn () => $this->act($room, $oracle, 'vote', ['target' => $roles['acolyte']]));
        $this->assertArrayNotHasKey($roles['oracle'], $room->fresh()->state['actions']);
        $payload['phase_id'] = $room->fresh()->state['phase_id'];
        $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertOk()->assertJsonPath('me.curse', null)->assertJsonPath('me.submitted', false);
        $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertUnprocessable();
        $this->act($room, $oracle, 'vote', ['target' => $roles['acolyte']]);
    }

    public function test_curse_can_be_solved_after_main_action_and_mist_only_garbles_victims_chat(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $identities[$roles['oracle']], 'discussion_ready');
        $curse = $this->afflict($room, $roles['oracle'], 'mist');
        $body = 'The village heard chanting near the lighthouse.';
        $normal = $this->act($room, $identities[$roles['acolyte']], 'chat', ['body' => $body]);
        $this->assertSame($body, $normal['messages'][0]['body']);
        $view = $this->engine->access($room->code, $identities[$roles['oracle']]);
        $this->assertNotSame($body, $view['messages'][0]['body']);
        $this->assertSame($roles['acolyte'], $view['messages'][0]['player_id']);
        $this->assertSame($normal['messages'][0]['sent_at'], $view['messages'][0]['sent_at']);
        $this->assertSame($body, $room->fresh()->state['messages'][0]['body']);
        $this->assertSame($view['messages'], $this->engine->access($room->code, $identities[$roles['oracle']])['messages']);
        $solved = $this->act($room, $identities[$roles['oracle']], 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        $this->assertNull($solved['me']['curse']);
        $this->assertTrue($solved['me']['submitted']);
        $this->assertSame($body, $solved['messages'][0]['body']);
    }

    public function test_puzzle_and_mist_lock_room_actions_until_solved_without_stopping_deadlines(): void
    {
        foreach (['puzzle', 'mist'] as $type) {
            [$room, $identities] = $this->match();
            $roles = $this->roles($room);
            $this->expire($room);
            $this->expire($room);
            $curse = $this->afflict($room, $roles['townsperson'], $type);
            $identity = $identities[$roles['townsperson']];
            $deadline = $room->fresh()->deadline;
            $this->assertRejected(fn () => $this->act($room, $identity, 'chat', ['body' => 'Bypass the dialog']));
            $this->assertRejected(fn () => $this->act($room, $identity, 'discussion_ready'));
            $this->assertEquals($deadline, $room->fresh()->deadline);
            $this->expire($room);
            $this->assertSame('voting', $room->state['phase']);
            $this->assertRejected(fn () => $this->act($room, $identity, 'vote'));
            $this->assertRejected(fn () => $this->act($room, $identity, 'vote', ['target' => $roles['oracle']]));
            $this->expire($room);
            $this->assertRejected(fn () => $this->act($room, $identity, 'night'));
            $this->act($room, $identity, 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
            $this->assertTrue($this->act($room, $identity, 'night')['me']['submitted']);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function cursePuzzleSets(): iterable
    {
        foreach ([
            'puzzle' => ['astral-binding', 'drowned-tribute', 'rune-prison'],
            'mist' => ['lost-shore', 'echoing-fog', 'false-lights'],
            'misdirection' => ['crooked-compass', 'mirror-trail', 'false-oracle'],
        ] as $type => $sets) {
            foreach ($sets as $set) {
                yield $set => [$type, $set];
            }
        }
    }

    #[DataProvider('cursePuzzleSets')]
    public function test_curse_seals_persist_and_only_the_last_seal_releases_the_player(string $type, string $set): void
    {
        foreach ([1 => 0, 2 => 2, 3 => 4] as $level => $tokens) {
            if ($type === 'misdirection' && $level < 3) {
                continue;
            }
            [$room, $identities] = $this->match();
            $roles = $this->roles($room);
            $this->expire($room);
            $this->expire($room);
            $id = $roles['oracle'];
            $curse = app(CurseEngine::class)->create($tokens, 6, $room->state['day'], $type, $set);
            $state = $room->fresh()->state;
            $state['players'][$id]['curse'] = $curse;
            $room->update(['state' => $state]);
            $deadline = $room->fresh()->deadline;
            for ($stage = 1; $stage <= 3; $stage++) {
                if ($type !== 'misdirection') {
                    $this->assertRejected(fn () => $this->act($room, $identities[$id], 'discussion_ready'));
                }
                $payload = ['type' => 'solve_curse', 'phase_id' => $room->fresh()->state['phase_id'],
                    'curse_id' => $curse['id'], 'answer' => $curse['solution']];
                $this->withSession(['chanting.identity' => $identities[$roles['townsperson']]])
                    ->postJson('/rooms/'.$room->code.'/actions', $payload)->assertUnprocessable();
                $this->withSession(['chanting.identity' => $identities[$id]])
                    ->postJson('/rooms/'.$room->code.'/actions', [...$payload, 'answer' => [(string) Str::uuid()]])->assertUnprocessable();
                $this->assertEquals($curse, $room->fresh()->state['players'][$id]['curse']);
                $response = $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertOk();
                $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertUnprocessable();
                $view = $this->engine->access($room->code, $identities[$id]);
                $this->assertEquals($deadline, $room->fresh()->deadline);
                if ($stage === 3) {
                    $response->assertJsonPath('me.curse', null);
                    $this->assertNull($view['me']['curse']);
                } else {
                    $response->assertJsonPath('me.curse.stage', $stage + 1)->assertJsonPath('me.curse.stages', 3);
                    $this->assertSame($stage + 1, $view['me']['curse']['stage']);
                    $this->assertSame($level, $view['me']['curse']['level']);
                    $this->assertArrayNotHasKey('solution', $view['me']['curse']);
                    $this->assertSame($curse['set_name'], $view['me']['curse']['set_name']);
                    $next = $room->fresh()->state['players'][$id]['curse'];
                    $this->assertSame($set, $next['puzzle_set']);
                    $this->assertNotSame($curse['id'], $next['id']);
                    $this->assertSame($curse['day'], $next['day']);
                    $curse = $next;
                }
            }
            $this->act($room, $identities[$id], 'discussion_ready');
        }
    }

    public function test_spatial_curses_require_the_current_solution_and_preserve_the_phase_deadline(): void
    {
        foreach (['rings', 'towers', 'lanterns'] as $kind) {
            [$room, $identities] = $this->match();
            $roles = $this->roles($room);
            $this->expire($room);
            $this->expire($room);
            $id = $roles['oracle'];
            $curse = ['id' => (string) Str::uuid(), 'type' => $kind === 'lanterns' ? 'mist' : 'puzzle',
                'level' => 3, 'day' => $room->state['day'], ...app(CurseEngine::class)->challenge($kind, 3)];
            $state = $room->fresh()->state;
            $state['players'][$id]['curse'] = $curse;
            $room->update(['state' => $state]);
            $deadline = $room->fresh()->deadline;
            $view = $this->engine->access($room->code, $identities[$id]);
            $this->assertSame($kind, $view['me']['curse']['challenge']['scene']['kind']);
            $this->assertArrayNotHasKey('solution', $view['me']['curse']);
            $payload = ['curse_id' => $curse['id'], 'answer' => array_reverse($curse['solution'])];
            $this->assertRejected(fn () => $this->act($room, $identities[$id], 'solve_curse', $payload));
            $this->assertNotNull($room->fresh()->state['players'][$id]['curse']);
            $solved = $this->act($room, $identities[$id], 'solve_curse', [...$payload, 'answer' => $curse['solution']]);
            $this->assertNull($solved['me']['curse']);
            $this->assertEquals($deadline, $room->fresh()->deadline);
        }
    }

    public function test_misdirection_can_be_broken_before_it_redirects_a_vote(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $id = $roles['oracle'];
        $curse = app(CurseEngine::class)->create(4, 6, $room->state['day'], 'misdirection', 'crooked-compass');
        $state = $room->fresh()->state;
        $state['players'][$id]['curse'] = $curse;
        $room->update(['state' => $state]);
        $this->assertRejected(fn () => $this->act($room, $identities[$id], 'solve_curse', [
            'curse_id' => $curse['id'], 'answer' => array_column($curse['challenge']['scene']['rings'], 'options'),
        ]));
        do {
            $solved = $this->act($room, $identities[$id], 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
            $curse = $room->fresh()->state['players'][$id]['curse'];
        } while ($curse !== null);
        $this->assertNull($solved['me']['curse']);
        $this->act($room, $identities[$id], 'vote', ['target' => $roles['acolyte']]);
        $this->assertSame($roles['acolyte'], $room->fresh()->state['actions'][$id]['target']);
    }

    public function test_real_misdirection_records_its_caster_without_exposing_them_during_the_match(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $state = $room->fresh()->state;
        $state['tokens'] = 4;
        $state['threshold'] = 6;
        $room->update(['state' => $state]);
        $this->act($room, $identities[$roles['acolyte']], 'night', ['target' => $roles['oracle'], 'curse_type' => 'misdirection']);
        $this->expire($room);
        $curse = $room->fresh()->state['players'][$roles['oracle']]['curse'];
        $this->assertSame($roles['acolyte'], $curse['source_player_id']);
        $public = $this->engine->access($room->code, $identities[$roles['oracle']]);
        $this->assertArrayNotHasKey('source_player_id', $public['me']['curse']);
        $this->assertNull($public['recap']);
        $this->expire($room);
        $this->act($room, $identities[$roles['oracle']], 'vote', ['target' => $roles['acolyte']]);
        $action = $room->fresh()->state['actions'][$roles['oracle']];
        $this->assertSame($roles['acolyte'], $action['misdirection_source_id']);
        $this->assertNotSame($action['chosen_target'], $action['target']);
        $this->expire($room);
        $ballot = collect($room->fresh()->state['rounds'][1]['vote']['ballots'])->firstWhere('player_id', $roles['oracle']);
        $this->assertSame($roles['acolyte'], $ballot['misdirection_source_id']);
        $result = (new SeasonalAchievements)->evaluate($room->fresh()->state, $roles['acolyte'], []);
        $this->assertNotContains('seasonal_cultist', $result['completed']);
    }

    public function test_redirected_vigilante_shot_credits_the_cultist_seasonal_reward(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $vigilante = $roles['oracle'];
        $this->expire($room);
        $state = $room->fresh()->state;
        $state['tokens'] = 67;
        $state['threshold'] = 100;
        $state['players'][$vigilante]['role'] = 'vigilante';
        $room->update(['state' => $state]);
        $this->act($room, $identities[$roles['acolyte']], 'night', ['target' => $vigilante, 'curse_type' => 'misdirection']);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $identities[$vigilante], 'night', ['target' => $roles['acolyte'], 'use_ability' => true]);
        $this->expire($room);
        $state = $room->fresh()->state;
        $action = collect($state['rounds'][2]['night']['actions'])->firstWhere('player_id', $vigilante);
        $this->assertTrue($action['shot_fired']);
        $this->assertSame($roles['acolyte'], $action['misdirection_source_id']);
        $this->assertNotSame($action['chosen_target_id'], $action['target_id']);
        $this->assertFalse($state['players'][$action['target_id']]['alive']);
        $result = (new SeasonalAchievements)->evaluate($state, $roles['acolyte'], []);
        $this->assertContains('seasonal_cultist', $result['completed']);
    }

    public function test_warden_does_not_get_save_credit_when_sanctuary_already_blocks_the_curse(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $state = $room->fresh()->state;
        $state['chaos_event'] = 'sanctuary';
        $room->update(['state' => $state]);
        $this->act($room, $identities[$roles['warden']], 'night', ['target' => $roles['oracle']]);
        $this->act($room, $identities[$roles['acolyte']], 'night', ['target' => $roles['oracle']]);
        $this->expire($room);
        $action = collect($room->fresh()->state['rounds'][1]['night']['actions'])->firstWhere('player_id', $roles['warden']);
        $this->assertFalse($action['prevented_curse']);
        $this->assertNull($room->fresh()->state['players'][$roles['oracle']]['curse']);
    }

    public function test_legacy_misdirection_cannot_be_cleared_with_an_empty_answer(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $curse = $this->afflict($room, $roles['oracle'], 'misdirection');
        $this->assertRejected(fn () => $this->act($room, $identities[$roles['oracle']], 'solve_curse', [
            'curse_id' => $curse['id'], 'answer' => [],
        ]));
    }

    public function test_misdirection_changes_the_authoritative_vote_once_but_never_abstention(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->afflict($room, $roles['oracle'], 'misdirection');
        $this->afflict($room, $roles['townsperson'], 'misdirection');
        $abstain = $this->act($room, $identities[$roles['townsperson']], 'vote');
        $this->assertSame('misdirection', $abstain['me']['curse']['type']);
        $view = $this->act($room, $identities[$roles['oracle']], 'vote', ['target' => $roles['acolyte']]);
        $actual = $room->fresh()->state['actions'][$roles['oracle']]['target'];
        $this->assertNotContains($actual, [$roles['oracle'], $roles['acolyte']]);
        $this->assertTrue($room->fresh()->state['players'][$actual]['alive']);
        $this->assertNull($view['me']['curse']);
        $this->assertStringContainsString($room->fresh()->state['players'][$actual]['name'], $view['me']['curse_notice']);
        $this->assertRejected(fn () => $this->act($room, $identities[$roles['oracle']], 'vote', ['target' => $roles['acolyte']]));
        $this->expire($room);
        $ballots = array_column($room->state['rounds'][1]['vote']['ballots'], 'target_id', 'player_id');
        $this->assertSame($actual, $ballots[$roles['oracle']]);
        $choices = array_column($room->state['rounds'][1]['vote']['ballots'], 'chosen_target_id', 'player_id');
        $this->assertSame($roles['acolyte'], $choices[$roles['oracle']]);
    }

    public function test_misdirection_changes_oracle_reading_and_excludes_dead_players(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $state = $room->fresh()->state;
        $state['players'][$roles['townsperson']]['alive'] = false;
        $room->update(['state' => $state]);
        $this->afflict($room, $roles['oracle'], 'misdirection');
        $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['acolyte']]);
        $actual = $room->fresh()->state['actions'][$roles['oracle']]['target'];
        $this->assertNotContains($actual, [$roles['oracle'], $roles['acolyte'], $roles['townsperson']]);
        $this->expire($room);
        $result = $room->state['players'][$roles['oracle']]['results'][0];
        $this->assertSame($room->state['players'][$actual]['name'], $result['target']);
        $this->assertSame($room->state['players'][$actual]['alignment'], $result['alignment']);
        $actions = array_column($room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertSame($roles['acolyte'], $actions[$roles['oracle']]['chosen_target_id']);
        $this->assertSame($actual, $actions[$roles['oracle']]['target_id']);
    }

    public function test_curses_survive_until_next_dawn_and_clear_on_banishment_victory_and_rematch(): void
    {
        [$room, $identities] = $this->match();
        $roles = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->afflict($room, $roles['oracle'], 'puzzle');
        $this->expire($room);
        $this->assertNotNull($room->state['players'][$roles['oracle']]['curse']);
        $this->expire($room);
        $this->assertNotNull($room->state['players'][$roles['oracle']]['curse']);
        $this->assertRejected(fn () => $this->act($room, $identities[$roles['oracle']], 'night', ['target' => $roles['acolyte']]));
        $this->expire($room);
        $this->assertNull($room->state['players'][$roles['oracle']]['curse']);
        $this->afflict($room, $roles['townsperson'], 'mist');
        $this->expire($room);
        $this->banish($room, $identities, $roles['townsperson']);
        $this->assertNull($room->fresh()->state['players'][$roles['townsperson']]['curse']);
        $this->assertRejected(fn () => $this->act($room, $identities[$roles['townsperson']], 'solve_curse'));
        $this->afflict($room, $roles['oracle'], 'mist');
        $state = $room->fresh()->state;
        $state['tokens'] = $state['threshold'];
        $room->update(['state' => $state]);
        $this->expire($room);
        $this->assertSame('discussion', $room->state['phase']);
        $this->expire($room);
        $this->expire($room);
        $this->assertSame('finished', $room->state['phase']);
        foreach ($room->state['players'] as $player) {
            $this->assertNull($player['curse']);
        }
        $this->act($room, 'secret-0', 'rematch');
        $this->assertNull($this->engine->access($room->code, $identities[$roles['oracle']])['me']['curse']);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected invalid action to be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }
}
