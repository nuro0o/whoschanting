<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $bundle_id
 * @property string $status
 * @property string $price_id
 * @property int $amount
 * @property string $currency
 * @property list<array{category:string,id:string,name:string}> $cosmetics
 * @property array<string,mixed> $checkout_parameters
 * @property array<string,mixed>|null $legal_acceptance
 * @property string|null $stripe_session_id
 * @property string|null $stripe_payment_intent_id
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 * @property string|null $bundle_name
 * @property int|null $total_amount
 * @property array<string,mixed>|null $receipt_payload
 * @property CarbonImmutable|null $receipt_queued_at
 * @property CarbonImmutable|null $receipt_sent_at
 */
class PaidOrder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'cosmetics' => 'array', 'checkout_parameters' => 'array', 'legal_acceptance' => 'array',
            'paid_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'total_amount' => 'integer',
            'receipt_payload' => 'array', 'receipt_queued_at' => 'immutable_datetime', 'receipt_sent_at' => 'immutable_datetime'];
    }
}
