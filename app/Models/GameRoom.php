<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property array<string, mixed> $state
 * @property CarbonImmutable|null $deadline
 * @property CarbonImmutable|null $maintenance_at
 */
class GameRoom extends Model
{
    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['state'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['state' => 'array', 'deadline' => 'immutable_datetime', 'maintenance_at' => 'immutable_datetime'];
    }
}
