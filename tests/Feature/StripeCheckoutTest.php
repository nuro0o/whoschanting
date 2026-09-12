<?php

namespace Tests\Feature;

use App\Game\AccountProgression;
use App\Game\PaidCosmetics;
use App\Game\PurchasePolicy;
use App\Models\PaidOrder;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private array $stripeSession = [];

    private array $stripeIntent = [];

    private bool $failCheckout = false;

    private int $stripePriceAmount = 799;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['payments.secret_key' => 'sk_test_example', 'payments.webhook_secret' => 'whsec_example',
            'payments.bundles.0.price_id' => 'price_founders']);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/prices/')) {
                return Http::response(['active' => true, 'type' => 'one_time', 'unit_amount' => $this->stripePriceAmount, 'currency' => 'eur']);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/checkout/sessions')) {
                if ($this->failCheckout) {
                    return Http::response(['error' => ['message' => 'Temporary outage']], 503);
                }

                return Http::response(['id' => 'cs_test_founder', 'url' => 'https://checkout.stripe.com/c/pay/test']);
            }
            if (str_contains($request->url(), '/checkout/sessions/cs_')) {
                return Http::response($this->stripeSession);
            }
            if (str_contains($request->url(), '/payment_intents/')) {
                return Http::response($this->stripeIntent);
            }

            throw new \RuntimeException('Unexpected Stripe request: '.$request->url());
        });
    }

    private function checkout(?User $user = null): PaidOrder
    {
        $this->actingAs($user ?? User::factory()->create())->postJson('/account/store/checkout', [
            'bundle_id' => 'founders-pack', 'price_id' => 'price_fake', 'amount' => 1, 'user_id' => 999,
            'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION,
        ])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/test');
        $order = PaidOrder::firstOrFail();
        $this->stripeIntent = ['id' => 'pi_founder', 'status' => 'succeeded', 'metadata' => $order->checkout_parameters['metadata'],
            'latest_charge' => ['id' => 'ch_founder', 'paid' => true, 'refunded' => false, 'disputed' => false]];
        $this->stripeSession = ['id' => 'cs_test_founder', 'mode' => 'payment', 'client_reference_id' => $order->id,
            'metadata' => $order->checkout_parameters['metadata'], 'payment_status' => 'paid', 'status' => 'complete',
            'currency' => 'eur', 'amount_subtotal' => 799, 'amount_total' => 799,
            'payment_intent' => $this->stripeIntent,
            'line_items' => ['data' => [['price' => ['id' => 'price_founders'], 'quantity' => 1]]]];

        return $order;
    }

    private function webhook(string $type = 'checkout.session.completed', ?array $object = null, int $age = 0): TestResponse
    {
        $body = json_encode(['id' => 'evt_example', 'type' => $type, 'data' => ['object' => $object ?? $this->stripeSession]], JSON_THROW_ON_ERROR);
        $timestamp = time() - $age;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body);
    }

    public function test_checkout_requires_verified_account_valid_bundle_and_complete_configuration(): void
    {
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/account/store/checkout', ['bundle_id' => 'unknown'])->assertUnprocessable();
        config(['payments.webhook_secret' => null]);
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable();
        $this->assertDatabaseCount('paid_orders', 0);
        Http::assertNothingSent();
    }

    public function test_server_sets_prices_and_metadata_and_checkout_does_not_grant_ownership(): void
    {
        $order = $this->checkout();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['line_items'][0]['price'] === 'price_founders' && $request['line_items'][0]['quantity'] === 1
            && $request['mode'] === 'payment' && $request['metadata']['source_app'] === 'whoschanting'
            && $request['client_reference_id'] === $order->id
            && $request->hasHeader('Idempotency-Key', 'whoschanting-checkout:'.$order->id));
        $this->assertSame(799, $order->amount);
        $this->assertSame('pending', $order->status);
        $this->assertSame(config('legal.version'), $order->legal_acceptance['terms_version']);
        $this->assertFalse($order->legal_acceptance['withdrawal_waived']);
        $this->assertNotEmpty($order->legal_acceptance['accepted_at']);
        $this->assertFalse((new PaidCosmetics)->view($order->user_id)[0]['owned']);
        $this->get('/settings/profile?checkout=success&session_id=cs_test_founder')->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function test_paid_signed_webhook_unlocks_once_and_can_equip_all_bundle_cosmetics(): void
    {
        $order = $this->checkout();
        $this->webhook()->assertOk();
        $this->webhook()->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->receipt_queued_at);
        $this->assertNull($order->fresh()->receipt_sent_at);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(799, $order->fresh()->total_amount);
        $this->assertCount(7, (new PaidCosmetics)->ownedCosmetics($order->user_id));
        $this->postJson('/account/customization', ['title' => 'founder', 'frame' => 'founder', 'accent' => 'founder_gold',
            'background' => 'founders_hall', 'table' => 'founders_oak', 'banishment' => 'gilded_vortex', 'celebration' => 'crownfall'])
            ->assertOk()->assertJsonPath('progression.profile.equipped.table', 'founders_oak');
        $appearance = app(AccountProgression::class)->appearance($order->user_id);
        $this->assertSame('Village Founder', $appearance['title_name']);
        $this->assertSame('crownfall', $appearance['celebration']);
        $this->assertArrayNotHasKey('user_id', $appearance);
        $this->assertSame(0, PlayerProfile::findOrFail($order->user_id)->coins);
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable();
        $this->assertDatabaseCount('paid_orders', 1);
    }

    public function test_unpaid_and_delayed_payments_do_not_unlock_until_confirmed(): void
    {
        $order = $this->checkout();
        $this->stripeSession['payment_status'] = 'unpaid';
        $this->stripeSession['payment_intent']['status'] = 'processing';
        $this->webhook()->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertUnprocessable();
        $this->stripeSession['payment_status'] = 'paid';
        $this->stripeSession['payment_intent']['status'] = 'succeeded';
        $this->webhook('checkout.session.async_payment_succeeded')->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_bad_signatures_stale_events_and_other_app_events_cannot_grant(): void
    {
        $order = $this->checkout();
        $this->postJson('/stripe/webhook', ['id' => 'evt_fake'])->assertBadRequest();
        $this->webhook(age: 301)->assertBadRequest();
        $foreign = $this->stripeSession;
        $foreign['metadata']['source_app'] = 'geniousverse';
        $this->webhook(object: $foreign)->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_return_status_is_private_and_can_recover_a_delayed_webhook(): void
    {
        $order = $this->checkout();
        $this->actingAs(User::factory()->create())->getJson('/account/store/status?session_id=cs_test_founder')->assertNotFound();
        $response = $this->actingAs(User::findOrFail($order->user_id))->getJson('/account/store/status?session_id=cs_test_founder')
            ->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('progression.store.bundles.0.owned', true);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_mismatched_price_or_session_identity_never_grants(): void
    {
        $order = $this->checkout();
        $this->stripeSession['line_items']['data'][0]['price']['id'] = 'price_wrong';
        $this->webhook()->assertStatus(500);
        $this->assertSame('pending', $order->fresh()->status);
        $this->stripeSession['line_items']['data'][0]['price']['id'] = 'price_founders';
        $this->stripeSession['client_reference_id'] = 'someone-else';
        $this->webhook()->assertStatus(500);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_open_session_is_reused_instead_of_charging_twice(): void
    {
        $this->checkout();
        $this->stripeSession['payment_status'] = 'unpaid';
        $this->stripeSession['status'] = 'open';
        $this->stripeSession['url'] = 'https://checkout.stripe.com/c/pay/existing';
        $this->stripeSession['payment_intent'] = null;
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertOk()
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/existing');
        $this->assertDatabaseCount('paid_orders', 1);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
    }

    public function test_full_refund_revokes_equipped_items_and_late_payment_events_do_not_restore_them(): void
    {
        $order = $this->checkout();
        $this->webhook()->assertOk();
        $this->postJson('/account/customization', ['title' => 'founder', 'frame' => 'founder', 'accent' => 'founder_gold',
            'table' => 'founders_oak', 'banishment' => 'gilded_vortex', 'celebration' => 'crownfall'])->assertOk();
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_founder', 'refunded' => true])->assertOk();
        $this->webhook()->assertOk();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertFalse((new PaidCosmetics)->view($order->user_id)[0]['owned']);
        $appearance = app(AccountProgression::class)->appearance($order->user_id);
        $this->assertSame('plain', $appearance['frame']);
        $this->assertSame('classic', $appearance['table']);
        $this->assertSame('classic', $appearance['celebration']);
        $this->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'table' => 'founders_oak'])
            ->assertUnprocessable()->assertJsonValidationErrors('table');
    }

    public function test_refund_before_completion_and_dispute_before_completion_cannot_be_overwritten(): void
    {
        $order = $this->checkout();
        $this->webhook('charge.dispute.created', ['payment_intent' => 'pi_founder'])->assertOk();
        $this->webhook()->assertOk();
        $this->assertSame('disputed', $order->fresh()->status);
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_founder', 'refunded' => true])->assertOk();
        $this->webhook()->assertOk();
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_partial_refund_preserves_owned_bundle(): void
    {
        $order = $this->checkout();
        $this->webhook()->assertOk();
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_founder', 'refunded' => false])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_retiring_bundle_preserves_paid_ownership_but_not_refunded_appearance(): void
    {
        $order = $this->checkout();
        $this->webhook()->assertOk();
        $this->postJson('/account/customization', ['title' => 'founder', 'frame' => 'founder', 'accent' => 'founder_gold', 'table' => 'founders_oak'])->assertOk();
        config(['payments.bundles' => []]);
        $this->assertSame('founders_oak', app(AccountProgression::class)->appearance($order->user_id)['table']);
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_founder', 'refunded' => true])->assertOk();
        $this->assertSame('classic', app(AccountProgression::class)->appearance($order->user_id)['table']);
    }

    public function test_foreign_account_and_earned_crowns_cannot_unlock_paid_cosmetics(): void
    {
        $this->checkout();
        $this->webhook()->assertOk();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'celebration' => 'crownfall'])
            ->assertUnprocessable()->assertJsonValidationErrors('celebration');
        $this->postJson('/account/store/purchase', ['item_id' => 'founders-pack'])->assertUnprocessable();
        $this->assertFalse((new PaidCosmetics)->view($other->id)[0]['owned']);
    }

    public function test_stripe_price_mismatch_disables_checkout_before_creating_an_order(): void
    {
        $this->stripePriceAmount = 1;
        $this->actingAs(User::factory()->create())->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])
            ->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
        $this->assertDatabaseCount('paid_orders', 0);
    }

    public function test_failed_session_creation_retries_the_same_order_parameters_and_idempotency_key(): void
    {
        $this->failCheckout = true;
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertStatus(503);
        $order = PaidOrder::firstOrFail();
        $this->assertNull($order->stripe_session_id);
        $parameters = $order->checkout_parameters;
        $this->failCheckout = false;
        $user->update(['email' => 'changed@example.test']);
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])->assertOk();
        $this->assertDatabaseCount('paid_orders', 1);
        $this->assertSame($parameters, $order->fresh()->checkout_parameters);
        $requests = Http::recorded(fn (Request $request): bool => $request->method() === 'POST')->values();
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0][0]->data(), $requests[1][0]->data());
        $this->assertSame($requests[0][0]->header('Idempotency-Key'), $requests[1][0]->header('Idempotency-Key'));
    }

    public function test_expired_and_failed_sessions_do_not_unlock_and_late_failure_does_not_revoke_paid_order(): void
    {
        $order = $this->checkout();
        $paid = $this->stripeSession;
        $this->stripeSession['payment_status'] = 'unpaid';
        $this->stripeSession['status'] = 'expired';
        $this->stripeSession['payment_intent'] = null;
        $this->webhook('checkout.session.expired')->assertOk();
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertFalse((new PaidCosmetics)->view($order->user_id)[0]['owned']);
        $this->stripeSession = $paid;
        $this->webhook()->assertOk();
        $this->webhook('checkout.session.async_payment_failed')->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }
}
