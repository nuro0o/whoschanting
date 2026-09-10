<?php

return [
    'min_players' => (int) env('GAME_MIN_PLAYERS', 5),
    'max_players' => (int) env('GAME_MAX_PLAYERS', 10),
    'tokens_per_player' => 1.2,
    'seconds' => ['reveal' => 25, 'night' => 45, 'discussion' => 90, 'voting' => 45],
    'missions' => [
        'concord' => ['id' => 'concord', 'name' => 'A chorus in the deep', 'description' => 'Every living cultist must chant tonight. If all do, each earns one ritual token. A lone survivor can chant alone.'],
        'shadows' => ['id' => 'shadows', 'name' => 'Beyond the lantern light', 'description' => 'Each cultist who chants without being investigated tonight earns one ritual token.'],
        'patience' => ['id' => 'patience', 'name' => 'The faithful endure', 'description' => 'Each chant earns one token if the previous vote did not banish a cultist. The first night is eligible.'],
    ],
];
