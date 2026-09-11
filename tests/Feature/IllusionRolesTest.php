<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\CurseEngine;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IllusionRolesTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    private GameRoom $room;

    /** @var array<string, string> */
    private array $ids;

    /** @var array<string, string> */
    private array $identities;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
        $this->engine = app(MatchEngine::class);
        $this->room = $this->engine->create('guest-0', 'Player 0');
        for ($i = 1; $i < 8; $i++) {
            $this->engine->join($this->room->code, 'guest-'.$i, 'Player '.$i);
        }
        $this->engine->access($this->room->code, 'guest-0', ['type' => 'roster', 'phase_id' => 1, 'roster' => 'illusions']);
        foreach ($this->room->fresh()->state['players'] as $id => $player) {
            $identity = 'guest-'.substr($player['name'], -1);
            $this->identities[$id] = $identity;
            $this->engine->access($this->room->code, $identity, ['type' => 'ready', 'phase_id' => 1]);
        }
        $this->engine->access($this->room->code, 'guest-0', ['type' => 'start', 'phase_id' => 1]);
        $s = $this->room->fresh()->state;
        foreach ($s['players'] as $id => $player) {
            $this->ids[$player['role']] = $id;
        }
        $s['mission'] = config('game.missions.patience');
        $this->room->update(['state' => $s]);
        $this->expire();
    }

    private function expire(): void
    {
        $this->travelTo($this->room->fresh()->deadline->addSecond());
        $this->engine->resolve($this->room->id);
        $this->room->refresh();
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(string $role, string $type, array $extra = []): array
    {
        return $this->engine->access($this->room->code, $this->identities[$this->ids[$role]], [
            'type' => $type, 'phase_id' => $this->room->fresh()->state['phase_id'], ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function stateFor(string $role): array
    {
        return $this->engine->access($this->room->code, $this->identities[$this->ids[$role]]);
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the action to be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_illusion_roster_assigns_all_four_and_cannot_change_mid_match(): void
    {
        foreach (['phantasm', 'counterfeiter', 'exorcist', 'oathkeeper', 'oracle', 'lamplighter', 'dreamweaver', 'townsperson'] as $role) {
            $this->assertArrayHasKey($role, $this->ids);
        }
        $this->assertSame('illusions', $this->stateFor('oracle')['roster']);
        $this->reject(fn () => $this->act('oracle', 'roster', ['roster' => 'classic']));
    }

    public function test_new_abilities_pass_http_validation_and_return_personalized_state(): void
    {
        foreach (['counterfeiter', 'phantasm'] as $role) {
            $this->withSession(['chanting.identity' => $this->identities[$this->ids[$role]]])
                ->postJson('/rooms/'.$this->room->code.'/actions', [
                    'type' => 'night', 'phase_id' => $this->room->fresh()->state['phase_id'],
                    'target' => $this->ids['oracle'], 'use_ability' => true,
                    ...($role === 'counterfeiter' ? ['forged_alignment' => 'cult'] : []),
                ])->assertOk()->assertJsonPath('me.ability_used', true)->assertJsonPath('me.submitted', true);
        }
        $this->expire();
        foreach (['exorcist' => 'exorcise', 'oathkeeper' => 'oath'] as $role => $type) {
            $this->withSession(['chanting.identity' => $this->identities[$this->ids[$role]]])
                ->postJson('/rooms/'.$this->room->code.'/actions', [
                    'type' => $type, 'phase_id' => $this->room->fresh()->state['phase_id'], 'target' => $this->ids['oracle'],
                ])->assertOk()->assertJsonPath('me.role', $role)->assertJsonPath('me.submitted', false);
        }
        $this->assertNull($this->stateFor('oracle')['me']['haunting']);
    }

    public function test_roster_selection_requires_host_and_resets_readiness_and_small_rooms_cannot_start_it(): void
    {
        $room = $this->engine->create('host', 'Host');
        foreach (['one', 'two'] as $guest) {
            $this->engine->join($room->code, $guest, $guest);
        }
        $act = fn (string $identity, string $type, array $extra = []) => $this->engine->access($room->code, $identity, ['type' => $type, 'phase_id' => 1, ...$extra]);
        $this->reject(fn () => $act('one', 'roster', ['roster' => 'illusions']));
        $act('one', 'ready');
        $view = $act('host', 'roster', ['roster' => 'illusions']);
        $this->assertEmpty(array_filter(array_column($view['players'], 'ready')));
        foreach (['host', 'one', 'two'] as $guest) {
            $act($guest, 'ready');
        }
        $this->reject(fn () => $act('host', 'start'));
        $this->assertSame('lobby', $room->fresh()->state['phase']);
    }

    public function test_missing_and_abstaining_ballots_break_an_oath(): void
    {
        foreach ([false, true] as $submit) {
            $this->expire();
            $this->act('oathkeeper', 'oath', ['target' => $this->ids['phantasm']]);
            $this->expire();
            if ($submit) {
                $this->act('oathkeeper', 'vote');
            }
            $this->expire();
            $view = $this->stateFor('oathkeeper');
            $this->assertFalse($view['me']['oath_protected']);
            $this->assertFalse(end($view['me']['results'])['kept']);
        }
    }

    public function test_forgery_can_frame_town_as_cult(): void
    {
        $this->act('counterfeiter', 'night', ['target' => $this->ids['exorcist'], 'use_ability' => true, 'forged_alignment' => 'cult']);
        $this->act('oracle', 'night', ['target' => $this->ids['exorcist']]);
        $this->expire();
        $this->assertSame('cult', $this->stateFor('oracle')['me']['results'][0]['alignment']);
        $this->assertNull($this->stateFor('exorcist')['me']['curse']);
    }

    public function test_forgery_fades_before_a_saved_oracle_investigation_the_next_night(): void
    {
        $this->act('counterfeiter', 'night', ['target' => $this->ids['exorcist'], 'use_ability' => true, 'forged_alignment' => 'cult']);
        $this->act('oracle', 'night');
        $this->expire();
        $this->assertFalse($this->stateFor('oracle')['me']['ability_used']);
        $this->assertSame([], $this->stateFor('oracle')['me']['results']);
        $this->expire();
        $this->expire();
        $this->act('oracle', 'night', ['target' => $this->ids['exorcist']]);
        $this->expire();
        $this->assertSame('town', $this->stateFor('oracle')['me']['results'][0]['alignment']);
    }

    public function test_stale_and_missed_night_abilities_remain_available(): void
    {
        $phaseId = $this->room->fresh()->state['phase_id'];
        $this->expire();
        $this->reject(fn () => $this->engine->access($this->room->code, $this->identities[$this->ids['phantasm']], [
            'type' => 'night', 'phase_id' => $phaseId, 'use_ability' => true, 'target' => $this->ids['oracle'],
        ]));
        $this->assertFalse($this->stateFor('phantasm')['me']['ability_used']);
        $this->assertFalse($this->stateFor('counterfeiter')['me']['ability_used']);
    }

    public function test_phantasm_hides_outgoing_visits_and_haunts_only_its_victim_until_voting(): void
    {
        $this->act('oracle', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('lamplighter', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('phantasm', 'night', ['target' => $this->ids['oracle'], 'use_ability' => true]);
        $this->act('counterfeiter', 'night');
        $this->act('dreamweaver', 'night');
        $this->expire();
        $victim = $this->stateFor('oracle');
        $this->assertFalse($this->stateFor('lamplighter')['me']['results'][0]['visited']);
        $this->assertSame('oracle', $victim['me']['role']);
        $this->assertSame('town', $victim['me']['results'][0]['alignment']);
        $this->assertNotNull($victim['me']['haunting']);
        $this->assertNull($victim['me']['curse']);
        $this->assertSame(2, $victim['ritual']['tokens']);
        foreach (array_keys($this->ids) as $role) {
            $view = $this->stateFor($role);
            if ($role !== 'oracle') {
                $this->assertNull($view['me']['haunting']);
            }
            foreach ($view['players'] as $player) {
                $this->assertArrayNotHasKey('haunting', $player);
                $this->assertArrayNotHasKey('role', $player);
            }
            $this->assertNull($view['recap']);
        }
        $this->expire();
        $this->assertNull($this->stateFor('oracle')['me']['haunting']);
    }

    public function test_other_visible_visits_still_count_when_one_visitor_is_hidden(): void
    {
        $this->act('oracle', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('lamplighter', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('phantasm', 'night', ['target' => $this->ids['oracle'], 'use_ability' => true]);
        $this->act('counterfeiter', 'night', ['target' => $this->ids['exorcist'], 'use_ability' => true, 'forged_alignment' => 'cult']);
        $this->expire();
        $this->assertTrue($this->stateFor('lamplighter')['me']['results'][0]['visited']);
    }

    public function test_forgery_overrides_a_veil_without_changing_true_roles_or_medium_readings(): void
    {
        // Exercise an interaction with the Classic roster's roles too.
        $s = $this->room->fresh()->state;
        $s['players'][$this->ids['dreamweaver']]['role'] = 'veilweaver';
        $s['players'][$this->ids['townsperson']]['role'] = 'medium';
        $s['players'][$this->ids['oathkeeper']]['alive'] = false;
        $this->room->update(['state' => $s]);
        $this->act('oracle', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('dreamweaver', 'night', ['target' => $this->ids['exorcist']]);
        $this->act('counterfeiter', 'night', ['target' => $this->ids['exorcist'], 'use_ability' => true, 'forged_alignment' => 'town']);
        $this->act('townsperson', 'night', ['target' => $this->ids['oathkeeper'], 'use_ability' => true]);
        $this->expire();
        $this->assertSame('town', $this->stateFor('oracle')['me']['results'][0]['alignment']);
        $this->assertSame('town', $this->stateFor('townsperson')['me']['results'][0]['alignment']);
        $this->assertSame('exorcist', $this->stateFor('exorcist')['me']['role']);
        $this->assertArrayNotHasKey('forged', $this->stateFor('oracle')['me']['results'][0]);
        $actions = array_column($this->room->state['rounds'][1]['night']['actions'], null, 'player_id');
        $this->assertTrue($actions[$this->ids['oracle']]['forged']);
        $this->assertFalse($actions[$this->ids['counterfeiter']]['contributed']);
    }

    public function test_disruption_stops_both_new_night_abilities_but_spends_them(): void
    {
        foreach (['phantasm', 'counterfeiter'] as $role) {
            $extra = $role === 'counterfeiter' ? ['forged_alignment' => 'cult'] : [];
            $this->act($role, 'night', ['target' => $this->ids['oracle'], 'use_ability' => true, ...$extra]);
            $this->act('dreamweaver', 'night', ['target' => $this->ids[$role], 'use_ability' => true]);
            $this->expire();
            $this->assertNull($this->stateFor('oracle')['me']['haunting']);
            $this->assertSame('disrupted', $this->stateFor($role)['me']['results'][0]['kind']);
            $this->assertTrue($this->stateFor($role)['me']['ability_used']);
            $this->expire();
            $this->expire();
            $this->reject(fn () => $this->act($role, 'night', ['target' => $this->ids['oracle'], 'use_ability' => true, ...$extra]));
            // Give the test's Dreamweaver a second independent scenario.
            $s = $this->room->fresh()->state;
            $s['players'][$this->ids['dreamweaver']]['ability_used'] = false;
            $this->room->update(['state' => $s]);
        }
    }

    public function test_exorcism_clears_both_afflictions_without_consuming_discussion_readiness(): void
    {
        $this->act('phantasm', 'night', ['target' => $this->ids['oracle'], 'use_ability' => true]);
        $this->expire();
        $s = $this->room->fresh()->state;
        $s['players'][$this->ids['oracle']]['curse'] = app(CurseEngine::class)->create(0, 10, 1, 'mist');
        $this->room->update(['state' => $s]);
        $view = $this->act('exorcist', 'exorcise', ['target' => $this->ids['oracle']]);
        $this->assertTrue($view['me']['ability_used']);
        $this->assertFalse($view['me']['submitted']);
        $this->assertNull($this->stateFor('oracle')['me']['curse']);
        $this->assertNull($this->stateFor('oracle')['me']['haunting']);
        $this->reject(fn () => $this->act('exorcist', 'exorcise', ['target' => $this->ids['oracle']]));
        $this->assertTrue($this->act('exorcist', 'discussion_ready')['me']['submitted']);
    }

    public function test_invalid_abilities_and_wrong_phases_do_not_spend_anything(): void
    {
        foreach (['phantasm', 'counterfeiter'] as $role) {
            $this->reject(fn () => $this->act($role, 'night', ['use_ability' => true]));
            $this->reject(fn () => $this->act($role, 'night', ['target' => $this->ids['oracle']]));
            $this->assertFalse($this->stateFor($role)['me']['ability_used']);
        }
        $this->reject(fn () => $this->act('exorcist', 'exorcise', ['target' => $this->ids['oracle']]));
        $this->reject(fn () => $this->act('oathkeeper', 'oath', ['target' => $this->ids['oracle']]));
        $this->expire();
        $this->reject(fn () => $this->act('exorcist', 'exorcise', ['target' => $this->ids['exorcist']]));
        $this->reject(fn () => $this->act('oracle', 'exorcise', ['target' => $this->ids['exorcist']]));
        $this->assertFalse($this->stateFor('exorcist')['me']['ability_used']);
        // Cleansing an unaffected player still spends it without revealing whether a curse existed.
        $this->assertTrue($this->act('exorcist', 'exorcise', ['target' => $this->ids['oracle']])['me']['ability_used']);
    }

    public function test_kept_public_oath_protects_only_the_following_night(): void
    {
        $this->expire();
        $this->act('oathkeeper', 'oath', ['target' => $this->ids['phantasm']]);
        $public = array_column($this->stateFor('oracle')['players'], null, 'id');
        $this->assertSame($this->ids['phantasm'], $public[$this->ids['oathkeeper']]['oath']['target_id']);
        $this->reject(fn () => $this->act('oathkeeper', 'oath', ['target' => $this->ids['oracle']]));
        $this->expire();
        $this->act('oathkeeper', 'vote', ['target' => $this->ids['phantasm']]);
        $this->expire();
        $this->assertTrue($this->stateFor('oathkeeper')['me']['oath_protected']);
        $s = $this->room->fresh()->state;
        $s['players'][$this->ids['counterfeiter']]['role'] = 'acolyte';
        $this->room->update(['state' => $s]);
        $this->act('counterfeiter', 'night', ['target' => $this->ids['oathkeeper']]);
        $this->expire();
        $this->assertNull($this->stateFor('oathkeeper')['me']['curse']);
        $this->expire();
        $this->expire();
        $this->assertFalse($this->stateFor('oathkeeper')['me']['oath_protected']);
        $this->act('counterfeiter', 'night', ['target' => $this->ids['oathkeeper']]);
        $this->expire();
        $this->assertNotNull($this->stateFor('oathkeeper')['me']['curse']);
    }

    public function test_redirected_ballot_breaks_oath_and_keeps_actual_choice_truthful(): void
    {
        $this->expire();
        $this->act('oathkeeper', 'oath', ['target' => $this->ids['phantasm']]);
        $this->expire();
        $s = $this->room->fresh()->state;
        $s['players'][$this->ids['oathkeeper']]['curse'] = app(CurseEngine::class)->create(8, 10, 1, 'misdirection');
        $this->room->update(['state' => $s]);
        $view = $this->act('oathkeeper', 'vote', ['target' => $this->ids['phantasm']]);
        $this->assertNotNull($view['me']['curse_notice']);
        $this->expire();
        $this->assertFalse($this->stateFor('oathkeeper')['me']['oath_protected']);
        $this->assertFalse($this->stateFor('oathkeeper')['me']['results'][0]['kept']);
    }

    public function test_using_illusion_ability_breaks_concord_and_rematch_resets_all_new_state(): void
    {
        $s = $this->room->fresh()->state;
        $s['mission'] = config('game.missions.concord');
        $this->room->update(['state' => $s]);
        $this->act('phantasm', 'night', ['target' => $this->ids['oracle'], 'use_ability' => true]);
        $this->act('counterfeiter', 'night');
        $this->act('dreamweaver', 'night');
        $this->expire();
        $this->assertSame(0, $this->stateFor('oracle')['ritual']['tokens']);
        $this->act('oathkeeper', 'oath', ['target' => $this->ids['phantasm']]);
        $this->act('exorcist', 'exorcise', ['target' => $this->ids['oracle']]);
        $s = $this->room->fresh()->state;
        $s['tokens'] = $s['threshold'];
        $this->room->update(['state' => $s]);
        $this->expire();
        $this->expire();
        $finished = $this->stateFor('oracle');
        $this->assertSame('finished', $finished['phase']);
        $this->assertCount(2, $finished['recap']['rounds'][0]['discussion']);
        $this->assertSame('illusions', $finished['recap']['roster']);
        $this->engine->access($this->room->code, 'guest-0', ['type' => 'rematch', 'phase_id' => $finished['phase_id']]);
        foreach (array_keys($this->ids) as $role) {
            $view = $this->stateFor($role);
            $this->assertFalse($view['me']['ability_used']);
            $this->assertNull($view['me']['haunting']);
            $this->assertFalse($view['me']['oath_protected']);
            $this->assertEmpty($view['me']['results']);
            $this->assertEmpty(array_filter(array_column($view['players'], 'oath')));
        }
    }
}
