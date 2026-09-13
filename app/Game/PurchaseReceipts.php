<?php

namespace App\Game;

use App\Jobs\SendPurchaseReceipt;
use App\Models\PaidOrder;

class PurchaseReceipts
{
    /** Called with the order locked inside its payment transaction. */
    public function queue(PaidOrder $order): void
    {
        if ($order->receipt_queued_at !== null || $order->status !== 'paid') {
            return;
        }
        $consent = $order->legal_acceptance ?? [];
        $hasConsent = ($consent['digital_content_consent'] ?? false) === true && filled($consent['digital_content_consent_text'] ?? null);
        $policyText = $consent['purchase_policy_text'] ?? match ($consent['purchase_policy_version'] ?? null) {
            PurchasePolicy::VERSION => PurchasePolicy::POLICY,
            '2026-09-13-packs-v1' => PurchasePolicy::ORIGINAL_POLICY,
            default => 'Your original purchase terms and applicable statutory rights remain in effect. This receipt does not introduce a withdrawal waiver. Contact support for refunds and purchase questions.',
        };
        $amount = $order->total_amount ?? $order->amount;
        $items = array_column($order->cosmetics, 'name');
        $bundle = collect(config()->array('payments.bundles'))->firstWhere('id', $order->bundle_id);
        if (($bundle['kind'] ?? null) === 'faction') {
            array_unshift($items, $order->bundle_name ?? $order->bundle_id);
        }
        $order->receipt_payload = [
            'order_id' => $order->id, 'name' => $order->bundle_name ?? $order->bundle_id,
            'email' => $order->checkout_parameters['customer_email'] ?? null,
            'amount_label' => strtoupper($order->currency).' '.number_format($amount / 100, 2, '.', '').($order->total_amount === null ? ' (subtotal; see Stripe receipt for final total)' : ''),
            'paid_at' => $order->paid_at?->toISOString(), 'items' => $items ?: [$order->bundle_name ?? $order->bundle_id],
            'consent_text' => $hasConsent ? ($consent['digital_content_consent_text'] ?? null) : null,
            'consent_accepted_at' => $hasConsent ? ($consent['accepted_at'] ?? null) : null,
            'policy_text' => $policyText,
            'terms_version' => $consent['terms_version'] ?? null,
            'operator_name' => config('legal.operator_name'), 'business_address' => config('legal.business_address'),
            'registration_number' => config('legal.registration_number'), 'support_email' => config('legal.support_email'),
            'purchases_url' => url('/account/purchases'), 'refunds_url' => url('/refunds').'?order='.$order->id,
            'terms_url' => $consent['terms_url'] ?? url('/terms'), 'privacy_url' => $consent['privacy_url'] ?? url('/privacy'),
            'art_url' => url('/assets/chanting/verify-email-ferryman.jpg'),
        ];
        $order->receipt_queued_at = now()->toImmutable();
        $order->save();
        SendPurchaseReceipt::dispatch($order->id)->onConnection('database')->beforeCommit();
    }
}
