<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\GameModes;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameModesTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    private GameModes $modes;

    /** @var array<string, string> */
    private array $identities = [];

    /** @var array<string, list<string>> */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
        $this->engine = app(MatchEngine::class);
        $this->modes = app(GameModes::class);
    }

    /** @param array<string, mixed> $setup */
    private function gathering(array $setup, int $count = 5, bool $start = true): GameRoom
    {
        $room = $this->engine->create('guest-0', 'Player 0', setup: $setup);
        for ($i = 1; $i < $count; $i++) {
            $this->engine->join($room->code, 'guest-'.$i, 'Player '.$i);
        }
        foreach ($room->fresh()->state['players'] as $id => $player) {
            $this->identities[$id] = 'guest-'.substr($player['name'], -1);
            $this->act($room, $id, 'ready');
        }
        if ($start) {
            $this->act($room, $room->fresh()->state['host_id'], 'start');
            foreach ($room->fresh()->state['players'] as $id => $player) {
                $this->roles[$player['role']][] = $id;
            }
            $s = $room->fresh()->state;
            $s['mission'] = config('game.missions.patience');
            $room->update(['state' => $s]);
            $this->expire($room);
        }

        return $room->refresh();
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, string $id, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, $this->identities[$id], ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function expire(GameRoom $room): void
    {
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->engine->resolve($room->id);
        $room->refresh();
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Invalid setup or action was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_hard_rosters_expand_by_room_size_and_classic_preserves_its_lineup(): void
    {
        foreach (range(5, 10) as $count) {
            $hard = $this->modes->roster($this->modes->normalize(['mode' => 'hard']), $count);
            $classic = $this->modes->roster($this->modes->normalize(), $count);
            $this->assertCount($count, $hard);
            $this->assertContains('tracker', $hard);
            $this->assertSame($count >= 8, in_array('herbalist', $hard, true));
            $this->assertContains('warden', $classic);
            $this->assertNotContains('tracker', $classic);
            $this->assertSame(config('game.cultists_by_player_count.'.$count), count(array_filter($hard, fn (string $role): bool => config('game.role_alignments')[$role] === 'cult')));
        }
    }

    public function test_http_creation_persists_selected_modes_and_custom_counts(): void
    {
        foreach ([['mode' => 'hard'], ['mode' => 'paranoia'], ['mode' => 'chaos', 'chaos_variant' => 'maelstrom'], ['mode' => 'custom', 'roles' => ['oracle' => 2, 'acolyte' => 1]]] as $setup) {
            $code = $this->postJson('/rooms', ['name' => 'Host', 'setup' => $setup])->assertCreated()->json('code');
            $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('mode_setup.mode', $setup['mode']);
            $this->assertSame($this->modes->normalize($setup), GameRoom::where('code', $code)->firstOrFail()->state['mode_setup']);
        }
    }

    public function test_custom_exact_counts_allow_duplicates_and_limit_join_capacity(): void
    {
        $configured = ['oracle' => 2, 'tracker' => 1, 'acolyte' => 2];
        $room = $this->gathering(['mode' => 'custom', 'roles' => $configured]);
        $this->assertCount(2, $this->roles['oracle']);
        $this->assertCount(2, $this->roles['acolyte']);
        $this->assertArrayNotHasKey('townsperson', $this->roles);
        $actual = array_count_values(array_column($room->state['players'], 'role'));
        $this->assertEquals($configured, $actual);
        $view = $this->engine->access($room->code, 'guest-0');
        $this->assertEquals($configured, $view['mode_preview']['roles']);
        foreach ($view['players'] as $player) {
            $this->assertArrayNotHasKey('role', $player);
        }
        $lobby = $this->gathering(['mode' => 'custom', 'roles' => ['oracle' => 2, 'acolyte' => 1]], 3, false);
        $this->reject(fn () => $this->engine->join($lobby->code, 'extra', 'Extra'));
    }

    public function test_http_mode_edit_applies_atomically_and_rejects_invalid_and_stale_settings(): void
    {
        $room = $this->gathering(['mode' => 'classic'], 3, false);
        $this->withSession(['chanting.identity' => 'guest-0']);
        $payload = ['type' => 'configure_mode', 'phase_id' => 1, 'setup' => ['mode' => 'custom', 'roles' => ['oracle' => 2, 'acolyte' => 1]]];
        $view = $this->postJson('/rooms/'.$room->code.'/actions', $payload)->assertOk()->assertJsonPath('mode_setup.mode', 'custom')->json();
        $this->assertEmpty(array_filter(array_column($view['players'], 'ready')));
        $saved = $room->fresh()->state;
        $this->postJson('/rooms/'.$room->code.'/actions', [...$payload, 'setup' => ['mode' => 'custom', 'roles' => ['unknown' => 3]]])->assertUnprocessable();
        $this->assertSame($saved, $room->fresh()->state);
        $this->postJson('/rooms/'.$room->code.'/actions', [...$payload, 'phase_id' => 999])->assertUnprocessable();
        $this->assertSame($saved, $room->fresh()->state);
    }

    public function test_custom_mismatch_blocks_start_and_mode_changes_reset_ready_only_for_host(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['oracle' => 3, 'acolyte' => 1]], 3, false);
        $host = $room->state['host_id'];
        $this->reject(fn () => $this->act($room, $host, 'start'));
        $this->assertStringContainsString('exactly 4', $this->engine->access($room->code, 'guest-0')['mode_preview']['error']);
        $other = array_keys($room->state['players'])[1];
        $this->reject(fn () => $this->act($room, $other, 'configure_mode', ['setup' => ['mode' => 'classic']]));
        $view = $this->act($room, $host, 'configure_mode', ['setup' => ['mode' => 'classic']]);
        $this->assertEmpty(array_filter(array_column($view['players'], 'ready')));
        $this->assertNull($view['mode_preview']['error']);
        $this->reject(fn () => $this->act($room, $host, 'start'));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidSetups(): array
    {
        return [
            'unknown mode' => [['mode' => 'made-up']],
            'unknown variant' => [['mode' => 'chaos', 'chaos_variant' => 'made-up']],
            'no town' => [['mode' => 'custom', 'roles' => ['acolyte' => 3]]],
            'no cult' => [['mode' => 'custom', 'roles' => ['oracle' => 3]]],
            'too many' => [['mode' => 'custom', 'roles' => ['oracle' => 10, 'acolyte' => 1]]],
            'too few' => [['mode' => 'custom', 'roles' => ['oracle' => 1, 'acolyte' => 1]]],
            'unknown role' => [['mode' => 'custom', 'roles' => ['oracle' => 2, 'fake' => 1]]],
            'fraction' => [['mode' => 'custom', 'roles' => ['oracle' => 2.5, 'acolyte' => 1]]],
            'negative' => [['mode' => 'custom', 'roles' => ['oracle' => 3, 'acolyte' => -1]]],
            'boolean' => [['mode' => 'custom', 'roles' => ['oracle' => true, 'acolyte' => 2]]],
        ];
    }

    #[DataProvider('invalidSetups')]
    public function test_invalid_setups_cannot_create_rooms(array $setup): void
    {
        $this->postJson('/rooms', ['name' => 'Host', 'setup' => $setup])->assertUnprocessable();
        $this->assertDatabaseCount('game_rooms', 0);
    }

    public function test_chaos_allows_repeated_roles_but_preserves_faction_counts_and_never_rerolls_on_reads(): void
    {
        config(['game.role_alignments' => ['oracle' => 'town', 'acolyte' => 'cult']]);
        $room = $this->gathering(['mode' => 'chaos'], 5, false);
        $before = $room->fresh()->state;
        $this->assertNull($this->engine->access($room->code, 'guest-0')['mode_preview']['roles']);
        $this->assertSame($before, $room->fresh()->state);
        $this->act($room, $before['host_id'], 'start');
        $this->assertEquals(['oracle' => 3, 'acolyte' => 2], array_count_values(array_column($room->fresh()->state['players'], 'role')));
        $assigned = $room->fresh()->state['players'];
        $this->engine->access($room->code, 'guest-0');
        $this->assertSame($assigned, $room->fresh()->state['players']);
    }

    public function test_paranoia_preserves_team_sizes_allows_duplicates_and_requires_five_players(): void
    {
        // A restricted pool demonstrates duplicates and the absence of a guaranteed Oracle.
        config(['game.role_alignments' => ['oathkeeper' => 'town', 'acolyte' => 'cult']]);
        $setup = $this->modes->normalize(['mode' => 'paranoia']);
        foreach (range(5, 10) as $count) {
            $cult = config('game.cultists_by_player_count.'.$count);
            $this->assertEquals(['oathkeeper' => $count - $cult, 'acolyte' => $cult], array_count_values($this->modes->roster($setup, $count)));
            $preview = $this->modes->preview($setup, $count);
            $this->assertNull($preview['roles']);
            $this->assertSame(['town' => $count - $cult, 'cult' => $cult], $preview['team_counts']);
            $this->assertSame(['town' => ['oathkeeper'], 'cult' => ['acolyte']], $preview['possible_roles']);
        }
        $room = $this->gathering($setup, 4, false);
        $this->assertSame(5, $this->engine->access($room->code, 'guest-0')['rules']['min_players']);
        $this->assertNotNull($this->engine->access($room->code, 'guest-0')['mode_preview']['error']);
        $this->reject(fn () => $this->act($room, $room->state['host_id'], 'start'));
    }

    public function test_paranoia_hides_the_dealt_cast_until_victory_and_resets_for_rematches(): void
    {
        $room = $this->gathering(['mode' => 'paranoia'], 5, false);
        $host = $room->state['host_id'];
        $this->act($room, $host, 'start');
        $s = $room->fresh()->state;
        $cast = array_count_values(array_column($s['players'], 'role'));
        $deadTown = array_key_first(array_filter($s['players'], fn (array $p): bool => $p['alignment'] === 'town'));
        $s['players'][$deadTown]['alive'] = false;
        $room->update(['state' => $s]);
        foreach (['reveal', 'night', 'discussion', 'voting'] as $phase) {
            $this->assertSame($phase, $room->fresh()->state['phase']);
            foreach ($this->identities as $identity) {
                $view = $this->engine->access($room->code, $identity);
                $this->assertNull($view['mode_preview']['roles']);
                $this->assertNull($view['recap']);
                $this->assertEmpty($view['mode_setup']['roles']);
                foreach ($view['players'] as $player) {
                    $this->assertArrayNotHasKey('role', $player);
                    $this->assertArrayNotHasKey('alignment', $player);
                }
            }
            if ($phase === 'night') {
                $s = $room->fresh()->state;
                $s['tokens'] = $s['threshold'];
                $room->update(['state' => $s]);
            }
            $this->expire($room);
        }
        $finished = $this->engine->access($room->code, 'guest-0');
        $this->assertSame('finished', $finished['phase']);
        $this->assertEquals($cast, $finished['mode_preview']['roles']);
        $this->assertSame('paranoia', $finished['recap']['mode_setup']['mode']);
        $rematch = $this->act($room, $host, 'rematch');
        $this->assertSame('paranoia', $rematch['mode_setup']['mode']);
        $this->assertNull($rematch['mode_preview']['roles']);
        $this->assertArrayNotHasKey('match_rules', $room->fresh()->state);
    }

    /** @return array<string, array{int, int}> */
    public static function paranoiaProgress(): array
    {
        return ['empty' => [0, 90], 'early' => [1, 90], 'one third' => [2, 70],
            'middle' => [3, 70], 'two thirds' => [4, 50], 'late' => [5, 50], 'final vote' => [6, 50]];
    }

    #[DataProvider('paranoiaProgress')]
    public function test_paranoia_discussion_deadlines_use_snapshotted_progress_tiers(int $tokens, int $seconds): void
    {
        $room = $this->gathering(['mode' => 'paranoia']);
        $s = $room->state;
        $s['tokens'] = $tokens;
        $s['threshold'] = 6;
        $room->update(['state' => $s]);
        config(['game.paranoia_discussion_seconds' => ['early' => 5, 'middle' => 4, 'late' => 3], 'game.seconds.voting' => 10]);
        $this->expire($room);
        $view = $this->engine->access($room->code, 'guest-0');
        $this->assertSame('discussion', $view['phase']);
        $this->assertEquals($seconds, now()->diffInSeconds($room->deadline));
        $this->assertSame($seconds, $view['rules']['seconds']['discussion']);
        $this->assertSame(90, $view['mode_preview']['discussion_seconds']['early']);
        $this->assertStringContainsString('Paranoia: '.$seconds.' seconds', implode(' ', $view['log']));
        $this->expire($room);
        $this->assertSame('voting', $room->state['phase']);
        $this->assertEquals(45, now()->diffInSeconds($room->deadline));
    }

    public function test_paranoia_public_oaths_do_not_prove_roles_or_grant_other_roles_protection(): void
    {
        $room = $this->gathering(['mode' => 'paranoia']);
        $s = $room->state;
        $ids = array_keys($s['players']);
        foreach (['oathkeeper', 'townsperson', 'exorcist', 'acolyte', 'veilweaver'] as $index => $role) {
            $s['players'][$ids[$index]]['role'] = $role;
            $s['players'][$ids[$index]]['alignment'] = config('game.role_alignments')[$role];
        }
        $room->update(['state' => $s]);
        $this->reject(fn () => $this->act($room, $ids[3], 'oath', ['target' => $ids[4]]));
        $this->expire($room);
        $this->reject(fn () => $this->act($room, $ids[3], 'oath', ['target' => $ids[3]]));
        $this->reject(fn () => $this->act($room, $ids[3], 'oath'));
        foreach (array_slice($ids, 0, 4) as $id) {
            $view = $this->act($room, $id, 'oath', ['target' => $ids[4]]);
            $this->assertFalse($view['me']['submitted']);
            $public = array_column($view['players'], null, 'id')[$id];
            $this->assertSame(['day' => 1, 'target_id' => $ids[4]], $public['oath']);
            $this->assertArrayNotHasKey('role', $public);
            $this->reject(fn () => $this->act($room, $id, 'oath', ['target' => $ids[0]]));
        }
        $this->assertTrue($this->act($room, $ids[2], 'exorcise', ['target' => $ids[1]])['me']['ability_used']);
        $this->expire($room);
        foreach (array_slice($ids, 0, 4) as $id) {
            $this->act($room, $id, 'vote', ['target' => $ids[4]]);
        }
        $this->act($room, $ids[4], 'vote');
        foreach (array_slice($ids, 0, 4) as $index => $id) {
            $view = $this->engine->access($room->code, $this->identities[$id]);
            $this->assertSame($index === 0, $view['me']['oath_protected']);
        }
        $this->act($room, $ids[3], 'night', ['target' => $ids[1]]);
        $this->expire($room);
        $this->assertNotNull($room->state['players'][$ids[1]]['curse']);
        $this->assertNotEmpty($this->act($room, $ids[3], 'oath', ['target' => $ids[0]]));
        $this->reject(fn () => $this->act($room, $ids[4], 'oath', ['target' => $ids[0]]));
    }

    public function test_classic_keeps_its_discussion_time_and_oath_restrictions(): void
    {
        $room = $this->gathering(['mode' => 'classic']);
        $s = $room->state;
        $s['tokens'] = $s['threshold'] - 1;
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->assertEquals(90, now()->diffInSeconds($room->deadline));
        $ids = array_keys($room->state['players']);
        $this->reject(fn () => $this->act($room, $ids[0], 'oath', ['target' => $ids[1]]));
    }

    public function test_tracker_sees_submitted_visit_even_if_disrupted_but_not_private_ability(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['tracker' => 1, 'oracle' => 1, 'townsperson' => 1, 'dreamweaver' => 1, 'acolyte' => 1]]);
        $oracle = $this->roles['oracle'][0];
        $target = $this->roles['townsperson'][0];
        $this->act($room, $oracle, 'night', ['target' => $target]);
        $this->act($room, $this->roles['tracker'][0], 'night', ['target' => $oracle]);
        $this->act($room, $this->roles['dreamweaver'][0], 'night', ['target' => $oracle, 'use_ability' => true]);
        $this->expire($room);
        $result = $room->state['players'][$this->roles['tracker'][0]]['results'][0];
        $this->assertSame('tracking', $result['kind']);
        $this->assertSame($room->state['players'][$target]['name'], $result['visited_target']);
        $this->assertArrayNotHasKey('role', $result);
        $this->assertSame('disrupted', $room->state['players'][$oracle]['results'][0]['kind']);
    }

    public function test_phantasm_hides_a_visit_from_tracker_and_a_missed_target_leaves_no_trace(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['tracker' => 2, 'oracle' => 1, 'townsperson' => 1, 'phantasm' => 1]]);
        $oracle = $this->roles['oracle'][0];
        $this->act($room, $oracle, 'night', ['target' => $this->roles['townsperson'][0]]);
        $this->act($room, $this->roles['tracker'][0], 'night', ['target' => $oracle]);
        $this->act($room, $this->roles['tracker'][1], 'night', ['target' => $this->roles['townsperson'][0]]);
        $this->act($room, $this->roles['phantasm'][0], 'night', ['target' => $oracle, 'use_ability' => true]);
        $this->expire($room);
        foreach ($this->roles['tracker'] as $tracker) {
            $this->assertNull($room->state['players'][$tracker]['results'][0]['visited_target']);
        }
    }

    public function test_herbalist_protects_all_new_curses_without_stopping_veils_or_chants(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['herbalist' => 1, 'oracle' => 1, 'townsperson' => 1, 'veilweaver' => 1, 'acolyte' => 1]]);
        $town = $this->roles['townsperson'][0];
        $herbalist = $this->roles['herbalist'][0];
        $this->reject(fn () => $this->act($room, $herbalist, 'night', ['target' => $town, 'use_ability' => true]));
        $this->act($room, $herbalist, 'night', ['use_ability' => true]);
        $this->act($room, $this->roles['veilweaver'][0], 'night', ['target' => $town]);
        $this->act($room, $this->roles['acolyte'][0], 'night', ['target' => $herbalist]);
        $this->act($room, $this->roles['oracle'][0], 'night', ['target' => $town]);
        $this->expire($room);
        foreach ($room->state['players'] as $player) {
            $this->assertNull($player['curse']);
        }
        $this->assertSame('cult', $room->state['players'][$this->roles['oracle'][0]]['results'][0]['alignment']);
        $this->assertSame(2, $room->state['tokens']);
        $this->assertTrue($room->state['players'][$herbalist]['ability_used']);
        $this->expire($room);
        $this->expire($room);
        $this->reject(fn () => $this->act($room, $herbalist, 'night', ['use_ability' => true]));
    }

    public function test_disrupted_herbalist_spends_ability_without_protecting(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['herbalist' => 1, 'oracle' => 1, 'townsperson' => 1, 'dreamweaver' => 1, 'acolyte' => 1]]);
        $herbalist = $this->roles['herbalist'][0];
        $this->act($room, $herbalist, 'night', ['use_ability' => true]);
        $this->act($room, $this->roles['dreamweaver'][0], 'night', ['target' => $herbalist, 'use_ability' => true]);
        $this->act($room, $this->roles['acolyte'][0], 'night', ['target' => $this->roles['townsperson'][0]]);
        $this->expire($room);
        $this->assertTrue($room->state['players'][$herbalist]['ability_used']);
        $this->assertSame('disrupted', $room->state['players'][$herbalist]['results'][0]['kind']);
        $this->assertNotNull($room->state['players'][$this->roles['townsperson'][0]]['curse']);
    }

    public function test_duplicate_oracles_keep_independent_readings_and_bells_each_prevent_one_step(): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['oracle' => 2, 'bellkeeper' => 2, 'acolyte' => 2]], 6);
        foreach ($this->roles['oracle'] as $index => $oracle) {
            $this->act($room, $oracle, 'night', ['target' => $this->roles['acolyte'][$index]]);
        }
        foreach ($this->roles['acolyte'] as $cult) {
            $this->act($room, $cult, 'night');
        }
        foreach ($this->roles['bellkeeper'] as $bell) {
            $this->act($room, $bell, 'night', ['use_ability' => true]);
        }
        $s = $room->fresh()->state;
        $this->assertSame('discussion', $s['phase']);
        $this->assertSame(0, $s['tokens']);
        foreach ($this->roles['bellkeeper'] as $bell) {
            $this->assertTrue($s['players'][$bell]['ability_used']);
            $this->assertSame(1, $s['players'][$bell]['results'][0]['prevented']);
        }
        foreach ($this->roles['oracle'] as $index => $oracle) {
            $view = $this->engine->access($room->code, $this->identities[$oracle]);
            $this->assertCount(1, $view['me']['results']);
            $this->assertSame($s['players'][$this->roles['acolyte'][$index]]['name'], $view['me']['results'][0]['target']);
        }
    }

    /** @return array<string, array{string}> */
    public static function duplicateModes(): array
    {
        return ['custom' => ['custom'], 'paranoia' => ['paranoia']];
    }

    #[DataProvider('duplicateModes')]
    public function test_duplicate_forgeries_resolve_in_seat_order_regardless_of_submission_order(string $mode): void
    {
        $room = $this->gathering(['mode' => 'custom', 'roles' => ['counterfeiter' => 2, 'oracle' => 1, 'tracker' => 1, 'townsperson' => 1]]);
        if ($mode === 'paranoia') {
            $s = $room->state;
            $s['mode_setup'] = $this->modes->normalize(['mode' => $mode]);
            $room->update(['state' => $s]);
        }
        $casters = $this->roles['counterfeiter']; // collected in public seat order
        $target = $this->roles['townsperson'][0];
        $this->act($room, $casters[1], 'night', ['target' => $target, 'use_ability' => true, 'forged_alignment' => 'cult']);
        $this->act($room, $casters[0], 'night', ['target' => $target, 'use_ability' => true, 'forged_alignment' => 'town']);
        $this->act($room, $this->roles['oracle'][0], 'night', ['target' => $target]);
        $this->expire($room);
        $this->assertSame('cult', $room->state['players'][$this->roles['oracle'][0]]['results'][0]['alignment']);
        foreach ($casters as $caster) {
            $this->assertTrue($room->state['players'][$caster]['ability_used']);
        }
    }

    /** @return array<string, array{string}> */
    public static function events(): array
    {
        return ['mirrors' => ['mirrors'], 'sanctuary' => ['sanctuary'], 'eclipse' => ['eclipse']];
    }

    #[DataProvider('events')]
    public function test_maelstrom_events_resolve_and_are_public_and_archived(string $event): void
    {
        $room = $this->gathering(['mode' => 'chaos', 'chaos_variant' => 'maelstrom']);
        $s = $room->fresh()->state;
        $this->assertContains($s['chaos_event'], ['mirrors', 'sanctuary', 'eclipse']);
        $ids = array_keys($s['players']);
        foreach (['oracle', 'tracker', 'lamplighter', 'acolyte', 'townsperson'] as $index => $role) {
            $s['players'][$ids[$index]]['role'] = $role;
            $s['players'][$ids[$index]]['alignment'] = config('game.role_alignments')[$role];
        }
        $s['chaos_event'] = $event;
        $room->update(['state' => $s]);
        $this->assertSame($event, $this->engine->access($room->code, 'guest-0')['chaos_event']);
        $this->reject(fn () => $this->act($room, $s['host_id'], 'configure_mode', ['setup' => ['mode' => 'classic']]));
        $this->act($room, $ids[0], 'night', ['target' => $ids[4]]);
        $this->act($room, $ids[1], 'night', ['target' => $ids[0]]);
        $this->act($room, $ids[2], 'night', ['target' => $ids[4]]);
        $this->act($room, $ids[3], 'night', ['target' => $ids[4]]);
        $this->expire($room);
        $this->assertSame($event === 'mirrors' ? 'cult' : 'town', $room->state['players'][$ids[0]]['results'][0]['alignment']);
        $this->assertSame($event !== 'eclipse', $room->state['players'][$ids[2]]['results'][0]['visited']);
        $this->assertSame($event === 'eclipse' ? null : $s['players'][$ids[4]]['name'], $room->state['players'][$ids[1]]['results'][0]['visited_target']);
        $this->assertSame($event === 'sanctuary', $room->state['players'][$ids[4]]['curse'] === null);
        $this->assertSame($event, $room->state['rounds'][1]['night']['chaos_event']);
        $s = $room->fresh()->state;
        $s['tokens'] = $s['threshold'];
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->expire($room);
        $finished = $this->engine->access($room->code, 'guest-0');
        $this->assertSame('chaos', $finished['recap']['mode_setup']['mode']);
        $this->assertSame($event, $finished['recap']['rounds'][0]['night']['chaos_event']);
        $rematch = $this->act($room, $s['host_id'], 'rematch');
        $this->assertSame('maelstrom', $rematch['mode_setup']['chaos_variant']);
        $this->assertNull($rematch['chaos_event']);
    }
}
