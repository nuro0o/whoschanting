<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\FactionExpansions;
use App\Game\GameModes;
use App\Game\MatchEngine;
use App\Game\PaidCosmetics;
use App\Game\PurchasePolicy;
use App\Game\RoomExpansions;
use App\Models\GameRoom;
use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomExpansionsTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    private User $owner;

    private array $identities = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
        $this->engine = app(MatchEngine::class);
        $this->owner = User::factory()->create();
        foreach (FactionExpansions::ADDITIONAL as $id) {
            config(['factions.'.$id.'.active' => true]);
            PaidOrder::create(['id' => (string) Str::uuid(), 'user_id' => $this->owner->id,
                'bundle_id' => $id, 'price_id' => 'price_'.$id, 'amount' => 499, 'currency' => 'eur',
                'cosmetics' => [], 'checkout_parameters' => [], 'status' => 'paid', 'paid_at' => now()]);
        }
    }

    private function gathering(string $expansion, bool $start = true, string $variant = 'classic'): GameRoom
    {
        $room = $this->engine->create('guest-0', 'Player 0');
        for ($i = 1; $i < config('factions.'.$expansion.'.min_players'); $i++) {
            $this->engine->join($room->code, 'guest-'.$i, 'Player '.$i, accountId: $i === 1 ? $this->owner->id : null);
        }
        foreach ($room->fresh()->state['players'] as $id => $player) {
            $this->identities[$id] = 'guest-'.substr($player['name'], 7);
        }
        $this->act($room, $room->state['host_id'], 'configure_mode', ['setup' => ['expansion' => $expansion, 'classic_variant' => $variant]]);
        foreach (array_keys($room->fresh()->state['players']) as $id) {
            $this->act($room, $id, 'ready');
        }
        if ($start) {
            $this->act($room, $room->state['host_id'], 'start');
            $this->expire($room);
        }

        return $room->refresh();
    }

    private function act(GameRoom $room, string $id, string $type, array $extra = []): array
    {
        $s = $room->fresh()->state;

        return $this->engine->access($room->code, $this->identities[$id], ['type' => $type, 'phase_id' => $s['phase_id'], ...$extra], $s['players'][$id]['user_id'] ?? null);
    }

    private function roomView(GameRoom $room, string $id): array
    {
        return $this->engine->access($room->code, $this->identities[$id], accountId: $room->fresh()->state['players'][$id]['user_id'] ?? null);
    }

    private function role(GameRoom $room, string $role): string
    {
        return array_key_first(array_filter($room->fresh()->state['players'], fn ($p) => $p['role'] === $role));
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
            $this->fail('An invalid expansion action was accepted.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_all_rosters_preserve_both_original_factions_and_cannot_be_injected_into_other_modes(): void
    {
        $modes = new GameModes;
        foreach (FactionExpansions::ADDITIONAL as $id) {
            foreach (['classic', 'illusions'] as $variant) {
                foreach (range(config('factions.'.$id.'.min_players'), 15) as $count) {
                    $roster = $modes->roster($modes->normalize(['expansion' => $id, 'classic_variant' => $variant]), $count);
                    $this->assertCount($count, $roster);
                    foreach (config('factions.'.$id.'.roles') as $role) {
                        $this->assertSame(1, array_count_values($roster)[$role]);
                    }
                    $this->assertContains('oracle', $roster);
                    $this->assertContains($variant === 'illusions' ? 'phantasm' : 'veilweaver', $roster);
                }
            }
            $this->reject(fn () => $modes->roster($modes->normalize(['expansion' => $id]), config('factions.'.$id.'.min_players') - 1));
            $this->reject(fn () => $modes->normalize(['mode' => 'chaos', 'expansion' => $id]));
            $this->reject(fn () => $modes->normalize(['fae_court' => true, 'expansion' => $id]));
            $this->reject(fn () => $modes->normalize(['mode' => 'custom', 'roles' => [config('factions.'.$id.'.roles')[0] => 1, 'acolyte' => 2, 'townsperson' => 6]]));
        }
    }

    public function test_every_pack_requires_a_verified_seated_owner_and_records_only_the_supplying_purchase(): void
    {
        foreach (FactionExpansions::ADDITIONAL as $id) {
            $this->reject(fn () => $this->engine->create('nobody', 'Host', setup: ['expansion' => $id]));
            $room = $this->gathering($id, false);
            $order = PaidOrder::where('bundle_id', $id)->firstOrFail();
            $order->update(['status' => 'refunded']);
            $this->reject(fn () => $this->act($room, $room->state['host_id'], 'start'));
            $order->update(['status' => 'paid']);
            $this->owner->forceFill(['email_verified_at' => null])->save();
            $this->reject(fn () => $this->act($room, $room->state['host_id'], 'start'));
            $this->owner->forceFill(['email_verified_at' => now()])->save();
            $this->act($room, $room->state['host_id'], 'start');
            $this->assertDatabaseHas('paid_pack_usages', ['paid_order_id' => $order->id, 'match_id' => $room->fresh()->state['match_id']]);
            config(['factions.'.$id.'.active' => false, 'factions.'.$id.'.goal' => 999]);
            $order->update(['status' => 'refunded']);
            $this->expire($room);
            $view = $this->roomView($room, $room->state['host_id']);
            $this->assertSame($id, $view['expansion']['id']);
            $this->assertSame(3, $view['expansion']['goal']);
        }
        $this->assertDatabaseCount('paid_pack_usages', 4);
    }

    public function test_inactive_packs_are_hidden_and_checkout_is_blocked_before_contacting_stripe(): void
    {
        Http::preventStrayRequests();
        config(['inertia.ssr.enabled' => false, 'factions.fae-court.active' => false]);
        foreach (FactionExpansions::ADDITIONAL as $id) {
            config(['factions.'.$id.'.active' => false]);
            $this->assertNull(collect((new PaidCosmetics)->view($this->owner->id))->firstWhere('id', $id));
            $this->actingAs($this->owner)->postJson('/account/store/checkout', ['bundle_id' => $id,
                'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true,
                'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
        }
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('activeFactions', [])->where('factionCatalog', []));
        Http::assertNothingSent();
        $this->assertDatabaseCount('paid_orders', 4);
    }

    public function test_drowned_marks_and_soundings_stay_private_and_counterplay_spends_the_normal_action(): void
    {
        $room = $this->gathering('drowned');
        $caller = $this->role($room, 'drowned_tidecaller');
        $oracle = $this->role($room, 'oracle');
        $cult = $this->role($room, 'veilweaver');
        $this->act($room, $caller, 'night', ['expansion_action' => 'mark', 'target' => $cult]);
        $this->act($room, $oracle, 'night', ['expansion_action' => 'sound', 'target' => $cult]);
        $this->expire($room);
        $own = $this->roomView($room, $caller);
        $outside = $this->roomView($room, $oracle);
        $this->assertSame([$cult], $own['expansion']['marks']);
        $this->assertArrayNotHasKey('marks', $outside['expansion']);
        $this->assertArrayNotHasKey('events', $own['expansion']);
        $this->assertStringContainsString('carries a Drowned mark', json_encode($outside['me']['results']));
        $this->assertFalse($room->state['players'][$oracle]['ability_used'] ?? false);
        $this->assertCount(1, $room->state['players'][$oracle]['results']);
        $this->assertArrayNotHasKey('alignment', $room->state['players'][$oracle]['results'][0]);
    }

    public function test_each_active_pack_checks_out_with_its_server_price_and_unlocks_only_after_payment(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        config(['payments.secret_key' => 'sk_test_expansions', 'payments.webhook_secret' => 'whsec_expansions']);
        Http::preventStrayRequests();
        foreach (FactionExpansions::ADDITIONAL as $id) {
            $index = array_search($id, array_column(config('payments.bundles'), 'id'), true);
            config(['payments.bundles.'.$index.'.price_id' => 'price_'.$id, 'payments.bundles.'.$index.'.amount' => 499]);
            Http::fake(function (Request $request) use (&$id, $buyer) {
                if (str_contains($request->url(), '/prices/')) {
                    return Http::response(['active' => true, 'type' => 'one_time', 'unit_amount' => 499, 'currency' => 'eur']);
                }
                if ($request->method() === 'POST') {
                    return Http::response(['id' => 'cs_test_'.str_replace('-', '', $id), 'url' => 'https://checkout.stripe.com/c/pay/'.$id]);
                }
                $order = PaidOrder::where('user_id', $buyer->id)->where('bundle_id', $id)->firstOrFail();

                return Http::response(['id' => 'cs_test_'.str_replace('-', '', $id), 'mode' => 'payment', 'client_reference_id' => $order->id,
                    'metadata' => $order->checkout_parameters['metadata'], 'payment_status' => 'paid', 'status' => 'complete',
                    'currency' => 'eur', 'amount_subtotal' => 499, 'amount_total' => 499,
                    'payment_intent' => ['id' => 'pi_'.$id, 'status' => 'succeeded', 'metadata' => $order->checkout_parameters['metadata'],
                        'latest_charge' => ['id' => 'ch_'.$id, 'paid' => true, 'refunded' => false, 'disputed' => false]],
                    'line_items' => ['data' => [['price' => ['id' => 'price_'.$id], 'quantity' => 1]]]]);
            });
            $this->actingAs($buyer)->postJson('/account/store/checkout', ['bundle_id' => $id, 'amount' => 1,
                'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true,
                'purchase_policy_version' => PurchasePolicy::VERSION])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/'.$id);
            $seats = ['players' => [['user_id' => $buyer->id]]];
            $this->assertFalse(FactionExpansions::available($seats, $id));
            $this->getJson('/account/store/status?session_id=cs_test_'.str_replace('-', '', $id))->assertOk()->assertJsonPath('status', 'paid');
            $this->assertTrue(FactionExpansions::available($seats, $id));
            $this->assertDatabaseHas('paid_orders', ['user_id' => $buyer->id, 'bundle_id' => $id, 'amount' => 499]);
        }
        $portraits = collect((new PaidCosmetics)->ownedCosmetics($buyer->id));
        $this->assertCount(4, $portraits);
        $this->assertSame(['characters'], $portraits->pluck('category')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['drowned_diver', 'relic_broker', 'hollow_cantor', 'carnival_ringmaster'], $portraits->pluck('id')->all());
    }

    public function test_cleanse_prevents_new_marks_and_ferry_conflicts_are_submission_order_independent(): void
    {
        $room = $this->gathering('drowned');
        $s = $room->state;
        $caller = $this->role($room, 'drowned_tidecaller');
        $ferry = $this->role($room, 'drowned_ferryman');
        $oracle = $this->role($room, 'oracle');
        $cult = $this->role($room, 'veilweaver');
        $s['expansion']['marks'][$oracle] = true;
        $actions = [$ferry => ['expansion_action' => 'ferry', 'target' => $oracle, 'secondary_target' => $cult],
            $caller => ['expansion_action' => 'mark', 'target' => $cult]];
        $a = $b = $s;
        RoomExpansions::night($a, $actions, []);
        RoomExpansions::night($b, array_reverse($actions, true), []);
        $this->assertSame($a['expansion']['marks'], $b['expansion']['marks']);
        $this->assertCount(2, $a['expansion']['marks']);
        $actions[$cult] = ['expansion_action' => 'cleanse', 'target' => $cult];
        RoomExpansions::night($s, $actions, []);
        $this->assertSame([$oracle => true], $s['expansion']['marks']);
        RoomExpansions::cleanse($s, $oracle);
        $this->assertSame([], $s['expansion']['marks']);
    }

    public function test_tide_counts_only_living_marks_after_vote_and_adds_a_shared_win(): void
    {
        $room = $this->gathering('drowned');
        $s = $room->state;
        $targets = array_keys(array_filter($s['players'], fn ($p) => $p['alignment'] !== 'drowned'));
        $s['expansion']['marks'] = array_fill_keys(array_slice($targets, 0, 3), true);
        $s['day'] = 2;
        RoomExpansions::settle($s);
        $this->assertFalse($s['expansion']['secured']);
        $s['day'] = 3;
        $s['players'][$targets[0]]['alive'] = false;
        RoomExpansions::settle($s);
        $this->assertFalse($s['expansion']['secured']);
        $s['expansion']['marks'][$targets[3]] = true;
        RoomExpansions::settle($s);
        $this->assertTrue($s['expansion']['secured']);
        foreach (['town', 'cult'] as $winner) {
            $s['winners'] = [$winner];
            $s['win_reason'] = 'Primary objective.';
            RoomExpansions::finish($s);
            $this->assertSame([$winner, 'drowned'], $s['winners']);
        }
    }

    public function test_gilded_information_transfers_conflicts_and_departures(): void
    {
        $room = $this->gathering('gilded-hand');
        $s = $room->state;
        $lifter = $this->role($room, 'gilded_lifter');
        $appraiser = $this->role($room, 'gilded_appraiser');
        $holder = $s['expansion']['relics']['silver_key'];
        $this->assertCount(3, array_unique($s['expansion']['relics']));
        $this->assertSame([], RoomExpansions::view($s, $lifter)['court_inventory']);
        $this->assertArrayNotHasKey('court_inventory', RoomExpansions::view($s, $holder));
        $this->act($room, $lifter, 'night', ['expansion_action' => 'steal', 'target' => $holder, 'relic_id' => 'silver_key']);
        $this->act($room, $appraiser, 'night', ['expansion_action' => 'locate', 'relic_id' => 'silver_key']);
        $this->expire($room);
        $s = $room->state;
        $this->assertSame($lifter, $s['expansion']['relics']['silver_key']);
        $this->assertStringContainsString($s['players'][$lifter]['name'], json_encode($s['players'][$appraiser]['results']));
        $s['expansion']['relics']['silver_key'] = $holder;
        RoomExpansions::night($s, [$lifter => ['expansion_action' => 'steal', 'target' => $holder, 'relic_id' => 'silver_key'],
            $holder => ['expansion_action' => 'give', 'target' => $appraiser, 'relic_id' => 'silver_key']], []);
        $this->assertSame($holder, $s['expansion']['relics']['silver_key']);
        RoomExpansions::night($s, [$holder => ['expansion_action' => 'give', 'target' => $appraiser, 'relic_id' => 'silver_key']], []);
        $this->assertSame($appraiser, $s['expansion']['relics']['silver_key']);
        $s['players'][$appraiser]['alive'] = false;
        RoomExpansions::dropRelics($s);
        $this->assertNotSame($appraiser, $s['expansion']['relics']['silver_key']);
        $this->assertNotSame('gilded', $s['players'][$s['expansion']['relics']['silver_key']]['alignment']);
        $this->assertCount(3, RoomExpansions::view($s, $holder, true)['court_inventory']);
    }

    public function test_gilded_win_requires_current_living_ownership_at_match_end(): void
    {
        $room = $this->gathering('gilded-hand');
        $s = $room->state;
        $lifter = $this->role($room, 'gilded_lifter');
        $oracle = $this->role($room, 'oracle');
        $s['expansion']['relics'] = array_fill_keys(array_keys($s['expansion']['relics']), $lifter);
        $s['winners'] = ['town'];
        $s['win_reason'] = '';
        RoomExpansions::finish($s);
        $this->assertSame(['town', 'gilded'], $s['winners']);
        $s['expansion']['relics']['silver_key'] = $oracle;
        $s['winners'] = ['cult'];
        RoomExpansions::finish($s);
        $this->assertSame(['cult'], $s['winners']);
        $this->assertFalse($s['expansion']['secured']);
    }

    public function test_choir_only_siphons_new_steps_leaves_one_and_resonance_is_spent_once(): void
    {
        $room = $this->gathering('hollow-choir');
        $cantor = $this->role($room, 'choir_cantor');
        $resonant = $this->role($room, 'choir_resonant');
        $effective = [$cantor => ['expansion_action' => 'siphon'], $resonant => ['expansion_action' => 'resonate']];
        foreach ([0 => 0, 1 => 0, 2 => 1, 3 => 2] as $gained => $taken) {
            $s = $room->state;
            $s['tokens'] = 5 + $gained;
            $this->assertSame($gained - $taken, RoomExpansions::siphon($s, $effective, $gained));
            $this->assertSame(5 + $gained - $taken, $s['tokens']);
            $this->assertSame($taken, $s['expansion']['echoes']);
        }
        $this->act($room, $resonant, 'night', ['expansion_action' => 'resonate']);
        $this->expire($room);
        $this->assertTrue($room->state['players'][$resonant]['expansion_ability_used']);
        $this->assertSame(0, $room->state['expansion']['echoes']);
        $this->assertNotContains('resonate', array_column(RoomExpansions::choices($room->state, $resonant), 'id'));
    }

    public function test_choir_listens_to_effective_chants_without_revealing_allegiance(): void
    {
        $room = $this->gathering('hollow-choir');
        $cantor = $this->role($room, 'choir_cantor');
        $resonant = $this->role($room, 'choir_resonant');
        $cult = $this->role($room, 'veilweaver');
        $this->act($room, $resonant, 'night', ['expansion_action' => 'listen', 'target' => $cult]);
        $this->act($room, $cantor, 'night', ['expansion_action' => 'siphon']);
        foreach ($room->state['players'] as $id => $p) {
            if ($p['alignment'] === 'cult') {
                $this->act($room, $id, 'night');
            }
        }
        $this->expire($room);
        $this->assertStringContainsString(' chanted tonight.', json_encode($room->state['players'][$resonant]['results']));
        $this->assertArrayNotHasKey('alignment', $room->state['players'][$resonant]['results'][0]);
        $this->assertGreaterThanOrEqual(1, $room->state['expansion']['echoes']);
        $this->assertGreaterThanOrEqual(1, $room->state['tokens']);
    }

    public function test_carnival_requires_committed_distinct_acts_actual_ballots_and_two_days(): void
    {
        $room = $this->gathering('carnival');
        $s = $room->state;
        $harlequin = $this->role($room, 'carnival_harlequin');
        $augur = $this->role($room, 'carnival_augur');
        $oracle = $this->role($room, 'oracle');
        $cult = $this->role($room, 'veilweaver');
        RoomExpansions::night($s, [$harlequin => ['expansion_action' => 'taunt'], $augur => ['expansion_action' => 'foretell', 'target' => $cult]], []);
        $this->assertNull(RoomExpansions::view($s, $oracle)['plan']);
        $this->assertSame('foretell', RoomExpansions::view($s, $augur)['plan']['action']);
        $s['rounds'][1] = ['last_words' => ['accusations' => [['player_id' => $oracle, 'target_id' => $harlequin]]],
            'vote' => ['ballots' => [], 'banished_id' => $cult]];
        RoomExpansions::settle($s);
        $this->assertSame(['foretell' => 1], $s['expansion']['acts']);
        $s['actions'][$harlequin] = ['target' => $cult];
        RoomExpansions::settle($s);
        $this->assertCount(2, $s['expansion']['acts']);
        $this->assertFalse($s['expansion']['secured']);
        $s['day'] = 2;
        RoomExpansions::night($s, [$harlequin => ['expansion_action' => 'tie']], []);
        $s['rounds'][2]['vote'] = ['banished_id' => null, 'ballots' => [['target_id' => $oracle], ['target_id' => $cult], ['target_id' => null], ['target_id' => null]]];
        RoomExpansions::settle($s);
        $this->assertFalse($s['expansion']['secured']); // Abstention leads; not a candidate tie.
        $s['rounds'][2]['vote']['ballots'] = [['target_id' => $oracle], ['target_id' => $cult]];
        $s['actions'][$harlequin] = ['target' => null];
        RoomExpansions::settle($s);
        $this->assertFalse($s['expansion']['secured']);
        $s['actions'][$harlequin] = ['target' => $oracle];
        RoomExpansions::settle($s);
        $this->assertTrue($s['expansion']['secured']);
        $this->assertSame(['foretell' => 1, 'taunt' => 1, 'tie' => 2], $s['expansion']['acts']);
    }

    public function test_disruption_stops_marks_and_forged_actions_cannot_spend_two_abilities(): void
    {
        $room = $this->gathering('drowned', variant: 'illusions');
        $caller = $this->role($room, 'drowned_tidecaller');
        $dream = $this->role($room, 'dreamweaver');
        $oracle = $this->role($room, 'oracle');
        $this->reject(fn () => $this->act($room, $oracle, 'night', ['expansion_action' => 'mark', 'target' => $dream]));
        $this->reject(fn () => $this->act($room, $oracle, 'night', ['expansion_action' => 'sound', 'target' => $dream, 'use_ability' => true]));
        $this->reject(fn () => $this->act($room, $caller, 'night', ['expansion_action' => 'mark', 'target' => $caller]));
        $this->reject(fn () => $this->act($room, $caller, 'night', ['relic_id' => 'silver_key']));
        $this->act($room, $caller, 'night', ['expansion_action' => 'mark', 'target' => $oracle]);
        $this->act($room, $dream, 'night', ['target' => $caller, 'use_ability' => true]);
        $this->expire($room);
        $this->assertSame([], $room->state['expansion']['marks']);
    }

    public function test_finished_match_archives_shared_victory_and_rematch_clears_objectives(): void
    {
        foreach (FactionExpansions::ADDITIONAL as $id) {
            $room = $this->gathering($id);
            $s = $room->state;
            $s['expansion']['secured'] = true;
            $s['expansion']['echoes'] = 3;
            $member = $this->role($room, config('factions.'.$id.'.roles')[0]);
            if ($id === 'gilded-hand') {
                $s['expansion']['relics'] = array_fill_keys(array_keys($s['expansion']['relics']), $member);
            }
            foreach ($s['players'] as &$p) {
                if ($p['alignment'] === 'cult') {
                    $p['alive'] = false;
                }
            }
            unset($p);
            $room->update(['state' => $s]);
            $this->expire($room);
            $this->assertSame('finished', $room->state['phase']);
            $this->assertSame(['town', config('factions.'.$id.'.alignment')], $room->state['winners']);
            $view = $this->roomView($room, $member);
            $this->assertArrayHasKey('events', $view['recap']['expansion']);
            $this->act($room, $room->state['host_id'], 'rematch');
            $this->assertArrayNotHasKey('expansion', $room->fresh()->state);
        }
    }
}
