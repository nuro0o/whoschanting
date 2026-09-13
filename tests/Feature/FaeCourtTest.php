<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\FaeCourt;
use App\Game\GameModes;
use App\Game\MatchEngine;
use App\Game\PaidCosmetics;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FaeCourtTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    private User $owner;

    private PaidOrder $order;

    /** @var array<string,string> */
    private array $identities = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
        $this->engine = app(MatchEngine::class);
        $this->owner = User::factory()->create();
        $this->order = PaidOrder::create(['id' => (string) Str::uuid(), 'user_id' => $this->owner->id,
            'bundle_id' => 'fae-court', 'price_id' => 'price_fae', 'amount' => 299, 'currency' => 'eur',
            'cosmetics' => [], 'checkout_parameters' => [], 'status' => 'paid', 'paid_at' => now()]);
    }

    private function gathering(bool $start = true, string $variant = 'classic'): GameRoom
    {
        $this->identities = [];
        $room = $this->engine->create('guest-0', 'Player 0');
        for ($i = 1; $i < 7; $i++) {
            $this->engine->join($room->code, 'guest-'.$i, 'Player '.$i, accountId: $i === 1 ? $this->owner->id : null);
        }
        foreach ($room->fresh()->state['players'] as $id => $player) {
            $this->identities[$id] = 'guest-'.substr($player['name'], -1);
        }
        $this->act($room, $room->fresh()->state['host_id'], 'configure_mode', ['setup' => ['fae_court' => true, 'classic_variant' => $variant]]);
        foreach (array_keys($this->identities) as $id) {
            $this->act($room, $id, 'ready');
        }
        if ($start) {
            $this->act($room, $room->fresh()->state['host_id'], 'start');
            $this->expire($room);
        }

        return $room->refresh();
    }

    /** @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function act(GameRoom $room, string $id, string $type, array $extra = []): array
    {
        $state = $room->fresh()->state;

        return $this->engine->access($room->code, $this->identities[$id], ['type' => $type, 'phase_id' => $state['phase_id'], ...$extra], $state['players'][$id]['user_id'] ?? null);
    }

    /** @return array<string,mixed> */
    private function roomView(GameRoom $room, string $id): array
    {
        return $this->engine->access($room->code, $this->identities[$id], accountId: $room->fresh()->state['players'][$id]['user_id'] ?? null);
    }

    private function role(GameRoom $room, string $role): string
    {
        return array_key_first(array_filter($room->fresh()->state['players'], fn (array $player): bool => $player['role'] === $role));
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
            $this->fail('Invalid Fae action was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function offer(GameRoom $room, string $kind, string $recipient, ?string $promise = null, ?string $gift = null): string
    {
        $this->act($room, $this->role($room, 'fae_broker'), 'night', ['target' => $recipient, 'bargain_kind' => $kind, 'promise_target' => $promise,
            'gift_target' => $kind === 'voice' ? ($gift ?? $this->role($room, 'fae_broker')) : null]);
        $this->expire($room);
        $this->assertSame('bargains', $room->state['phase']);

        return $this->roomView($room, $recipient)['fae']['bargains'][0]['id'];
    }

    public function test_guest_host_can_enable_with_any_seated_owner_and_roster_stays_balanced(): void
    {
        $room = $this->gathering();
        $roles = array_count_values(array_column($room->state['players'], 'role'));
        $this->assertSame(1, $roles['fae_broker']);
        $this->assertSame(1, $roles['oracle']);
        $this->assertSame(['cult' => 3, 'town' => 3, 'fae' => 1], array_replace(['cult' => 0, 'town' => 0, 'fae' => 0], array_count_values(array_column($room->state['players'], 'alignment'))));
        $this->assertNull($room->state['players'][$room->state['host_id']]['user_id']);
        $this->assertSame(25, $room->state['match_rules']['seconds']['bargains']);
        $this->assertSame(3, $room->state['fae']['rules']['seals_to_win']);
    }

    public function test_unowned_forged_custom_and_too_small_setups_are_rejected(): void
    {
        $this->reject(fn () => $this->engine->create('guest', 'Host', setup: ['fae_court' => true]));
        $modes = new GameModes;
        $this->reject(fn () => $modes->normalize(['mode' => 'custom', 'roles' => ['fae_broker' => 1, 'acolyte' => 2, 'oracle' => 4]]));
        $this->reject(fn () => $modes->normalize(['mode' => 'chaos', 'fae_court' => true]));
        $this->reject(fn () => $modes->roster($modes->normalize(['fae_court' => true]), 6));
        foreach (['classic', 'illusions'] as $variant) {
            foreach (range(7, 15) as $count) {
                $roster = $modes->roster($modes->normalize(['fae_court' => true, 'classic_variant' => $variant]), $count);
                $this->assertCount($count, $roster);
                $this->assertSame(1, array_count_values($roster)['fae_broker']);
            }
        }
        $this->assertNotContains('fae_broker', $modes->roster($modes->normalize(['mode' => 'chaos']), 15));
    }

    public function test_start_rechecks_refunds_but_running_match_keeps_expansion(): void
    {
        $room = $this->gathering(false);
        $this->order->update(['status' => 'refunded']);
        $this->assertNotNull($this->roomView($room, $room->state['host_id'])['mode_preview']['error']);
        $this->reject(fn () => $this->act($room, $room->state['host_id'], 'start'));
        $this->order->update(['status' => 'paid']);
        $this->act($room, $room->state['host_id'], 'start');
        $this->order->update(['status' => 'disputed']);
        $this->expire($room);
        $this->assertNotNull($this->roomView($room, $room->state['host_id'])['fae']);
        $this->assertSame('night', $room->state['phase']);
    }

    public function test_removing_owner_blocks_start_and_pending_payment_does_not_unlock(): void
    {
        $room = $this->gathering(false);
        $ownerSeat = array_key_first(array_filter($room->state['players'], fn (array $p): bool => $p['user_id'] === $this->owner->id));
        $this->act($room, $room->state['host_id'], 'remove_player', ['target' => $ownerSeat]);
        $this->assertFalse($this->roomView($room, $room->state['host_id'])['expansions']['fae_court']['available']);
        $this->order->update(['status' => 'pending']);
        $this->assertFalse(collect((new PaidCosmetics)->view($this->owner->id))->firstWhere('id', 'fae-court')['owned']);
        $this->assertFalse(FaeCourt::available(['players' => [['user_id' => $this->owner->id]]]));
    }

    public function test_offer_identity_and_clue_are_private_and_replies_cannot_be_forged_or_replayed(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $promise = $this->role($room, 'warden');
        $broker = $this->role($room, 'fae_broker');
        $offer = $this->offer($room, 'lantern', $recipient, $promise);
        $private = $this->roomView($room, $recipient);
        $this->assertArrayNotHasKey('sender_id', $private['fae']['bargains'][0]);
        $this->assertArrayNotHasKey('visited', $private['fae']['bargains'][0]);
        $this->assertEmpty($private['me']['results']);
        $this->assertEmpty($this->roomView($room, $promise)['fae']['bargains']);
        $this->assertNull($private['recap']);
        $this->reject(fn () => $this->act($room, $promise, 'fae_response', ['bargain_id' => $offer, 'accept' => true]));
        $this->reject(fn () => $this->act($room, $broker, 'fae_response', ['bargain_id' => $offer, 'accept' => true]));
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
        $this->assertCount(1, $this->roomView($room, $recipient)['me']['results']);
        $this->assertEmpty($this->roomView($room, $broker)['me']['results']);
        $this->reject(fn () => $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => false]));
        $this->assertSame('bargains', $room->fresh()->state['phase']);
        $this->expire($room);
        $this->reject(fn () => $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]));
    }

    public function test_expired_and_declined_offers_grant_no_gift_and_early_night_completion_keeps_response_window(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $broker = $this->role($room, 'fae_broker');
        foreach ($room->state['players'] as $id => $p) {
            $this->act($room, $id, 'night', $id === $broker ? ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $broker] : ($p['role'] === 'lamplighter' ? ['target' => $recipient] : []));
        }
        $this->assertSame('bargains', $room->fresh()->state['phase']);
        $this->assertEquals(25, now()->diffInSeconds($room->fresh()->deadline));
        $this->expire($room);
        $this->assertSame('expired', $room->state['fae']['bargains'][0]['status']);
        $this->assertSame(0, $this->roomView($room, $recipient)['fae']['seals']);
    }

    public function test_cursed_recipient_can_accept_ballot_report_without_cleansing_and_explicit_abstention_earns_seal(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $this->act($room, $this->role($room, 'veilweaver'), 'night', ['target' => $recipient]);
        $offer = $this->offer($room, 'voice', $recipient);
        $this->assertNotNull($room->state['players'][$recipient]['curse']);
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
        $this->assertNotNull($room->fresh()->state['players'][$recipient]['curse']);
        $this->assertEmpty($this->roomView($room, $recipient)['me']['results']);
        $this->expire($room); // discussion
        while ($curse = $room->fresh()->state['players'][$recipient]['curse']) {
            $this->act($room, $recipient, 'solve_curse', ['curse_id' => $curse['id'], 'answer' => $curse['solution']]);
        }
        $this->expire($room); // voting
        $this->act($room, $recipient, 'vote');
        $this->expire($room);
        $this->assertSame('fulfilled', $room->state['fae']['bargains'][0]['status']);
        $this->assertSame(1, $this->roomView($room, $recipient)['fae']['seals']);
        $this->assertSame(['kind' => 'ballot', 'day' => 1, 'target' => $room->state['players'][$this->role($room, 'fae_broker')]['name'],
            'submitted' => false, 'voted_for' => null], $this->roomView($room, $recipient)['me']['results'][0]);
        $this->reject(fn () => $this->act($room, $this->role($room, 'fae_broker'), 'night', ['target' => $recipient, 'bargain_kind' => 'voice']));
    }

    public function test_missed_abstention_breaks_promise_and_protection_survives_betrayal(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $promise = $this->role($room, 'warden');
        $offer = $this->offer($room, 'thorn', $recipient, $promise);
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $recipient, 'vote'); // Break the promise to vote for Warden.
        $this->expire($room);
        $this->assertSame('broken', $room->state['fae']['bargains'][0]['status']);
        $this->act($room, $this->role($room, 'veilweaver'), 'night', ['target' => $recipient]);
        $this->expire($room);
        $this->assertNull($room->state['players'][$recipient]['curse']);
    }

    public function test_disruption_prevents_offer_and_oracle_reads_fae(): void
    {
        $room = $this->gathering();
        $broker = $this->role($room, 'fae_broker');
        $oracle = $this->role($room, 'oracle');
        $this->act($room, $this->role($room, 'dreamweaver'), 'night', ['target' => $broker, 'use_ability' => true]);
        $this->act($room, $oracle, 'night', ['target' => $broker]);
        $this->act($room, $broker, 'night', ['target' => $oracle, 'bargain_kind' => 'voice', 'gift_target' => $broker]);
        $this->expire($room);
        $this->assertEmpty($room->state['fae']['bargains']);
        $this->assertSame('fae', $this->roomView($room, $oracle)['me']['results'][0]['alignment']);
        $this->assertSame('disrupted', $this->roomView($room, $broker)['me']['results'][0]['kind']);
    }

    public function test_lantern_promise_uses_public_accusation_not_final_ballot(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $promise = $this->role($room, 'warden');
        $this->act($room, $promise, 'night', ['target' => $this->role($room, 'lamplighter')]);
        $offer = $this->offer($room, 'lantern', $recipient, $promise);
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
        $this->expire($room);
        $this->act($room, $recipient, 'accuse', ['target' => $promise]);
        $this->expire($room); // Last Words
        $this->expire($room); // voting
        $this->act($room, $recipient, 'vote');
        $this->expire($room);
        $this->assertSame('fulfilled', $room->state['fae']['bargains'][0]['status']);
    }

    public function test_both_primary_sides_can_share_victory_with_banished_fae_and_archive_all_bargains(): void
    {
        foreach (['town', 'cult'] as $winner) {
            $this->identities = [];
            $room = $this->gathering();
            $s = $room->state;
            $broker = $this->role($room, 'fae_broker');
            $partners = array_keys(array_filter($s['players'], fn (array $p): bool => $p['alignment'] === $winner));
            foreach (array_slice($partners, 0, 3) as $i => $id) {
                $s['fae']['bargains'][] = ['id' => (string) Str::uuid(), 'sender_id' => $broker, 'recipient_id' => $id, 'kind' => 'voice', 'promise_target' => null, 'day' => $i + 1, 'status' => 'fulfilled'];
            }
            $s['players'][$broker]['alive'] = false;
            foreach ($s['players'] as &$p) {
                if ($p['alignment'] !== $winner) {
                    $p['alive'] = false;
                }
            }
            unset($p);
            $room->update(['state' => $s]);
            $this->expire($room);
            $view = $this->roomView($room, $broker);
            $this->assertSame([$winner, 'fae'], $view['winners']);
            $this->assertSame('finished', $view['phase']);
            $this->assertSame($broker, $view['recap']['fae']['bargains'][0]['sender_id']);
            $archive = GameMatch::findOrFail($room->state['match_id']);
            $this->assertSame([$winner, 'fae'], $archive->recap['winners']);
            $this->act($room, $room->state['host_id'], 'rematch');
            $this->assertArrayNotHasKey('fae', $room->fresh()->state);
            $this->assertArrayNotHasKey('winners', $room->fresh()->state);
        }
    }

    public function test_enough_seals_without_winning_partner_does_not_win_and_fae_prevents_premature_final_pair(): void
    {
        $room = $this->gathering();
        $s = $room->state;
        $broker = $this->role($room, 'fae_broker');
        foreach ($s['players'] as $id => &$p) {
            if ($p['alignment'] === 'town') {
                $s['fae']['bargains'][] = ['id' => (string) Str::uuid(), 'sender_id' => $broker, 'recipient_id' => $id, 'kind' => 'voice', 'promise_target' => null, 'day' => count($s['fae']['bargains']) + 1, 'status' => 'fulfilled'];
            }
            $p['alive'] = in_array($id, [$broker, $this->role($room, 'oracle'), $this->role($room, 'veilweaver')], true);
        }
        unset($p);
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->assertSame('bargains', $room->state['phase']);
        $s = $room->state;
        $s['players'][$this->role($room, 'oracle')]['alive'] = false;
        $s['phase'] = 'night';
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->assertSame(['cult'], $room->state['winners']);
    }

    public function test_three_real_bargains_share_a_ritual_victory_and_award_fae_once(): void
    {
        $room = $this->gathering();
        $s = $room->state;
        $broker = $this->role($room, 'fae_broker');
        // Give the randomly dealt Broker a verified account to check progression.
        foreach ($s['players'] as $id => &$p) {
            $p['user_id'] = $id === $broker ? $this->owner->id : null;
        }
        unset($p);
        $s['mission'] = config('game.missions.concord');
        $room->update(['state' => $s]);
        $partners = array_keys(array_filter($s['players'], fn (array $p): bool => $p['alignment'] === 'cult'));
        foreach ($partners as $index => $recipient) {
            foreach ($room->fresh()->state['players'] as $id => $player) {
                $extra = $id === $broker ? ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $broker]
                    : ($player['role'] === 'lamplighter' ? ['target' => $recipient] : []);
                $this->act($room, $id, 'night', $extra);
            }
            $view = $this->roomView($room, $recipient);
            $offer = array_values(array_filter($view['fae']['bargains'], fn (array $b): bool => $b['status'] === 'offered'))[0];
            $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer['id'], 'accept' => true]);
            $this->expire($room);
            $this->expire($room);
            foreach (array_keys($s['players']) as $id) {
                $this->act($room, $id, 'vote');
            }
            $room->refresh();
            $this->assertSame($index + 1, $this->roomView($room, $broker)['fae']['seals']);
            $this->assertSame($index === 2 ? 'finished' : 'night', $room->state['phase']);
        }
        $view = $this->roomView($room, $broker);
        $this->assertSame(['cult', 'fae'], $view['winners']);
        $this->assertTrue($view['me']['match_reward']['won']);
        $this->assertSame(140, $view['me']['match_reward']['xp']);
        $this->assertSame(17, $view['me']['match_reward']['coins']);
        $this->assertDatabaseHas('player_profiles', ['user_id' => $this->owner->id, 'wins' => 1, 'town_wins' => 0, 'cult_wins' => 0]);
        $this->roomView($room, $broker);
        $this->engine->resolve($room->id);
        $this->assertDatabaseCount('match_rewards', 1);
        $this->assertDatabaseCount('coin_transactions', 1);
    }

    public function test_decline_missing_ballot_and_redirected_ballot_do_not_fulfill_promises(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $promise = $this->role($room, 'warden');
        $offer = $this->offer($room, 'voice', $recipient);
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => false]);
        $this->assertSame('declined', $room->fresh()->state['fae']['bargains'][0]['status']);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $this->role($room, 'fae_broker'), 'night', ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $promise]);
        $this->expire($room);
        $offers = $this->roomView($room, $recipient)['fae']['bargains'];
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offers[1]['id'], 'accept' => true]);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room); // Missing vote, not an explicit abstention.
        $this->assertSame('broken', $room->state['fae']['bargains'][1]['status']);
        $this->act($room, $this->role($room, 'fae_broker'), 'night', ['target' => $recipient, 'bargain_kind' => 'thorn', 'promise_target' => $promise]);
        $this->expire($room);
        $offers = $this->roomView($room, $recipient)['fae']['bargains'];
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offers[2]['id'], 'accept' => true]);
        $this->expire($room);
        $this->expire($room);
        $s = $room->state;
        $s['players'][$recipient]['curse'] = ['id' => (string) Str::uuid(), 'type' => 'misdirection', 'day' => 3, 'level' => 3, 'challenge' => null];
        $room->update(['state' => $s]);
        $this->act($room, $recipient, 'vote', ['target' => $promise]);
        $this->expire($room);
        $this->assertSame('broken', $room->state['fae']['bargains'][2]['status']);
    }

    public function test_voice_reveals_only_resolved_ballot_after_voting_to_town_or_cult_recipient_even_when_banished(): void
    {
        foreach (['oracle', 'acolyte'] as $role) {
            $room = $this->gathering();
            $recipient = $this->role($room, $role);
            $voter = $this->role($room, 'warden');
            $intended = $this->role($room, 'lamplighter');
            $broker = $this->role($room, 'fae_broker');
            $offer = $this->offer($room, 'voice', $recipient, gift: $voter);
            $view = $this->roomView($room, $recipient);
            $this->assertSame($voter, $view['fae']['bargains'][0]['gift_target']);
            $this->assertArrayNotHasKey('sender_id', $view['fae']['bargains'][0]);
            $this->assertEmpty($this->roomView($room, $voter)['fae']['bargains']);
            $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
            $this->expire($room);
            $this->expire($room);
            $s = $room->state;
            $s['players'][$voter]['curse'] = ['id' => (string) Str::uuid(), 'type' => 'misdirection', 'day' => 1, 'level' => 3, 'challenge' => null];
            $room->update(['state' => $s]);
            $this->act($room, $voter, 'vote', ['target' => $intended]);
            $actual = $room->fresh()->state['actions'][$voter]['target'];
            $this->assertNotSame($intended, $actual);
            $this->assertEmpty($this->roomView($room, $recipient)['me']['results']);
            foreach (array_keys($s['players']) as $id) {
                if ($id !== $voter) {
                    $this->act($room, $id, 'vote', ['target' => $id === $recipient ? null : $recipient]);
                }
            }
            $room->refresh();
            $this->assertFalse($room->state['players'][$recipient]['alive']);
            $expected = ['kind' => 'ballot', 'day' => 1, 'target' => $s['players'][$voter]['name'],
                'submitted' => true, 'voted_for' => $s['players'][$actual]['name']];
            $this->assertSame([$expected], $this->roomView($room, $recipient)['me']['results']);
            foreach (array_keys($s['players']) as $id) {
                if ($id !== $recipient) {
                    $this->assertEmpty(array_filter($this->roomView($room, $id)['me']['results'], fn (array $r): bool => ($r['kind'] ?? null) === 'ballot'));
                }
            }
            $this->assertArrayNotHasKey('voted_for', $this->roomView($room, $broker)['fae']['bargains'][0]);
            $this->assertCount(7, $room->state['rounds'][1]['vote']['ballots']);
            $settled = $room->state;
            $settled['day'] = 1;
            FaeCourt::settle($settled);
            $this->assertSame([$expected], $settled['players'][$recipient]['results']);
        }
    }

    public function test_voice_distinguishes_abstention_from_missed_vote_and_keeps_gift_after_betrayal(): void
    {
        foreach ([true, false] as $submitted) {
            $room = $this->gathering();
            $recipient = $this->role($room, 'oracle');
            $voter = $this->role($room, 'warden');
            $offer = $this->offer($room, 'voice', $recipient, gift: $voter);
            $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
            $this->expire($room);
            $this->expire($room);
            if ($submitted) {
                $this->act($room, $voter, 'vote');
            }
            $this->act($room, $recipient, 'vote', ['target' => $voter]);
            $this->expire($room);
            $this->assertSame('broken', $room->state['fae']['bargains'][0]['status']);
            $this->assertSame(0, $this->roomView($room, $recipient)['fae']['seals']);
            $this->assertSame(['kind' => 'ballot', 'day' => 1, 'target' => $room->state['players'][$voter]['name'],
                'submitted' => $submitted, 'voted_for' => null], $this->roomView($room, $recipient)['me']['results'][0]);
        }
    }

    public function test_voice_without_acceptance_or_without_a_completed_vote_delivers_no_report(): void
    {
        foreach (['declined', 'expired', 'void'] as $status) {
            $room = $this->gathering();
            $recipient = $this->role($room, 'oracle');
            $offer = $this->offer($room, 'voice', $recipient);
            if ($status !== 'expired') {
                $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => $status === 'void']);
            }
            if ($status === 'void') {
                $s = $room->fresh()->state;
                $s['winner'] = 'town';
                $s['win_reason'] = 'The match ended before voting.';
                FaeCourt::finish($s);
            } else {
                $this->expire($room);
                $this->expire($room);
                $this->act($room, $recipient, 'vote');
                $this->expire($room);
                $s = $room->state;
            }
            $this->assertSame($status, $s['fae']['bargains'][0]['status']);
            $this->assertEmpty($s['players'][$recipient]['results']);
        }
    }

    public function test_voice_requires_valid_ballot_target_and_voids_if_that_player_dies_at_night(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $broker = $this->role($room, 'fae_broker');
        $voter = $this->role($room, 'warden');
        foreach ([null, $recipient, (string) Str::uuid()] as $gift) {
            $this->reject(fn () => $this->act($room, $broker, 'night', ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $gift]));
        }
        $this->reject(fn () => $this->act($room, $broker, 'night', ['gift_target' => $voter]));
        $this->reject(fn () => $this->act($room, $recipient, 'night', ['gift_target' => $voter]));
        $this->reject(fn () => $this->act($room, $broker, 'night', ['target' => $recipient, 'bargain_kind' => 'thorn', 'promise_target' => $voter, 'gift_target' => $voter]));
        $s = $room->state;
        $s['players'][$voter]['alive'] = false;
        $this->reject(fn () => FaeCourt::validateOffer($s, $broker, ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $voter]));
        $this->act($room, $broker, 'night', ['target' => $recipient, 'bargain_kind' => 'voice', 'gift_target' => $voter]);
        $s = $room->fresh()->state;
        $s['players'][$voter]['alive'] = false;
        $room->update(['state' => $s]);
        $this->expire($room);
        $this->assertSame('void', $room->state['fae']['bargains'][0]['status']);
        $this->reject(fn () => $this->act($room, $recipient, 'fae_response', ['bargain_id' => $room->state['fae']['bargains'][0]['id'], 'accept' => true]));
    }

    public function test_http_voice_offer_preserves_the_selected_ballot_target(): void
    {
        $room = $this->gathering();
        $broker = $this->role($room, 'fae_broker');
        $recipient = $this->role($room, 'oracle');
        $voter = $this->role($room, 'warden');
        if ($room->state['players'][$broker]['user_id'] !== null) {
            $this->actingAs($this->owner);
        }
        $fields = ['type' => 'night', 'phase_id' => $room->state['phase_id'], 'target' => $recipient, 'bargain_kind' => 'voice'];
        $this->withSession(['chanting.identity' => $this->identities[$broker]])
            ->postJson('/rooms/'.$room->code.'/actions', [...$fields, 'gift_target' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('gift_target');
        $this->postJson('/rooms/'.$room->code.'/actions', [...$fields, 'gift_target' => $voter])->assertOk();
        $this->assertSame($voter, $room->fresh()->state['actions'][$broker]['gift_target']);
    }

    public function test_previously_issued_voice_offer_keeps_its_original_cleansing_gift(): void
    {
        $room = $this->gathering();
        $recipient = $this->role($room, 'oracle');
        $this->act($room, $this->role($room, 'veilweaver'), 'night', ['target' => $recipient]);
        $offer = $this->offer($room, 'voice', $recipient);
        $s = $room->state;
        unset($s['fae']['bargains'][0]['gift_target']);
        $s['players'][$recipient]['haunting'] = ['day' => 1, 'seat_id' => $recipient];
        $room->update(['state' => $s]);
        $this->act($room, $recipient, 'fae_response', ['bargain_id' => $offer, 'accept' => true]);
        $this->assertNull($room->fresh()->state['players'][$recipient]['curse']);
        $this->assertNull($room->fresh()->state['players'][$recipient]['haunting']);
        $this->assertEmpty($room->state['players'][$recipient]['results']);
    }

    public function test_guest_http_cannot_inject_a_paid_faction_or_offer_as_town(): void
    {
        $this->postJson('/rooms', ['name' => 'Intruder', 'setup' => ['fae_court' => true]])->assertUnprocessable();
        $this->postJson('/rooms', ['name' => 'Intruder', 'setup' => ['mode' => 'custom', 'roles' => ['fae_broker' => 1, 'oracle' => 4, 'acolyte' => 2]]])->assertUnprocessable();
        $room = $this->gathering();
        $this->reject(fn () => $this->act($room, $this->role($room, 'oracle'), 'night', ['target' => $this->role($room, 'warden'), 'bargain_kind' => 'voice']));
        $this->reject(fn () => $this->act($room, $this->role($room, 'fae_broker'), 'night', ['target' => $this->role($room, 'oracle'), 'bargain_kind' => 'thorn', 'promise_target' => $this->role($room, 'oracle')]));
    }
}
