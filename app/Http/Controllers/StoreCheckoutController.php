<?php

namespace App\Http\Controllers;

use App\Game\AccountProgression;
use App\Game\PurchasePolicy;
use App\Game\StripeCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreCheckoutController extends Controller
{
    public function checkout(Request $request, StripeCheckout $stripe): JsonResponse
    {
        $input = $request->validate(['bundle_id' => ['required', 'string', 'max:80'],
            'terms' => ['required', 'accepted'], 'terms_version' => ['required', Rule::in([config('legal.version')])],
            'digital_content_consent' => ['required', 'accepted'],
            'purchase_policy_version' => ['required', Rule::in([PurchasePolicy::VERSION])]]);
        try {
            return response()->json(['url' => $stripe->checkout($request->user(), $input['bundle_id'])]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Checkout is temporarily unavailable. Please try again.'], 503);
        }
    }

    public function status(Request $request, StripeCheckout $stripe, AccountProgression $progression): JsonResponse
    {
        $input = $request->validate(['session_id' => ['required', 'string', 'max:255', 'regex:/^cs_[a-zA-Z0-9_]+$/']]);
        $status = $stripe->status($request->user(), $input['session_id']);

        return response()->json(['status' => $status, 'progression' => $progression->view($request->user()->id)]);
    }

    public function webhook(Request $request, StripeCheckout $stripe): JsonResponse
    {
        if (! $stripe->validSignature($request->getContent(), (string) $request->header('Stripe-Signature'))) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }
        $event = $request->json()->all();
        if (! is_string($event['id'] ?? null) || ! str_starts_with($event['id'], 'evt_') || ! is_string($event['type'] ?? null)) {
            return response()->json(['message' => 'Malformed Stripe event.'], 400);
        }
        try {
            $stripe->event($event);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Payment event could not be processed. Please retry.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
