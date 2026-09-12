<?php

namespace App\Http\Controllers;

use App\Game\PurchasePolicy;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\PaidOrder;
use App\Models\WithdrawalRequest;
use App\Support\LegalDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function show(Request $request, string $document, LegalDocuments $documents): Response
    {
        return Inertia::render('Legal', [
            'document' => $documents->document($document),
            'legal' => $documents->details(),
            'supportRequest' => $document === 'refunds' ? $request->session()->get('withdrawal_receipt') : null,
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $input = $request->validate([
            'request_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'], 'order_reference' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'], 'confirmation' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ]);
        $record = DB::transaction(function () use ($input): WithdrawalRequest {
            // A UUID belongs to one immutable submission; browser retries receive the same acknowledgment.
            WithdrawalRequest::query()->insertOrIgnore([
                'id' => $input['request_id'], 'name' => $input['name'], 'email' => $input['email'],
                'order_reference' => $input['order_reference'], 'message' => $input['message'] ?? null,
                'declaration' => 'I notify you that I withdraw from the purchase identified above.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = WithdrawalRequest::whereKey($input['request_id'])->lockForUpdate()->firstOrFail();
            abort_unless($record->name === $input['name'] && $record->email === $input['email']
                && $record->order_reference === $input['order_reference'] && $record->message === ($input['message'] ?? null), 409,
                'This request reference was already used. Start a new request or contact support.');
            if ($record->getAttribute('acknowledgment_queued_at') === null) {
                $details = ['reference' => $record->id, 'received_at' => $record->created_at->utc()->toISOString(),
                    'name' => $record->name, 'email' => $record->email, 'order_reference' => $record->order_reference,
                    'declaration' => $record->declaration, 'message' => $record->message];
                $order = PaidOrder::where(function ($query) use ($record): void {
                    $query->whereKey($record->order_reference)->orWhere('stripe_session_id', $record->order_reference)
                        ->orWhere('stripe_payment_intent_id', $record->order_reference);
                })->first();
                $supportDetails = $details;
                if ($order !== null && strcasecmp((string) ($order->checkout_parameters['customer_email'] ?? ''), $record->email) === 0) {
                    $supportDetails['purchase_review'] = json_encode((new PurchasePolicy)->review($order), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                }
                Mail::to(config('legal.support_email'))->queue(new WithdrawalAcknowledgment($supportDetails, true));
                Mail::to($record->email)->queue(new WithdrawalAcknowledgment($details));
                $record->setAttribute('acknowledgment_queued_at', now())->save();
            }

            return $record;
        });

        $receipt = ['reference' => $record->id, 'received_at' => $record->created_at->utc()->toISOString()];
        $request->session()->put('withdrawal_receipt', $receipt);

        return response()->json($receipt, 201);
    }
}
