import assert from 'node:assert/strict';
import test from 'node:test';
import { recapHighlights } from '../../resources/js/lib/recapHighlights.ts';
const players = [
    { id: 'a', name: 'Mara', alignment: 'town' },
    { id: 'b', name: 'Ivo', alignment: 'cult' },
];
const base = { rounds: [], ritual_goal: 3 };
const action = {
    player_id: 'a',
    role: 'oracle',
    target_id: 'b',
    submitted: true,
    apparent_alignment: 'town',
};
test('only recorded successful effects and revealed alignments become highlights', () => {
    const recap = {
        ...base,
        rounds: [
            {
                day: 1,
                night: {
                    gained: 0,
                    tokens: 0,
                    actions: [
                        action,
                        {
                            ...action,
                            player_id: 'b',
                            role: 'bellkeeper',
                            used_ability: true,
                            prevented_steps: 2,
                            disrupted: true,
                        },
                    ],
                },
            },
        ],
    };
    const highlights = recapHighlights(recap, players);
    assert.equal(highlights.length, 1);
    assert.match(
        highlights[0].detail,
        /read Ivo as town; their revealed alignment was cult/,
    );
    assert.equal(
        recapHighlights(
            recap,
            players.map((p) => ({ ...p, alignment: null })),
        ).length,
        0,
    );
    assert.equal(
        recapHighlights(
            {
                ...recap,
                rounds: [
                    {
                        day: 1,
                        night: {
                            gained: 0,
                            tokens: 0,
                            actions: [{ ...action, submitted: false }],
                        },
                    },
                ],
            },
            players,
        ).length,
        0,
    );
});
test('uses effective submitted ballots and chooses the final banishment', () => {
    const recap = {
        ...base,
        rounds: [1, 2].map((day) => ({
            day,
            vote: {
                banished_id: 'b',
                ballots: [
                    { submitted: true, target_id: 'b', chosen_target_id: 'a' },
                    { submitted: false, target_id: 'b' },
                    { submitted: true, target_id: null },
                ],
            },
        })),
    };
    const highlights = recapHighlights(recap, players);
    assert.equal(highlights.length, 1);
    assert.equal(highlights[0].day, 2);
    assert.equal(highlights[0].detail, '1 recorded ballot named Ivo.');
});
test('records the first ritual goal crossing and actual bell prevention without mutating history', () => {
    const rounds = [
        { day: 2, night: { tokens: 4, gained: 1, actions: [] } },
        {
            day: 1,
            night: {
                tokens: 3,
                gained: 3,
                actions: [
                    {
                        ...action,
                        role: 'bellkeeper',
                        used_ability: true,
                        prevented_steps: 1,
                    },
                ],
            },
        },
    ];
    const highlights = recapHighlights({ ...base, rounds }, players);
    assert.equal(highlights.find((h) => h.id.startsWith('ritual')).day, 1);
    assert.match(
        highlights.find((h) => h.id.startsWith('bell')).detail,
        /prevented 1 ritual step/,
    );
    assert.equal(rounds[0].day, 2);
    assert.deepEqual(recapHighlights(base, players), []);
});
