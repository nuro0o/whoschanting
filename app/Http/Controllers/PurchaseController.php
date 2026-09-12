<?php

namespace App\Http\Controllers;

use App\Game\PurchasePolicy;
use App\Models\PaidOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request, PurchasePolicy $policy): Response
    {
        $orders = PaidOrder::where('user_id', $request->user()->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(12);

        return Inertia::render('Purchases', ['purchases' => $orders->through(fn (PaidOrder $order): array => [
            'id' => $order->id, 'bundle_id' => $order->bundle_id, 'name' => $order->bundle_name ?? $order->bundle_id,
            'status' => $order->status, 'amount' => $order->amount, 'currency' => $order->currency,
            'total_amount' => $order->total_amount, 'paid_at' => $order->paid_at?->toISOString(), 'created_at' => $order->created_at->toISOString(),
            'cosmetics' => $order->cosmetics, ...$policy->usage($order), 'refund' => $policy->refund($order),
            'receipt_sent_at' => $order->receipt_sent_at?->toISOString(), 'withdrawal_url' => url('/refunds').'?order='.$order->id,
        ])]);
    }
}
