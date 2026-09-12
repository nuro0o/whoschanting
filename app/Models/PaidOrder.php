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
 * @property string|null $stripe_session_id
 * @property string|null $stripe_payment_intent_id
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 */
class PaidOrder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'cosmetics' => 'array', 'checkout_parameters' => 'array',
            'paid_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
