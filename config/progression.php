<?php

return [
    'match_xp' => 80,
    'win_xp' => 40,
    'attendance_xp' => 20,
    'level_step' => 250,
    'character_unlocks' => [
        'tidecaller' => ['level' => 2],
        'cartographer' => ['level' => 3],
        'maskmaker' => ['achievement' => 'many_faces', 'description' => 'Complete qualifying matches as 5 different roles.'],
        'drowned_regent' => ['achievement' => 'veteran', 'description' => 'Participate in 25 qualifying completed matches.'],
    ],
    'season_tiers' => [
        ['id' => 'unranked', 'name' => 'New arrival', 'xp' => 0],
        ['id' => 'bronze', 'name' => 'Bronze lantern', 'xp' => 250],
        ['id' => 'silver', 'name' => 'Silver lantern', 'xp' => 750],
        ['id' => 'gold', 'name' => 'Golden tide', 'xp' => 1500],
        ['id' => 'mythic', 'name' => 'Voice of the deep', 'xp' => 3000],
    ],
    'achievements' => [
        'first_watch' => ['name' => 'First watch', 'description' => 'Participate in your first completed match.', 'stat' => 'matches', 'target' => 1],
        'town_victory' => ['name' => 'Another sunrise', 'description' => 'Win a match with the Town.', 'stat' => 'town_wins', 'target' => 1],
        'cult_victory' => ['name' => 'The deep answers', 'description' => 'Win a match with the Cult. Unlocks the Ember accent.', 'stat' => 'cult_wins', 'target' => 1],
        'regular' => ['name' => 'A familiar suspect', 'description' => 'Participate in 5 completed matches.', 'stat' => 'matches', 'target' => 5],
        'veteran' => ['name' => 'Village veteran', 'description' => 'Participate in 25 completed matches. Unlocks the Veteran title.', 'stat' => 'matches', 'target' => 25],
        'many_faces' => ['name' => 'Many faces', 'description' => 'Participate in completed matches as 5 different roles.', 'stat' => 'roles', 'target' => 5],
        'kept_oath' => ['name' => 'My word is my bond', 'description' => 'Keep an Oathkeeper promise in a qualifying completed match. Unlocks the Oathbound title.', 'stat' => 'oath', 'target' => 1],
        'season_regular' => ['name' => 'A season in the village', 'description' => 'Participate in 10 completed matches in one season.', 'stat' => 'season_matches', 'target' => 10],
    ],
    'cosmetics' => [
        'titles' => [
            ['id' => 'newcomer', 'name' => 'Newcomer', 'level' => 1],
            ['id' => 'watchful', 'name' => 'The Watchful', 'level' => 3],
            ['id' => 'veteran', 'name' => 'Village Veteran', 'achievement' => 'veteran'],
            ['id' => 'oathbound', 'name' => 'Oathbound', 'achievement' => 'kept_oath'],
            ['id' => 'tidekeeper', 'name' => 'Tidekeeper', 'season_xp' => 1500],
        ],
        'frames' => [
            ['id' => 'plain', 'name' => 'Village frame', 'level' => 1],
            ['id' => 'copper', 'name' => 'Copper ring', 'level' => 2],
            ['id' => 'lantern', 'name' => 'Lantern keeper', 'level' => 5],
            ['id' => 'tidal', 'name' => 'Tidal wreath', 'season_xp' => 1500],
        ],
        'accents' => [
            ['id' => 'sea', 'name' => 'Sea glass', 'level' => 1],
            ['id' => 'storm', 'name' => 'Storm blue', 'level' => 1],
            ['id' => 'clay', 'name' => 'Rose clay', 'level' => 1],
            ['id' => 'moon', 'name' => 'Moon mist', 'level' => 2],
            ['id' => 'ember', 'name' => 'Ember', 'achievement' => 'cult_victory'],
            ['id' => 'gold', 'name' => 'Old gold', 'level' => 5],
        ],
        'backgrounds' => [
            ['id' => 'plain', 'name' => 'Original', 'level' => 1],
            ['id' => 'harbor', 'name' => 'Harbor fog', 'level' => 1],
            ['id' => 'dusk', 'name' => 'Dusk', 'level' => 2],
            ['id' => 'candlelight', 'name' => 'Candlelight', 'level' => 3],
        ],
    ],
];
