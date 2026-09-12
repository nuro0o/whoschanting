<?php

return [
    'currency' => 'Crowns',
    'coins_per_player' => 1,
    'small_game_max_players' => 6,
    'small_game_win_coins' => 5,
    'large_game_win_coins' => 10,
    'earning_guard' => [
        'rapid_match_seconds' => 120,
        'window_minutes' => 15,
        'rapid_match_limit' => 4,
        'cooldown_minutes' => 15,
    ],
    // Permanent cosmetics only. Prices and grants are always resolved on the server.
    'items' => [
        ['id' => 'title-night-market', 'category' => 'titles', 'cosmetic_id' => 'night_market', 'name' => 'Night Market Regular',
            'description' => 'A title for a familiar face beneath the market lanterns.', 'price' => 100],
        ['id' => 'title-velvet-voice', 'category' => 'titles', 'cosmetic_id' => 'velvet_voice', 'name' => 'The Velvet Voice',
            'description' => 'Let your village title do a little of the talking.', 'price' => 200],
        ['id' => 'accent-amethyst', 'category' => 'accents', 'cosmetic_id' => 'amethyst', 'name' => 'Amethyst',
            'description' => 'A soft violet accent for your portrait.', 'price' => 150],
        ['id' => 'accent-patina', 'category' => 'accents', 'cosmetic_id' => 'patina', 'name' => 'Patina',
            'description' => 'Weathered turquoise, carried in on the tide.', 'price' => 150],
        ['id' => 'background-moonlit', 'category' => 'backgrounds', 'cosmetic_id' => 'moonlit', 'name' => 'Moonlit Crossing',
            'description' => 'Frame your portrait in the silver blues of a midnight crossing.', 'price' => 250],
        ['id' => 'background-wildwood', 'category' => 'backgrounds', 'cosmetic_id' => 'wildwood', 'name' => 'Wildwood',
            'description' => 'A portrait backdrop of deep forest greens and warm woodland light.', 'price' => 250],
    ],
];
