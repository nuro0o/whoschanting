<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\FactionExpansions;
use App\Game\MatchEngine;
use App\Game\PaidCosmetics;
use App\Game\PaidPackUsage;
use App\Game\PurchaseReceipts;
use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpansionCharactersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
    }

    public static function expansions(): array
    {
        return [
            ['fae-court', 'fae_envoy', 'The Thorn Envoy'],
            ['drowned', 'drowned_diver', 'The Deep Diver'],
            ['gilded-hand', 'relic_broker', 'The Relic Broker'],
            ['hollow-choir', 'hollow_cantor', 'The Hollow Cantor'],
            ['carnival', 'carnival_ringmaster', 'The Ringmaster'],
        ];
    }

    private function order(User $owner, string $bundleId, bool $legacy = false): PaidOrder
    {
        $bundle = collect(config('payments.bundles'))->firstWhere('id', $bundleId);

        return PaidOrder::create(['id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'bundle_id' => $bundleId, 'bundle_name' => $bundle['name'], 'price_id' => 'price_test',
            'amount' => $bundle['amount'], 'currency' => 'eur', 'status' => 'paid', 'paid_at' => now(),
            'cosmetics' => $legacy ? [] : $bundle['cosmetics'], 'checkout_parameters' => []]);
    }

    #[DataProvider('expansions')]
    public function test_each_expansion_has_a_visible_locked_character_and_unlocks_only_for_its_owner(string $bundleId, string $characterId, string $name): void
    {
        $progression = new AccountProgression;
        $owner = User::factory()->create();
        $other = User::factory()->create();
        config(['factions.'.$bundleId.'.active' => true]);
        $guestCharacter = collect($progression->characterCatalog())->firstWhere('id', $characterId);
        $this->assertSame($name, $guestCharacter['name']);
        $this->assertSame('expansion', $guestCharacter['collection']);
        $this->assertSame($bundleId, $guestCharacter['expansion']);
        $this->assertFalse($guestCharacter['unlocked']);
        $this->assertFalse($guestCharacter['hidden'] ?? false);
        $this->assertNotContains($characterId, $progression->starterCharacterIds());
        $this->assertFalse($progression->canUseCharacter($owner->id, $characterId));

        $order = $this->order($owner, $bundleId);
        $bundle = collect((new PaidCosmetics)->view($owner->id))->firstWhere('id', $bundleId);
        $this->assertSame([['category' => 'characters', 'id' => $characterId, 'name' => $name]], $bundle['cosmetics']);
        $this->assertTrue($bundle['owned']);
        $this->assertTrue($progression->canUseCharacter($owner->id, $characterId));
        $this->assertFalse($progression->canUseCharacter($other->id, $characterId));
        foreach (self::expansions() as [$otherBundle, $otherCharacter]) {
            $this->assertSame($otherBundle === $bundleId, $progression->canUseCharacter($owner->id, $otherCharacter));
        }
        // Sharing the rules with a room does not share the purchaser's portrait.
        $state = ['players' => [['user_id' => $owner->id], ['user_id' => $other->id]]];
        $this->assertTrue(FactionExpansions::available($state, $bundleId));
        $this->actingAs($other)->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => $characterId])
            ->assertUnprocessable()->assertJsonValidationErrors('character');
        $this->actingAs($owner)->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => $characterId])->assertOk();
        $this->assertSame($characterId, $progression->preferredCharacter($owner->id));

        $owner->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($progression->canUseCharacter($owner->id, $characterId));
        $this->assertNull($progression->preferredCharacter($owner->id));
        $this->assertSame($order->cosmetics, $order->fresh()->cosmetics);
    }

    #[DataProvider('expansions')]
    public function test_existing_owners_receive_characters_without_rewriting_orders_even_when_expansion_is_paused(string $bundleId, string $characterId, string $name): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, $bundleId, legacy: true);
        config(['factions.'.$bundleId.'.active' => false]);
        $this->assertTrue((new AccountProgression)->canUseCharacter($owner->id, $characterId));
        $this->assertSame([['category' => 'characters', 'id' => $characterId, 'name' => $name]], (new PaidCosmetics)->ownedCosmetics($owner->id));
        $this->assertSame([], $order->fresh()->cosmetics);
        foreach (['refunded', 'disputed', 'pending', 'expired'] as $status) {
            $order->update(['status' => $status]);
            $this->assertFalse((new AccountProgression)->canUseCharacter($owner->id, $characterId), $status);
        }
    }

    public function test_portrait_use_counts_only_when_a_real_match_starts_and_legacy_orders_are_supported(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'drowned', legacy: true);
        (new AccountProgression)->rememberCharacter($owner->id, 'drowned_diver');
        $engine = app(MatchEngine::class);
        $room = $engine->create('owner', 'Owner', 'drowned_diver', accountId: $owner->id);
        for ($i = 1; $i < 5; $i++) {
            $engine->join($room->code, 'guest-'.$i, 'Guest '.$i);
        }
        $this->assertDatabaseCount('paid_pack_usages', 0);
        $act = fn (string $identity, string $type, ?int $userId = null): array => $engine->access($room->code, $identity,
            ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id']], $userId);
        $act('owner', 'ready', $owner->id);
        for ($i = 1; $i < 5; $i++) {
            $act('guest-'.$i, 'ready');
        }
        $view = $act('owner', 'start', $owner->id);
        $this->assertSame('drowned_diver', $view['me']['character']);
        $this->assertDatabaseCount('paid_pack_usages', 1);
        $this->assertDatabaseHas('paid_pack_usages', ['paid_order_id' => $order->id, 'cosmetics' => '["characters:drowned_diver"]']);
        $this->assertArrayNotHasKey('paid_cosmetic_orders', $view);
        $state = $room->fresh()->state;
        DB::transaction(fn () => (new PaidPackUsage)->record($state, $state['host_id'], 'character'));
        $this->assertDatabaseCount('paid_pack_usages', 1);
    }

    public function test_refunded_lobby_portrait_falls_back_before_match_start(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'fae-court', legacy: true);
        $progression = new AccountProgression;
        $progression->rememberCharacter($owner->id, 'fae_envoy');
        $engine = app(MatchEngine::class);
        $room = $engine->create('owner', 'Owner', 'fae_envoy', accountId: $owner->id);
        for ($i = 1; $i < 5; $i++) {
            $engine->join($room->code, 'guest-'.$i, 'Guest '.$i);
        }
        $order->update(['status' => 'refunded']);
        $this->assertNull($progression->preferredCharacter($owner->id));
        $this->assertNull($progression->view($owner->id)['profile']['equipped']['character']);
        $view = $engine->access($room->code, 'owner', accountId: $owner->id);
        $this->assertContains($view['me']['character'], $progression->starterCharacterIds());
        $act = fn (string $identity, string $type, ?int $userId = null): array => $engine->access($room->code, $identity,
            ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id']], $userId);
        $act('owner', 'ready', $owner->id);
        for ($i = 1; $i < 5; $i++) {
            $act('guest-'.$i, 'ready');
        }
        $act('owner', 'start', $owner->id);
        $state = $room->fresh()->state;
        $this->assertContains($state['players'][$state['host_id']]['character'], $progression->starterCharacterIds());
        $this->assertDatabaseCount('paid_pack_usages', 0);
    }

    public function test_character_and_shared_expansion_in_same_match_count_as_one_use(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'drowned', legacy: true);
        $state = ['match_id' => (string) Str::uuid(), 'host_id' => 'owner', 'expansion' => ['id' => 'drowned'],
            'players' => ['owner' => ['user_id' => $owner->id, 'character' => 'drowned_diver']]];
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertDatabaseCount('paid_pack_usages', 1);
        $usage = DB::table('paid_pack_usages')->where('paid_order_id', $order->id)->first();
        $this->assertEqualsCanonicalizing(['characters:drowned_diver', 'expansions:drowned'], json_decode($usage->cosmetics, true));
    }

    public function test_new_receipts_include_both_expansion_and_portrait_while_old_receipts_keep_their_purchase_contents(): void
    {
        $owner = User::factory()->create();
        $new = $this->order($owner, 'carnival');
        (new PurchaseReceipts)->queue($new);
        $this->assertSame(['The Carnival', 'The Ringmaster'], $new->fresh()->receipt_payload['items']);
        $old = $this->order($owner, 'hollow-choir', legacy: true);
        (new PurchaseReceipts)->queue($old);
        $this->assertSame(['The Hollow Choir'], $old->fresh()->receipt_payload['items']);
        $this->assertTrue((new AccountProgression)->canUseCharacter($owner->id, 'hollow_cantor'));
        $this->assertSame([], $old->fresh()->cosmetics);
    }
}
