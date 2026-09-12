<?php

return [
    // Same environment name as Geniousverse; never expose this key to the browser.
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'automatic_tax' => env('STRIPE_AUTOMATIC_TAX', false),
    'bundles' => [
        ['id' => 'founders-pack', 'name' => "Founder's Pack", 'description' => 'Leave your mark on the village with gilded portraits, an oak gathering table and a shower of crowns.',
            'amount' => 799, 'currency' => 'eur', 'price_id' => env('STRIPE_PRICE_FOUNDERS_PACK'),
            'cosmetics' => [
                ['category' => 'titles', 'id' => 'founder', 'name' => 'Village Founder'],
                ['category' => 'frames', 'id' => 'founder', 'name' => "Founder's crest"],
                ['category' => 'accents', 'id' => 'founder_gold', 'name' => "Founder's gold"],
                ['category' => 'backgrounds', 'id' => 'founders_hall', 'name' => "Founder's hall"],
                ['category' => 'tables', 'id' => 'founders_oak', 'name' => "Founder's oak table"],
                ['category' => 'banishments', 'id' => 'gilded_vortex', 'name' => 'Gilded vortex'],
                ['category' => 'celebrations', 'id' => 'crownfall', 'name' => 'Crownfall'],
            ]],
        ['id' => 'moonlit-coven', 'name' => 'Moonlit Coven', 'description' => 'Gather around a moonlit table, vanish into a lunar rift and celebrate beneath a rising moon.',
            'amount' => 499, 'currency' => 'eur', 'price_id' => env('STRIPE_PRICE_MOONLIT_COVEN'),
            'cosmetics' => [
                ['category' => 'accents', 'id' => 'lunar_violet', 'name' => 'Lunar violet'],
                ['category' => 'backgrounds', 'id' => 'moonlit_coven', 'name' => 'Moonlit coven'],
                ['category' => 'tables', 'id' => 'moonlit', 'name' => 'Moonlit table'],
                ['category' => 'banishments', 'id' => 'lunar_rift', 'name' => 'Lunar rift'],
                ['category' => 'celebrations', 'id' => 'moonrise', 'name' => 'Moonrise'],
            ]],
        ['id' => 'harvest-festival', 'name' => 'Harvest Festival', 'description' => 'Warm woodland colors, an autumn gathering table and lanterns that lift into the night.',
            'amount' => 499, 'currency' => 'eur', 'price_id' => env('STRIPE_PRICE_HARVEST_FESTIVAL'),
            'cosmetics' => [
                ['category' => 'accents', 'id' => 'harvest_amber', 'name' => 'Harvest amber'],
                ['category' => 'backgrounds', 'id' => 'harvest_glow', 'name' => 'Harvest glow'],
                ['category' => 'tables', 'id' => 'harvest', 'name' => 'Harvest table'],
                ['category' => 'banishments', 'id' => 'ember_spiral', 'name' => 'Ember spiral'],
                ['category' => 'celebrations', 'id' => 'lantern_festival', 'name' => 'Lantern festival'],
            ]],
    ],
];
