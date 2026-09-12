<?php

return [
    // English starter list. Add explicit words/inflections here; entries are
    // literal words, never regular expressions. Keep ambiguous words out.
    'words' => [
        'fuck', 'fucks', 'fucked', 'fucker', 'fuckers', 'fucking',
        'motherfucker', 'motherfuckers', 'motherfucking',
        'shit', 'shits', 'shitty', 'shitting', 'bullshit', 'shithead', 'shitheads', 'dipshit', 'dipshits',
        'bitch', 'bitches', 'bitching', 'bitchy',
        'asshole', 'assholes', 'arsehole', 'arseholes',
        'bastard', 'bastards', 'cunt', 'cunts', 'dickhead', 'dickheads',
        'wanker', 'wankers', 'twat', 'twats',
        'faggot', 'faggots', 'nigger', 'niggers', 'nigga', 'niggas',
    ],

    // Unambiguous roots also match inside usernames, including decorative letters
    // and numbers. Keep roots such as "cunt" out to preserve names like Scunthorpe.
    'name_fragments' => [
        'fuck', 'shit', 'bitch', 'asshole', 'arsehole', 'dickhead',
        'faggot', 'nigger', 'nigga',
    ],
];
