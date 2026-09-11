<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $xp
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
        return ['xp' => 'integer', 'matches' => 'integer', 'wins' => 'integer', 'town_wins' => 'integer', 'cult_wins' => 'integer',
            'roles_played' => 'array', 'achievements' => 'array', 'customization' => 'array'];
    }
}
