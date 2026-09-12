<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $xp
 * @property int $matches
 * @property int $wins
 * @property string $season_id
 * @property array<string, int>|null $achievement_progress
 */
class PlayerSeason extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['xp' => 'integer', 'matches' => 'integer', 'wins' => 'integer', 'achievement_progress' => 'array'];
    }
}
