<?php

return [
    'fae-court' => ['active' => env('FAE_COURT_ACTIVE', false), 'name' => 'The Fae Court', 'alignment' => 'fae',
        'description' => 'Anonymous bargains and shared victories.', 'instructions' => 'One owner shares the Court with the whole room. Fulfill bargains with different partners to share Town or Cult victory.',
        'min_players' => 7, 'roles' => ['fae_broker', 'fae_collector'], 'goal' => 3],
    'drowned' => ['active' => env('DROWNED_ACTIVE', false), 'name' => 'The Drowned', 'alignment' => 'drowned',
        'description' => 'Secret marks spread through the village before the tide rises.',
        'instructions' => 'The Tidecaller marks villagers; the Ferryman moves existing marks. Other players can spend a night sounding for a mark or cleansing one, instead of their usual ability. The Exorcist also cleanses marks. After every third vote, three living marked villagers secure the Drowned a shared victory. Marks never change allegiance or kill.',
        'min_players' => 9, 'roles' => ['drowned_tidecaller', 'drowned_ferryman'], 'replace' => ['townsperson', 'acolyte'], 'goal' => 3, 'tide_every' => 3],
    'gilded-hand' => ['active' => env('GILDED_HAND_ACTIVE', false), 'name' => 'The Gilded Hand', 'alignment' => 'gilded',
        'description' => 'Three relics, secret thefts, and suspicious handoffs.',
        'instructions' => 'The Lifter steals relics and the Appraiser privately locates them. Any holder can hand over a relic instead of their normal night action. Conflicting moves of the same relic fail; banished holders drop their relics to living villagers. The Hand shares victory only if all three relics are held by living members when Town or Cult wins.',
        'min_players' => 9, 'roles' => ['gilded_lifter', 'gilded_appraiser'], 'replace' => ['townsperson', 'acolyte'], 'goal' => 3,
        'relics' => ['silver_key' => 'Silver Key', 'glass_eye' => 'Glass Eye', 'sun_coin' => 'Sun Coin']],
    'hollow-choir' => ['active' => env('HOLLOW_CHOIR_ACTIVE', false), 'name' => 'The Hollow Choir', 'alignment' => 'choir',
        'description' => 'A rival chorus diverts the Cult’s ritual into its own awakening.',
        'instructions' => 'The Cantor siphons one newly earned ritual step when the Cult earns at least two. The Resonant can listen for a chant or, once per match, amplify a successful siphon by one. The Cult always keeps at least one earned step. Three echoes secure a shared victory, so the Choir needs the Cult alive and chanting long enough to feed it.',
        'min_players' => 11, 'roles' => ['choir_cantor', 'choir_resonant'], 'replace' => ['townsperson', 'townsperson'], 'goal' => 3],
    'carnival' => ['active' => env('CARNIVAL_ACTIVE', false), 'name' => 'The Carnival', 'alignment' => 'carnival',
        'description' => 'Secret mischief objectives build toward a shared finale.',
        'instructions' => 'Each night the Harlequin commits to surviving an accusation or helping cause a tied vote, while the Augur predicts a banishment. Each different act can score only once. Complete all three acts across at least two days to secure a shared finale. Accusation acts require submitting a ballot; ties require voting for one of the tied leading candidates.',
        'min_players' => 9, 'roles' => ['carnival_harlequin', 'carnival_augur'], 'replace' => ['townsperson', 'acolyte'], 'goal' => 3, 'minimum_days' => 2],
];
