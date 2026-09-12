<?php

namespace App\Game;

class VillageNames
{
    private const PREFIXES = [
        'Wobbly', 'Bumble', 'Sleepy', 'Mossy', 'Pickle', 'Puddle', 'Turnip', 'Noodle',
        'Waffle', 'Biscuit', 'Muffin', 'Pudding', 'Crumpet', 'Custard', 'Pumpkin', 'Potato',
        'Cabbage', 'Carrot', 'Radish', 'Parsnip', 'Onion', 'Garlic', 'Bean', 'Pea',
        'Apple', 'Pear', 'Plum', 'Peach', 'Berry', 'Cherry', 'Lemon', 'Acorn',
        'Chestnut', 'Walnut', 'Hazel', 'Clover', 'Daisy', 'Dandelion', 'Buttercup', 'Thistle',
        'Bramble', 'Briar', 'Fern', 'Willow', 'Birch', 'Maple', 'Oak', 'Pine',
        'Goose', 'Duck', 'Otter', 'Badger', 'Hedgehog', 'Sparrow', 'Robin', 'Beetle',
        'Snail', 'Turtle', 'Frog', 'Toad', 'Bunny', 'Fluffy', 'Wonky', 'Tiny',
    ];

    private const SUFFIXES = [
        'Hollow', 'Hamlet', 'Village', 'Valley', 'Meadow', 'Grove', 'Brook', 'Creek',
        'Pond', 'Lake', 'River', 'Spring', 'Falls', 'Ford', 'Bridge', 'Mill',
        'Hill', 'Knoll', 'Ridge', 'Peak', 'Bluff', 'Cliff', 'Moor', 'Heath',
        'Marsh', 'Fen', 'Bog', 'Dell', 'Dale', 'Glen', 'Glade', 'Wood',
        'Forest', 'Thicket', 'Copse', 'Orchard', 'Garden', 'Field', 'Pasture', 'Green',
        'Common', 'Downs', 'Heights', 'Crossing', 'Corner', 'Bend', 'Reach', 'Rest',
        'Haven', 'Harbor', 'Cove', 'Bay', 'Beach', 'Shore', 'Point', 'Isle',
        'Landing', 'Wharf', 'Port', 'Gate', 'Market', 'Square', 'Lane', 'End',
    ];

    /** @return list<string> */
    public function all(): array
    {
        $names = [];
        foreach (self::PREFIXES as $prefix) {
            foreach (self::SUFFIXES as $suffix) {
                $names[] = $prefix.' '.$suffix;
            }
        }

        return $names;
    }

    /** @param list<string> $taken */
    public function pick(array $taken = [], ?string $seed = null): string
    {
        $taken = array_fill_keys(array_map(mb_strtolower(...), $taken), true);
        $available = array_values(array_filter($this->all(), fn (string $name): bool => ! isset($taken[mb_strtolower($name)])));
        if ($available === []) {
            throw new \LogicException('No village names available.');
        }
        // Existing seats use their random ID so read-only views agree before the next save.
        $index = $seed === null ? random_int(0, count($available) - 1) : hexdec(substr(hash('sha256', $seed), 0, 7)) % count($available);

        return $available[$index];
    }
}
