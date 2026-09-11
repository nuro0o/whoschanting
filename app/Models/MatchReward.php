<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $data */
class MatchReward extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
