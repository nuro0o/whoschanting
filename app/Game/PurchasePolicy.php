<?php

namespace App\Game;

use App\Models\PaidOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PurchasePolicy
{
    public const VERSION = '2026-09-13-packs-v2';

    public const MAX_REFUND_GAMES = 2;

    public const CONSENT = 'I request immediate access to this digital content and expressly consent to supply beginning during the 14-day withdrawal period. I acknowledge that my statutory right of withdrawal is lost once supply begins, subject to the required purchase confirmation. The store additionally offers refunds within 14 days for purchases used in no more than two distinct games. My rights for faulty or undelivered content are unaffected.';

    public const ORIGINAL_POLICY = 'You may request a refund within 14 days of purchase if the pack or expansion has not been used in a started match. Previews, wardrobe selections and waiting lobbies do not count. A displayed appearance or host table counts when the match starts; banishment and victory animations count only when the server triggers them. Using any included cosmetic marks the pack as used. An expansion counts when your purchase enables an expansion match; joining somebody else’s expansion match does not count. Used purchases are normally outside this additional unused-purchase refund policy. Applicable statutory rights, including for faulty or undelivered content, remain unaffected. Missing consent or required confirmation is reviewed separately. Repeated refunded purchases may require support review before another purchase. Contact support about accidental purchases or a usage record you believe is incorrect.';

    public const POLICY = 'You may request a refund within 14 days of purchase if the pack or expansion has been used in no more than two distinct games. Previews, wardrobe selections and waiting lobbies do not count. A displayed appearance or host table counts when the match starts; banishment and victory animations count only when the server triggers them. Using any included cosmetic counts as one use of the pack for that game; multiple cosmetics in the same game still count as one use. An expansion counts when your purchase enables an expansion match; joining somebody else’s expansion match does not count. Purchases used in three or more games are outside this additional two-game refund policy. Applicable statutory rights, including for faulty or undelivered content, remain unaffected. Missing consent or required confirmation is reviewed separately. Repeated refunded purchases may require support review before another purchase. Contact support about accidental purchases or a usage record you believe is incorrect.';

    /** @return array{games_used:int,first_used_at:string|null} */
    public function usage(PaidOrder $order): array
    {
        $query = DB::table('paid_pack_usages')->where('paid_order_id', $order->id);
        $first = $query->min('used_at');

        return ['games_used' => $query->count(), 'first_used_at' => $first === null ? null : CarbonImmutable::parse($first, config('app.timezone'))->toISOString()];
    }

    /** @return array{status:string,label:string,explanation:string,deadline:string|null,can_request:bool} */
    public function refund(PaidOrder $order): array
    {
        $deadline = $order->paid_at?->addDays(14)->endOfDay();
        $usage = $this->usage($order);
        if ($order->status !== 'paid') {
            return ['status' => $order->status, 'label' => ucfirst($order->status), 'explanation' => 'Contact support if you need help with this payment.', 'deadline' => $deadline?->toISOString(), 'can_request' => false];
        }
        // This more generous store policy also covers older purchases; their statutory rights and recorded consent are unchanged.
        if ($usage['games_used'] <= self::MAX_REFUND_GAMES && $deadline?->isFuture()) {
            return ['status' => 'eligible', 'label' => 'Refund available',
                'explanation' => 'Used in no more than two games and within the 14-day refund window. Request a refund below; support will verify the payment and process your request.',
                'deadline' => $deadline->toISOString(), 'can_request' => true];
        }

        return ['status' => 'review', 'label' => $usage['games_used'] > self::MAX_REFUND_GAMES ? 'Two-game refund limit exceeded' : 'Refund window ended',
            'explanation' => 'This purchase is outside the additional two-game refund policy. You can still contact support for an accidental purchase, faulty or undelivered content, or another applicable statutory right. Original purchase terms and statutory rights remain in place; usage alone does not decide your legal rights.',
            'deadline' => $deadline?->toISOString(), 'can_request' => false];
    }

    public function needsRepurchaseReview(int $userId, string $bundleId): bool
    {
        return PaidOrder::where('user_id', $userId)->where('bundle_id', $bundleId)->where('status', 'refunded')->whereNull('repurchase_reviewed_at')->count() >= 2;
    }

    /** @return array<string,mixed> */
    public function review(PaidOrder $order): array
    {
        return ['order' => $order->id, 'status' => $order->status, ...$this->usage($order),
            'refund' => $this->refund($order), 'acceptance' => $order->legal_acceptance,
            'receipt_queued_at' => $order->receipt_queued_at?->toISOString(), 'receipt_sent_at' => $order->receipt_sent_at?->toISOString(),
            'previous_refunds_for_pack' => PaidOrder::where('user_id', $order->user_id)->where('bundle_id', $order->bundle_id)->where('status', 'refunded')->count(),
            'note' => 'Match usage is evidence for review, not an automatic fraud or legal eligibility decision. Queued/sent email does not prove receipt or confirmation before supply.'];
    }
}
