import assert from 'node:assert/strict';
import test from 'node:test';
import {
    actionConsequence,
    newPublicEvents,
    lobbyBlockers,
} from '../../resources/js/lib/gameUx.ts';

const state = {
    phase: 'night',
    me: {
        id: 'me',
        role: 'oracle',
        alive: true,
        alignment: 'town',
        ability_used: false,
    },
    players: [
        { id: 'me', name: 'You', ready: true },
        { id: 'mara', name: 'Mara', ready: false },
    ],
    rules: { min_players: 3 },
};
const choice = {
    target: 'mara',
    useAbility: true,
    curse: 'puzzle',
    forgedAlignment: 'cult',
};

test('once-only ability consequence explains commitment without inventing an outcome', () => {
    const text = actionConsequence(state, choice);
    assert.match(text, /Investigate Mara/);
    assert.match(text, /once-per-match ability, even if disrupted/);
    assert.doesNotMatch(text, /Mara is|Mara appeared/);
    assert.doesNotMatch(
        actionConsequence(state, { ...choice, useAbility: false }),
        /Uses your once/,
    );
    assert.match(
        actionConsequence(
            { ...state, me: { ...state.me, role: 'vigilante' } },
            choice,
        ),
        /you also leave the village in guilt/,
    );
});

test('Misdirection warns for targeted votes and nights, but not abstention', () => {
    const cursed = {
        ...state,
        me: { ...state.me, curse: { type: 'misdirection' } },
    };
    assert.match(actionConsequence(cursed, choice), /may redirect this target/);
    assert.match(
        actionConsequence({ ...cursed, phase: 'voting' }, choice),
        /may redirect this target/,
    );
    assert.doesNotMatch(
        actionConsequence(
            { ...cursed, phase: 'voting' },
            { ...choice, target: null },
        ),
        /redirect/,
    );
});

test('catch-up finds new events when server trims rolling history', () => {
    const previous = Array.from({ length: 100 }, (_, i) => `Event ${i}`);
    const current = [...previous.slice(2), 'Dawn', 'Ritual +1'];
    assert.deepEqual(newPublicEvents(previous, current), ['Dawn', 'Ritual +1']);
    assert.deepEqual(newPublicEvents(current, current), []);
    assert.deepEqual(newPublicEvents([], ['Start']), ['Start']);
    assert.deepEqual(newPublicEvents(['Old match'], ['New match']), [
        'New match',
    ]);
});

test('lobby reports all concrete blockers, including an invalid custom cast', () => {
    const blocked = lobbyBlockers(
        { ...state, mode_preview: { error: 'This cast needs 4 players.' } },
        true,
    );
    assert.equal(blocked.length, 4);
    assert.match(blocked.join(' '), /Invite 1 more player/);
    assert.match(blocked.join(' '), /needs 4 players/);
    assert.match(blocked.join(' '), /Apply or cancel/);
    assert.match(blocked.join(' '), /Mara/);
    assert.deepEqual(
        lobbyBlockers(
            {
                ...state,
                rules: { min_players: 2 },
                players: state.players.map((p) => ({ ...p, ready: true })),
            },
            false,
        ),
        [],
    );
});
