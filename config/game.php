<?php

return [
    'min_players' => (int) env('GAME_MIN_PLAYERS', 3),
    'max_players' => (int) env('GAME_MAX_PLAYERS', 10),
    'cultists_by_player_count' => [3 => 1, 4 => 1, 5 => 2, 6 => 2, 7 => 3, 8 => 3, 9 => 4, 10 => 4],
    'tokens_per_player' => 1.2,
    'characters' => [
        ['id' => 'mariner', 'name' => 'The Mariner'],
        ['id' => 'botanist', 'name' => 'The Botanist'],
        ['id' => 'lamplighter', 'name' => 'The Lamplighter'],
        ['id' => 'archivist', 'name' => 'The Archivist'],
        ['id' => 'baker', 'name' => 'The Baker'],
        ['id' => 'astronomer', 'name' => 'The Astronomer'],
        ['id' => 'ferryman', 'name' => 'The Ferryman'],
        ['id' => 'musician', 'name' => 'The Musician'],
    ],
    'seconds' => ['reveal' => 25, 'night' => 45, 'discussion' => 90, 'voting' => 45],
    'missions' => [
        'concord' => ['id' => 'concord', 'name' => 'A chorus in the deep', 'description' => 'Every living cultist must chant tonight. If all do, each chant advances the ritual by one step. A lone survivor can chant alone.'],
        'shadows' => ['id' => 'shadows', 'name' => 'Beyond the lantern light', 'description' => 'Each cultist who chants without being investigated tonight advances the ritual by one step.'],
        'patience' => ['id' => 'patience', 'name' => 'The faithful endure', 'description' => 'Each chant advances the ritual by one step if the previous vote did not banish a cultist. The first night counts too.'],
    ],
];
