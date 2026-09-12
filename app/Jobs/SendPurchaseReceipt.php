<?php

namespace App\Jobs;

use App\Mail\PurchaseReceipt;
use App\Models\PaidOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendPurchaseReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        $order = PaidOrder::find($this->orderId);
        if ($order === null || $order->receipt_sent_at !== null) {
            return;
        }
        $receipt = $order->receipt_payload;
        if (! is_array($receipt) || ! is_string($receipt['email'] ?? null) || ! filter_var($receipt['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The purchase receipt is missing its checkout email.');
        }
        Mail::to($receipt['email'])->send(new PurchaseReceipt($receipt));
        // Queued is not sent. Failed delivery never produces evidence of a confirmation.
        $order->forceFill(['receipt_sent_at' => now()])->save();
    }
}
