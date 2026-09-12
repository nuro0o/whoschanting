import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRecapStory } from '../../resources/js/lib/recapStory.ts';

const players = [
    { id: 'a', name: 'Ada', role: 'counterfeiter' },
    { id: 'b', name: 'Bo', role: 'oracle' },
    { id: 'c', name: 'Cam', role: 'bellkeeper' },
];
const base = { rounds: [], ritual_goal: 5 };

test('the reveal uses recorded forgeries and prevented steps without assigning unrecorded blame', () => {
    const story = buildRecapStory(
        {
            ...base,
            rounds: [
                {
                    day: 1,
                    night: {
                        gained: 0,
                        tokens: 3,
                        actions: [
                            {
                                player_id: 'b',
                                role: 'oracle',
                                target_id: 'a',
                                submitted: true,
                                forged: true,
                                apparent_alignment: 'town',
                            },
                            {
                                player_id: 'c',
                                role: 'bellkeeper',
                                submitted: true,
                                used_ability: true,
                                prevented_steps: 1,
                            },
                            {
                                player_id: 'a',
                                role: 'counterfeiter',
                                submitted: true,
                                disrupted: true,
                            },
                        ],
                    },
                },
            ],
        },
        players,
    );
    assert.match(story[0].body, /gained 0 ritual steps.*3 of 5/);
    assert.match(story[0].details[0], /Bo read Ada as Town.*forged/);
    assert.match(story[0].details[1], /Cam.*prevented 1 ritual step/);
    assert.match(story[0].details[2], /Ada was disrupted/);
});

test('a disrupted Oracle or Bellkeeper cannot produce a successful reveal highlight', () => {
    const story = buildRecapStory(
        {
            ...base,
            rounds: [
                {
                    day: 1,
                    night: {
                        gained: 1,
                        tokens: 1,
                        actions: [
                            {
                                player_id: 'b',
                                role: 'oracle',
                                submitted: true,
                                disrupted: true,
                                forged: true,
                            },
                            {
                                player_id: 'c',
                                role: 'bellkeeper',
                                submitted: true,
                                disrupted: true,
                                used_ability: true,
                                prevented_steps: 1,
                            },
                        ],
                    },
                },
            ],
        },
        players,
    );
    assert.ok(
        story[0].details.every((detail) => detail.includes('was disrupted')),
    );
});

test('role claims compare only revealed roles, including claims from a day without a completed night', () => {
    const story = buildRecapStory(
        {
            ...base,
            claims: [
                {
                    player_id: 'a',
                    day: 2,
                    role: 'warden',
                    body: 'I protected Bo.',
                },
                {
                    player_id: 'b',
                    day: 2,
                    role: 'oracle',
                    body: 'My reading was town.',
                },
                {
                    player_id: 'missing',
                    day: 2,
                    role: 'warden',
                    body: 'I was watching.',
                },
            ],
        },
        players,
    );
    assert.equal(story[0].title, '1 role claim did not match');
    assert.match(story[0].details[0], /Counterfeiter \(mismatch\)/);
    assert.match(story[0].details[1], /Oracle \(matches\)/);
    assert.match(story[0].details[2], /unavailable/);
    assert.doesNotMatch(JSON.stringify(story), /lied|liar/);
});

test('ballots retain abstentions and redirected choices; outcome uses the actual match winner', () => {
    const story = buildRecapStory(
        {
            ...base,
            winner: 'cult',
            win_reason: 'The ritual is complete.',
            rounds: [
                {
                    day: 2,
                    vote: {
                        banished_id: 'c',
                        ballots: [
                            {
                                player_id: 'a',
                                target_id: 'c',
                                chosen_target_id: 'b',
                                submitted: true,
                            },
                            {
                                player_id: 'b',
                                target_id: null,
                                submitted: false,
                            },
                            {
                                player_id: 'c',
                                target_id: null,
                                submitted: true,
                            },
                        ],
                    },
                },
            ],
        },
        players,
    );
    assert.match(story[0].label, /Last recorded vote/);
    assert.match(story[0].details[0], /voted for Cam.*choice from Bo/);
    assert.match(story[0].details[1], /missed the deadline/);
    assert.equal(story[0].details[2], 'Cam abstained.');
    assert.equal(story[1].title, 'The cult prevailed');
    assert.equal(story[1].body, 'The ritual is complete.');
});

test('legacy empty recaps and unordered rounds produce useful stable steps without mutating source', () => {
    assert.equal(buildRecapStory(base, [])[0].id, 'unrecorded');
    const recap = {
        ...base,
        rounds: [3, 1].map((day) => ({
            day,
            night: { gained: 0, tokens: 0, actions: [] },
        })),
    };
    assert.deepEqual(
        buildRecapStory(recap, []).map((step) => step.id),
        ['night-1', 'night-3'],
    );
    assert.deepEqual(
        recap.rounds.map((round) => round.day),
        [3, 1],
    );
});
