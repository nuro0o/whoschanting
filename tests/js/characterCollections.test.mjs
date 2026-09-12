import assert from 'node:assert/strict';
import test from 'node:test';
import { characterCollections } from '../../resources/js/lib/characterCollections.ts';

test('collections retain custom, locked, and hidden characters and separate named seasons', () => {
    const characters = [
        { id: 'custom', name: 'Your creation' },
        { id: 'mariner', name: 'The Mariner', collection: 'classics' },
        {
            id: 'maskmaker',
            name: 'The Maskmaker',
            collection: 'levelup',
            unlocked: false,
        },
        {
            id: 'warden',
            name: '?',
            seasonal: true,
            season_id: '2026-Q3',
            season_name: 'Season 3 · 2026',
            hidden: true,
            unlocked: false,
        },
        {
            id: 'winter',
            name: '?',
            seasonal: true,
            season_id: '2026-Q4',
            season_name: 'The Long Night',
            hidden: true,
        },
        {
            id: 'winter_friend',
            name: 'Friend',
            season_id: '2026-Q4',
            season_name: 'The Long Night',
            unlocked: true,
        },
    ];
    const groups = characterCollections(characters, []);
    assert.deepEqual(
        groups.map(({ id, name }) => [id, name]),
        [
            ['custom', 'Made in the looking glass'],
            ['classics', 'Classics'],
            ['levelup', 'Level Up'],
            ['season:2026-Q3', 'Season 3 · 2026'],
            ['season:2026-Q4', 'The Long Night'],
        ],
    );
    assert.deepEqual(
        groups.find(({ id }) => id === 'season:2026-Q4').characters,
        characters.slice(4),
    );
    assert.equal(
        groups.find(({ id }) => id === 'levelup').characters[0],
        characters[2],
    );
    assert.equal(
        groups.find(({ id }) => id === 'season:2026-Q3').characters[0],
        characters[3],
    );
    assert.equal(
        groups.flatMap(({ characters }) => characters).length,
        characters.length,
    );
});

test('legacy fallback excludes unknown ids from classics and keeps seasonal characters separate', () => {
    const legacyIds = [
        ...Array.from({ length: 16 }, (_, i) => `classic_${i}`),
        'earned',
    ];
    const groups = characterCollections(
        [
            { id: 'classic_0', name: 'Classic' },
            { id: 'earned', name: 'Earned' },
            { id: 'new_character', name: 'New' },
            { id: 'legacy_seasonal', name: '?', seasonal: true },
        ],
        legacyIds,
    );
    assert.deepEqual(
        groups
            .find(({ id }) => id === 'classics')
            .characters.map(({ id }) => id),
        ['classic_0'],
    );
    assert.deepEqual(
        groups
            .find(({ id }) => id === 'levelup')
            .characters.map(({ id }) => id),
        ['earned'],
    );
    assert.equal(
        groups.find(({ id }) => id === 'other').characters[0].id,
        'new_character',
    );
    assert.equal(
        groups.find(({ id }) => id === 'season:legacy').name,
        'Seasonal characters',
    );
});
