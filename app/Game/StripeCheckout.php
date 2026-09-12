<?php

namespace App\Game;

use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StripeCheckout
{
    private const API = 'https://api.stripe.com/v1';

    private function request(): PendingRequest
    {
        return Http::withBasicAuth((string) config('payments.secret_key'), '')
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    public function checkout(User $user, string $bundleId): string
    {
        $bundle = collect((new PaidCosmetics)->bundles())->firstWhere('id', $bundleId);
        if ($bundle === null || blank($bundle['price_id']) || blank(config('payments.secret_key')) || blank(config('payments.webhook_secret'))) {
            throw ValidationException::withMessages(['bundle_id' => 'This bundle is not available for checkout yet.']);
        }

        $this->assertRepurchaseAllowed($user->id, $bundleId);

        // Reconcile existing sessions before allowing another payment for the same bundle.
        $pending = PaidOrder::where('user_id', $user->id)->where('bundle_id', $bundleId)->where('status', 'pending')->first();
        if ($pending?->stripe_session_id !== null) {
            $session = $this->session($pending->stripe_session_id);
            $this->reconcile($pending->id, $session);
            $pending->refresh();
            if ($pending->status === 'pending') {
                if (($session['status'] ?? null) === 'open') {
                    if (($pending->legal_acceptance['terms_version'] ?? null) !== config('legal.version')
                        || ($pending->legal_acceptance['purchase_policy_version'] ?? null) !== PurchasePolicy::VERSION) {
                        DB::transaction(function () use ($pending): void {
                            $locked = PaidOrder::whereKey($pending->id)->lockForUpdate()->firstOrFail();
                            if ($locked->status === 'pending') {
                                $locked->update(['legal_acceptance' => $this->acceptance()]);
                            }
                        });
                    }

                    return $this->checkoutUrl($session);
                }
                throw ValidationException::withMessages(['bundle_id' => 'Your earlier payment is still processing. Check the store again shortly.']);
            }
        }

        $price = $this->request()->get(self::API.'/prices/'.rawurlencode($bundle['price_id']))->throw()->json();
        if (($price['active'] ?? false) !== true || ($price['type'] ?? null) !== 'one_time'
            || ($price['currency'] ?? null) !== $bundle['currency'] || ($price['unit_amount'] ?? null) !== $bundle['amount']) {
            throw ValidationException::withMessages(['bundle_id' => 'This bundle’s checkout price is being updated. Please try again later.']);
        }

        // Persist the immutable order before contacting Stripe. A timeout retries the exact same request/key.
        $order = DB::transaction(function () use ($user, $bundle, $bundleId): PaidOrder {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertRepurchaseAllowed($user->id, $bundleId);
            if (PaidOrder::where('user_id', $user->id)->where('bundle_id', $bundleId)->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['bundle_id' => 'You already own this purchase.']);
            }
            $existing = PaidOrder::where('user_id', $user->id)->where('bundle_id', $bundleId)->where('status', 'pending')->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->created_at->lt(now()->subHours(23))) {
                    throw ValidationException::withMessages(['bundle_id' => 'Your earlier checkout needs verification. Please contact support before trying again.']);
                }

                if (($existing->legal_acceptance['terms_version'] ?? null) !== config('legal.version')
                    || ($existing->legal_acceptance['purchase_policy_version'] ?? null) !== PurchasePolicy::VERSION) {
                    $existing->update(['legal_acceptance' => $this->acceptance()]);
                }

                return $existing;
            }
            $id = (string) Str::uuid();
            $metadata = ['source_app' => 'whoschanting', 'order_id' => $id, 'bundle_id' => $bundleId];

            return PaidOrder::create(['id' => $id, 'user_id' => $user->id, 'bundle_id' => $bundleId,
                'price_id' => $bundle['price_id'], 'amount' => $bundle['amount'], 'currency' => $bundle['currency'],
                'cosmetics' => $bundle['cosmetics'], 'bundle_name' => $bundle['name'], 'status' => 'pending',
                'legal_acceptance' => $this->acceptance(),
                'checkout_parameters' => [
                    'mode' => 'payment', 'client_reference_id' => $id, 'customer_email' => $user->email,
                    'line_items' => [['price' => $bundle['price_id'], 'quantity' => 1]],
                    'metadata' => $metadata, 'payment_intent_data' => ['metadata' => $metadata],
                    'automatic_tax' => ['enabled' => config('payments.automatic_tax') ? 'true' : 'false'],
                    'expires_at' => now()->addHour()->timestamp,
                    'success_url' => route('profile.edit').'?checkout=success&session_id={CHECKOUT_SESSION_ID}#store',
                    'cancel_url' => route('profile.edit').'?checkout=cancelled#store',
                    'custom_text' => ['submit' => ['message' => 'One-time digital content. Immediate supply with your express consent; withdrawal exception explained at checkout. Additional 14-day refunds for purchases used in at most two games. Faulty-content rights remain. Terms: '.url('/terms').'. Privacy: '.url('/privacy').'. Refunds: '.url('/refunds').'.']],
                ],
            ]);
        });

        $session = $this->request()->withHeaders(['Idempotency-Key' => 'whoschanting-checkout:'.$order->id])
            ->asForm()->post(self::API.'/checkout/sessions', $order->checkout_parameters)->throw()->json();
        if (! is_string($session['id'] ?? null) || ! str_starts_with($session['id'], 'cs_')) {
            throw new RuntimeException('Stripe returned an invalid checkout session.');
        }
        PaidOrder::whereKey($order->id)->update(['stripe_session_id' => $session['id']]);

        return $this->checkoutUrl($session);
    }

    /** @return array<string,string|bool> */
    private function acceptance(): array
    {
        return ['terms_version' => config('legal.version'), 'accepted_at' => now()->toISOString(),
            'terms_url' => url('/terms'), 'privacy_url' => url('/privacy'), 'refunds_url' => url('/refunds'),
            'purchase_policy_version' => PurchasePolicy::VERSION, 'purchase_policy_text' => PurchasePolicy::POLICY,
            'digital_content_consent' => true, 'digital_content_consent_text' => PurchasePolicy::CONSENT,
            // Acknowledgment is evidence, not a finding that withdrawal has already been lost.
            'withdrawal_waived' => false];
    }

    private function assertRepurchaseAllowed(int $userId, string $bundleId): void
    {
        if ((new PurchasePolicy)->needsRepurchaseReview($userId, $bundleId)) {
            throw ValidationException::withMessages(['bundle_id' => 'This pack has been refunded more than once. Contact '.config('legal.support_email').' before buying it again. Existing refund and consumer rights are unaffected.']);
        }
    }

    /** @param array<string,mixed> $session */
    private function checkoutUrl(array $session): string
    {
        $url = $session['url'] ?? null;
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.stripe.com') {
            throw new RuntimeException('Stripe checkout is not currently available.');
        }

        return $url;
    }

    /** @return array<string,mixed> */
    private function session(string $id): array
    {
        return $this->request()->get(self::API.'/checkout/sessions/'.rawurlencode($id),
            ['expand' => ['line_items', 'payment_intent.latest_charge']])->throw()->json();
    }

    public function status(User $user, string $sessionId): string
    {
        $order = PaidOrder::where('user_id', $user->id)->where('stripe_session_id', $sessionId)->firstOrFail();
        // Even paid orders are reconciled to catch refunds if a webhook was delayed.
        $this->reconcile($order->id, $this->session($sessionId));

        return $order->refresh()->status;
    }

    /** @param array<string,mixed> $session */
    private function reconcile(string $orderId, array $session): void
    {
        DB::transaction(function () use ($orderId, $session): void {
            $order = PaidOrder::whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                return;
            }
            $metadata = $session['metadata'] ?? [];
            if (($metadata['source_app'] ?? null) !== 'whoschanting' || ($metadata['order_id'] ?? null) !== $order->id
                || ($metadata['bundle_id'] ?? null) !== $order->bundle_id || ($session['client_reference_id'] ?? null) !== $order->id
                || ($session['mode'] ?? null) !== 'payment' || ! is_string($session['id'] ?? null)
                || ($order->stripe_session_id !== null && $order->stripe_session_id !== $session['id'])) {
                throw new RuntimeException('Checkout session does not match its order.');
            }
            $order->stripe_session_id = $session['id'];
            $intent = $session['payment_intent'] ?? null;
            if (is_array($intent) && is_string($intent['id'] ?? null)) {
                $order->stripe_payment_intent_id = $intent['id'];
            }
            // Terminal reversals cannot be undone by late completed events or stale API responses.
            if (in_array($order->status, ['refunded', 'disputed'], true)) {
                $order->save();

                return;
            }
            $charge = is_array($intent) ? ($intent['latest_charge'] ?? null) : null;
            if (is_array($charge) && ($charge['disputed'] ?? false)) {
                $order->status = 'disputed';
            } elseif (is_array($charge) && ($charge['refunded'] ?? false)) {
                $order->status = 'refunded';
            } elseif (($session['payment_status'] ?? null) === 'paid') {
                $lines = $session['line_items']['data'] ?? [];
                if (($session['currency'] ?? null) !== $order->currency || ($session['amount_subtotal'] ?? null) !== $order->amount
                    || count($lines) !== 1 || ($lines[0]['price']['id'] ?? null) !== $order->price_id || ($lines[0]['quantity'] ?? null) !== 1
                    || ! is_array($intent) || ($intent['status'] ?? null) !== 'succeeded'
                    || ! is_array($charge) || ! ($charge['paid'] ?? false)) {
                    throw new RuntimeException('Paid checkout does not match the purchased bundle.');
                }
                $order->status = 'paid';
                $order->paid_at ??= now()->toImmutable();
                if (is_int($session['amount_total'] ?? null) && $session['amount_total'] >= 0) {
                    $order->total_amount ??= $session['amount_total'];
                }
            } elseif ($order->status === 'pending' && ($session['status'] ?? null) === 'expired') {
                $order->status = 'expired';
            }
            $order->save();
            (new PurchaseReceipts)->queue($order);
        });
    }

    /** @param array<string,mixed> $event */
    public function event(array $event): void
    {
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];
        if (! is_array($object)) {
            throw new RuntimeException('Malformed Stripe event.');
        }
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
            if (($object['metadata']['source_app'] ?? null) !== 'whoschanting') {
                return;
            }
            $orderId = $object['metadata']['order_id'] ?? '';
            if (! is_string($orderId) || ! PaidOrder::whereKey($orderId)->exists()) {
                return;
            }
            $this->reconcile($orderId, $this->session((string) ($object['id'] ?? '')));
            if ($type === 'checkout.session.async_payment_failed') {
                PaidOrder::whereKey($orderId)->where('status', 'pending')->update(['status' => 'failed']);
            }
        } elseif (in_array($type, ['charge.refunded', 'charge.dispute.created'], true)) {
            $intentId = $object['payment_intent'] ?? null;
            if (! is_string($intentId)) {
                return;
            }
            $intent = $this->request()->get(self::API.'/payment_intents/'.rawurlencode($intentId))->throw()->json();
            if (($intent['metadata']['source_app'] ?? null) !== 'whoschanting') {
                return;
            }
            $orderId = $intent['metadata']['order_id'] ?? '';
            // Full refunds revoke the bundle. Partial refunds leave its cosmetics available.
            if ($type === 'charge.dispute.created' || ($object['refunded'] ?? false)) {
                PaidOrder::whereKey($orderId)->where(function ($query) use ($intentId): void {
                    $query->whereNull('stripe_payment_intent_id')->orWhere('stripe_payment_intent_id', $intentId);
                })->update(['status' => $type === 'charge.refunded' ? 'refunded' : 'disputed', 'stripe_payment_intent_id' => $intentId]);
            }
        }
    }

    public function validSignature(string $body, string $header): bool
    {
        $secret = (string) config('payments.webhook_secret');
        if ($secret === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || abs(time() - $timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
