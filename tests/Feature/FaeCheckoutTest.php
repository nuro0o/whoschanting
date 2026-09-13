<?php

namespace Tests\Feature;

use App\Game\FactionExpansions;
use App\Game\FaeCourt;
use App\Game\PaidCosmetics;
use App\Game\PurchasePolicy;
use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FaeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['factions.fae-court.active' => true, 'inertia.ssr.enabled' => false]);
        foreach (FactionExpansions::ADDITIONAL as $id) {
            config(['factions.'.$id.'.active' => false]);
        }
    }

    public function test_checkout_grants_room_access_only_after_verified_payment_and_refund_revokes_it(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $bundles = config('payments.bundles');
        $index = array_search('fae-court', array_column($bundles, 'id'), true);
        config(['payments.secret_key' => 'sk_test_fae', 'payments.webhook_secret' => 'whsec_fae',
            'payments.bundles.'.$index.'.price_id' => 'price_fae', 'payments.bundles.'.$index.'.amount' => 299]);
        $charge = ['id' => 'ch_fae', 'paid' => true, 'refunded' => false, 'disputed' => false];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$charge) {
            if (str_contains($request->url(), '/prices/')) {
                return Http::response(['active' => true, 'type' => 'one_time', 'unit_amount' => 299, 'currency' => 'eur']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_fae', 'url' => 'https://checkout.stripe.com/c/pay/fae']);
            }
            $order = PaidOrder::firstOrFail();

            return Http::response(['id' => 'cs_test_fae', 'mode' => 'payment', 'client_reference_id' => $order->id,
                'metadata' => $order->checkout_parameters['metadata'], 'payment_status' => 'paid', 'status' => 'complete',
                'currency' => 'eur', 'amount_subtotal' => 299, 'amount_total' => 299,
                'payment_intent' => ['id' => 'pi_fae', 'status' => 'succeeded', 'metadata' => $order->checkout_parameters['metadata'], 'latest_charge' => $charge],
                'line_items' => ['data' => [['price' => ['id' => 'price_fae'], 'quantity' => 1]]]]);
        });
        $this->actingAs($user)->postJson('/account/store/checkout', ['bundle_id' => 'fae-court', 'amount' => 1,
            'price_id' => 'price_fake', 'terms' => true, 'terms_version' => config('legal.version'),
            'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])
            ->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/fae');
        $order = PaidOrder::firstOrFail();
        $this->assertSame(299, $order->amount);
        $this->assertSame('price_fae', $order->price_id);
        $this->assertSame([['category' => 'characters', 'id' => 'fae_envoy', 'name' => 'The Thorn Envoy']], $order->cosmetics);
        $room = ['players' => [['user_id' => $user->id]]];
        $this->assertFalse(FaeCourt::available($room));
        config(['factions.fae-court.active' => false]);
        $this->getJson('/account/store/status?session_id=cs_test_fae')->assertOk()->assertJsonPath('status', 'paid');
        $this->assertFalse(FaeCourt::available($room));
        config(['factions.fae-court.active' => true]);
        $this->assertTrue(FaeCourt::available($room));
        $bundle = collect((new PaidCosmetics)->view($user->id))->firstWhere('id', 'fae-court');
        $this->assertTrue($bundle['owned']);
        $this->assertSame('faction', $bundle['kind']);
        $this->assertSame($order->cosmetics, (new PaidCosmetics)->ownedCosmetics($user->id));
        $this->assertDatabaseCount('coin_transactions', 0);
        $charge['refunded'] = true;
        $this->getJson('/account/store/status?session_id=cs_test_fae')->assertOk()->assertJsonPath('status', 'refunded');
        $this->assertFalse(FaeCourt::available($room));
    }

    public function test_missing_stripe_price_keeps_faction_unavailable_for_purchase(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $bundles = config('payments.bundles');
        $index = array_search('fae-court', array_column($bundles, 'id'), true);
        config(['payments.bundles.'.$index.'.price_id' => null]);
        $bundle = collect((new PaidCosmetics)->view($user->id))->firstWhere('id', 'fae-court');
        $this->assertFalse($bundle['available']);
        $this->actingAs($user)->postJson('/account/store/checkout', ['bundle_id' => 'fae-court',
            'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true,
            'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
        $this->assertDatabaseCount('paid_orders', 0);
        Http::assertNothingSent();
    }

    public function test_inactive_faction_is_hidden_and_cannot_be_purchased_even_with_stripe_configured(): void
    {
        $user = User::factory()->create();
        $index = array_search('fae-court', array_column(config('payments.bundles'), 'id'), true);
        config(['factions.fae-court.active' => false, 'payments.secret_key' => 'sk_test_fae',
            'payments.webhook_secret' => 'whsec_fae', 'payments.bundles.'.$index.'.price_id' => 'price_fae']);
        Http::preventStrayRequests();
        $this->assertNull(collect((new PaidCosmetics)->view($user->id))->firstWhere('id', 'fae-court'));
        $this->assertNotNull(collect((new PaidCosmetics)->view($user->id))->firstWhere('id', 'founders-pack'));
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('activeFactions', []));
        $this->actingAs($user)->postJson('/account/store/checkout', ['bundle_id' => 'fae-court',
            'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true,
            'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
        $this->assertDatabaseCount('paid_orders', 0);
        Http::assertNothingSent();
        config(['factions.fae-court.active' => true]);
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('activeFactions', ['fae-court']));
        $this->assertTrue(collect((new PaidCosmetics)->view($user->id))->firstWhere('id', 'fae-court')['available']);
    }
}
