<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $order_reference
 * @property string|null $message
 * @property string $declaration
 * @property CarbonImmutable $created_at
 */
class WithdrawalRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
