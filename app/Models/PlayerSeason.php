<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $xp
 * @property int $matches
 * @property int $wins
 * @property string $season_id
 */
class PlayerSeason extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['xp' => 'integer', 'matches' => 'integer', 'wins' => 'integer'];
    }
}
