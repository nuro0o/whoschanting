<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $xp
 * @property int $coins
 * @property int $coins_earned
 * @property CarbonImmutable|null $crown_cooldown_until
 * @property list<string>|null $rapid_crown_matches
 * @property int $matches
 * @property int $wins
 * @property int $town_wins
 * @property int $cult_wins
 * @property list<string> $roles_played
 * @property array<string, string> $achievements
 * @property array{title:string, frame:string, accent:string, background?:string, character:string|null, creator?:array<string,int|string>|null} $customization
 */
class PlayerProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['xp' => 'integer', 'coins' => 'integer', 'coins_earned' => 'integer', 'matches' => 'integer', 'wins' => 'integer', 'town_wins' => 'integer', 'cult_wins' => 'integer',
            'crown_cooldown_until' => 'immutable_datetime', 'rapid_crown_matches' => 'array',
            'roles_played' => 'array', 'achievements' => 'array', 'customization' => 'array'];
    }
}
