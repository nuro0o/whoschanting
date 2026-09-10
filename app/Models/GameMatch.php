<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['recap'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recap' => 'array', 'recap_complete' => 'boolean', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}
