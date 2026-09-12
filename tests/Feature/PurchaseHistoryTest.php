<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchCosmetics;
use App\Game\MatchEngine;
use App\Game\PaidPackUsage;
use App\Game\PurchasePolicy;
use App\Game\PurchaseReceipts;
use App\Jobs\SendPurchaseReceipt;
use App\Mail\PurchaseReceipt;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class PurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        Event::fake([RoomUpdated::class]);
    }

    private function order(?User $user = null, array $overrides = []): PaidOrder
    {
        $user ??= User::factory()->create();

        return PaidOrder::create([...[
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'bundle_id' => 'founders-pack',
            'bundle_name' => "Founder's Pack", 'price_id' => 'price_test', 'amount' => 799, 'total_amount' => 967,
            'currency' => 'eur', 'status' => 'paid', 'paid_at' => now(), 'cosmetics' => config('payments.bundles.0.cosmetics'),
            'checkout_parameters' => ['customer_email' => $user->email],
            'legal_acceptance' => ['terms_version' => config('legal.version'), 'purchase_policy_version' => PurchasePolicy::VERSION,
                'digital_content_consent' => true, 'digital_content_consent_text' => PurchasePolicy::CONSENT, 'accepted_at' => now()->toISOString()],
        ], ...$overrides]);
    }

    private function state(PaidOrder $order, array $appearance = []): array
    {
        return ['match_id' => (string) Str::uuid(), 'phase' => 'reveal', 'host_id' => 'owner',
            'players' => ['owner' => ['user_id' => $order->user_id, 'customization' => $appearance, 'alignment' => 'town']]];
    }

    public function test_digital_consent_and_current_policy_are_required_separately_before_stripe(): void
    {
        $this->actingAs(User::factory()->create());
        $input = ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => config('legal.version')];
        $this->postJson('/account/store/checkout', $input)->assertUnprocessable()->assertJsonValidationErrors(['digital_content_consent', 'purchase_policy_version']);
        $this->postJson('/account/store/checkout', [...$input, 'digital_content_consent' => false, 'purchase_policy_version' => PurchasePolicy::VERSION])
            ->assertUnprocessable()->assertJsonValidationErrors('digital_content_consent');
        $this->postJson('/account/store/checkout', [...$input, 'digital_content_consent' => true, 'purchase_policy_version' => 'stale'])
            ->assertUnprocessable()->assertJsonValidationErrors('purchase_policy_version');
        Http::assertNothingSent();
        $this->assertDatabaseCount('paid_orders', 0);
    }

    public function test_purchase_history_is_private_paginated_and_includes_unverified_owners(): void
    {
        $owner = User::factory()->unverified()->create();
        $this->get('/account/purchases')->assertRedirect('/login');
        $other = $this->order();
        $mine = $this->order($owner);
        $response = $this->actingAs($owner)->get('/account/purchases')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Purchases')->has('purchases.data', 1)->where('purchases.data.0.id', $mine->id)
            ->where('purchases.data.0.total_amount', 967)->where('purchases.data.0.games_used', 0)
            ->where('purchases.data.0.refund.status', 'eligible')->where('purchases.data.0.refund.can_request', true)->missing('purchases.data.0.checkout_parameters')
            ->missing('purchases.data.0.price_id')->missing('purchases.data.0.legal_acceptance'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($other->id, $response->getContent());
        for ($i = 0; $i < 12; $i++) {
            $this->order($owner, ['status' => 'refunded']);
        }
        $this->get('/account/purchases')->assertInertia(fn (Assert $page) => $page->has('purchases.data', 12)->where('purchases.last_page', 2));
        $this->get('/account/purchases?page=2')->assertInertia(fn (Assert $page) => $page->has('purchases.data', 1));
    }

    public function test_real_match_start_records_use_once_and_does_not_expose_order_provenance(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $this->actingAs($owner)->postJson('/account/customization', ['title' => 'founder', 'frame' => 'plain', 'accent' => 'sea'])
            ->assertOk();
        $engine = app(MatchEngine::class);
        $room = $engine->create('owner', 'Owner', accountId: $owner->id);
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
        $started = $act('owner', 'start', $owner->id);
        $firstUse = (new PurchasePolicy)->usage($order);
        $this->assertSame(1, $firstUse['games_used']);
        $this->assertArrayNotHasKey('paid_cosmetic_orders', $started);
        $this->assertStringNotContainsString($order->id, json_encode($started));
        for ($i = 0; $i < 3; $i++) {
            $engine->access($room->code, 'owner', accountId: $owner->id);
        }
        $this->assertSame($firstUse, (new PurchasePolicy)->usage($order));
        $state = $room->fresh()->state;
        $state['phase'] = 'finished';
        $room->update(['state' => $state, 'deadline' => null]);
        $act('owner', 'rematch', $owner->id);
        $act('owner', 'ready', $owner->id);
        for ($i = 1; $i < 5; $i++) {
            $act('guest-'.$i, 'ready');
        }
        $act('owner', 'start', $owner->id);
        $this->assertSame(2, (new PurchasePolicy)->usage($order)['games_used']);
    }

    public function test_only_host_table_and_triggered_animations_count_and_repurchase_cannot_inherit_old_use(): void
    {
        $order = $this->order();
        $state = $this->state($order, ['table' => 'founders_oak', 'banishment' => 'gilded_vortex', 'celebration' => 'crownfall']);
        $state['host_id'] = 'guest';
        $state['players']['guest'] = ['customization' => [], 'alignment' => 'cult'];
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertDatabaseCount('paid_pack_usages', 0);
        DB::transaction(function () use (&$state): void {
            MatchCosmetics::banish($state, 'owner');
        });
        $state['winner'] = 'town';
        DB::transaction(function () use (&$state): void {
            MatchCosmetics::celebrate($state);
        });
        $this->assertSame(1, (new PurchasePolicy)->usage($order)['games_used']);
        $this->assertCount(2, json_decode(DB::table('paid_pack_usages')->first()->cosmetics, true));
        $order->update(['status' => 'refunded']);
        $repurchase = $this->order(User::findOrFail($order->user_id));
        DB::transaction(function () use (&$state): void {
            MatchCosmetics::banish($state, 'owner');
        });
        $this->assertSame(0, (new PurchasePolicy)->usage($repurchase)['games_used']);
        $state['match_id'] = (string) Str::uuid();
        $state['host_id'] = 'owner';
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertSame(1, (new PurchasePolicy)->usage($repurchase)['games_used']);
    }

    public function test_two_game_refund_limit_and_window_come_from_server_usage(): void
    {
        $policy = new PurchasePolicy;
        $order = $this->order();
        $this->actingAs(User::findOrFail($order->user_id));
        for ($games = 0; $games <= 3; $games++) {
            if ($games > 0) {
                $state = $this->state($order, ['frame' => 'founder']);
                DB::transaction(function () use (&$state): void {
                    (new PaidPackUsage)->start($state);
                });
            }
            $this->assertSame($games <= 2, $policy->refund($order)['can_request']);
            $this->get('/account/purchases?games_used=0')->assertInertia(fn (Assert $page) => $page
                ->where('purchases.data.0.games_used', $games)
                ->where('purchases.data.0.refund.can_request', $games <= 2));
        }
        $this->assertSame('Two-game refund limit exceeded', $policy->refund($order)['label']);
        $this->assertStringContainsString('statutory', $policy->refund($order)['explanation']);
        $legacy = $this->order(overrides: ['legal_acceptance' => ['withdrawal_waived' => false]]);
        $this->assertTrue($policy->refund($legacy)['can_request']);
        $this->assertSame(['withdrawal_waived' => false], $legacy->fresh()->legal_acceptance);
        $expired = $this->order(overrides: ['paid_at' => now()->subDays(15)]);
        $this->assertFalse($policy->refund($expired)['can_request']);
        $this->assertSame('Refund window ended', $policy->refund($expired)['label']);
        foreach (['pending', 'refunded', 'disputed', 'failed', 'expired'] as $status) {
            $this->assertFalse($policy->refund($this->order(overrides: ['status' => $status]))['can_request']);
        }
        $this->assertNull($order->fresh()->receipt_sent_at);
    }

    public function test_expansion_usage_counts_only_the_purchase_enabling_the_match(): void
    {
        $host = $this->order(overrides: ['bundle_id' => 'fae-court', 'cosmetics' => []]);
        $other = $this->order(overrides: ['bundle_id' => 'fae-court', 'cosmetics' => []]);
        $state = $this->state($host);
        $state['players']['other'] = ['user_id' => $other->user_id, 'customization' => []];
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertDatabaseCount('paid_pack_usages', 0);
        $state['fae'] = ['rules' => []];
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertSame(1, (new PurchasePolicy)->usage($host)['games_used']);
        $this->assertSame(0, (new PurchasePolicy)->usage($other)['games_used']);
        $host->update(['status' => 'refunded']);
        $state['match_id'] = (string) Str::uuid();
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->assertSame(1, (new PurchasePolicy)->usage($other)['games_used']);
    }

    public function test_repeat_refunds_require_review_for_new_checkout_and_operator_can_clear_it(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, ['status' => 'refunded']);
        $policy = new PurchasePolicy;
        $this->assertFalse($policy->needsRepurchaseReview($owner->id, 'founders-pack'));
        $this->order($owner, ['status' => 'refunded']);
        $this->assertTrue($policy->needsRepurchaseReview($owner->id, 'founders-pack'));
        config(['payments.secret_key' => 'sk_test', 'payments.webhook_secret' => 'whsec_test', 'payments.bundles.0.price_id' => 'price_test']);
        $this->actingAs($owner)->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true,
            'terms_version' => config('legal.version'), 'digital_content_consent' => true, 'purchase_policy_version' => PurchasePolicy::VERSION])
            ->assertUnprocessable()->assertJsonValidationErrors('bundle_id');
        Http::assertNothingSent();
        $this->artisan('purchases:review', ['order' => $order->id])->assertSuccessful();
        $this->artisan('purchases:allow-repurchase', ['order' => $order->id])->assertSuccessful();
        $this->assertFalse($policy->needsRepurchaseReview($owner->id, 'founders-pack'));
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_receipt_outbox_is_atomic_idempotent_and_preserves_purchase_snapshot(): void
    {
        $order = $this->order();
        try {
            DB::transaction(function () use ($order): void {
                (new PurchaseReceipts)->queue($order);
                throw new RuntimeException('Rollback fixture');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback fixture', $exception->getMessage());
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertNull($order->refresh()->receipt_queued_at);
        DB::transaction(function () use ($order): void {
            (new PurchaseReceipts)->queue($order);
            (new PurchaseReceipts)->queue($order->refresh());
        });
        $this->assertDatabaseCount('jobs', 1);
        $snapshot = $order->refresh()->receipt_payload;
        $this->assertSame('EUR 9.67', $snapshot['amount_label']);
        $this->assertSame(PurchasePolicy::CONSENT, $snapshot['consent_text']);
        $this->assertCount(7, $snapshot['items']);
        $order->update(['bundle_name' => 'Renamed later']);
        (new SendPurchaseReceipt($order->id))->handle();
        (new SendPurchaseReceipt($order->id))->handle();
        Mail::assertSent(PurchaseReceipt::class, 1);
        Mail::assertSent(PurchaseReceipt::class, fn ($mail): bool => $mail->receipt === $snapshot && $mail->hasTo($snapshot['email']));
        $this->assertNotNull($order->fresh()->receipt_sent_at);
        $mail = new PurchaseReceipt($snapshot);
        $mail->assertSeeInHtml('Founder');
        $mail->assertSeeInText('EUR 9.67');
        $mail->assertSeeInText(PurchasePolicy::CONSENT);
        $mail->assertSeeInText($order->id);
    }

    public function test_older_checkout_confirmation_keeps_original_policy_after_two_game_update(): void
    {
        $originalConsent = str_replace('for purchases used in no more than two distinct games.', 'for packs not used in a match.', PurchasePolicy::CONSENT);
        $order = $this->order(overrides: ['legal_acceptance' => [
            'purchase_policy_version' => '2026-09-13-packs-v1', 'digital_content_consent' => true,
            'digital_content_consent_text' => $originalConsent, 'accepted_at' => now()->toISOString(),
        ]]);
        DB::transaction(function () use ($order): void {
            (new PurchaseReceipts)->queue($order);
        });
        $this->assertSame($originalConsent, $order->refresh()->receipt_payload['consent_text']);
        $this->assertSame(PurchasePolicy::ORIGINAL_POLICY, $order->receipt_payload['policy_text']);
        $this->assertTrue((new PurchasePolicy)->refund($order)['can_request']);
    }

    public function test_failed_receipt_does_not_claim_confirmation_and_legacy_receipt_does_not_add_consent(): void
    {
        $order = $this->order(overrides: ['checkout_parameters' => [], 'legal_acceptance' => null]);
        DB::transaction(function () use ($order): void {
            (new PurchaseReceipts)->queue($order);
        });
        $this->assertNull($order->receipt_payload['consent_text']);
        try {
            (new SendPurchaseReceipt($order->id))->handle();
            $this->fail('Missing email should fail delivery');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('email', $exception->getMessage());
        }
        $this->assertNull($order->fresh()->receipt_sent_at);
        Mail::assertNothingSent();
    }

    public function test_withdrawal_notifies_support_of_verified_usage_but_never_leaks_review_to_customer(): void
    {
        $order = $this->order();
        $state = $this->state($order, ['title' => 'founder']);
        DB::transaction(function () use (&$state): void {
            (new PaidPackUsage)->start($state);
        });
        $this->postJson('/refunds', ['request_id' => (string) Str::uuid(), 'name' => 'Buyer',
            'email' => $order->checkout_parameters['customer_email'], 'order_reference' => $order->id, 'confirmation' => true])
            ->assertCreated()->assertJsonMissingPath('purchase_review');
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($mail): bool => $mail->forSupport && str_contains($mail->details['purchase_review'], '"games_used": 1'));
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($mail): bool => ! $mail->forSupport && ! isset($mail->details['purchase_review']));
        $this->assertSame('paid', $order->fresh()->status);
        Mail::fake();
        $this->postJson('/refunds', ['request_id' => (string) Str::uuid(), 'name' => 'Other',
            'email' => 'not-the-buyer@example.test', 'order_reference' => $order->id, 'confirmation' => true])->assertCreated();
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($mail): bool => $mail->forSupport && ! isset($mail->details['purchase_review']));
    }
}
