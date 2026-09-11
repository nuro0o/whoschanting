import assert from 'node:assert/strict';
import test from 'node:test';
import {
    eligibleTargets,
    privateResultText,
    nightActionLabel,
} from '../../resources/js/lib/roleActions.ts';

const state = {
    phase: 'night',
    me: { id: 'me', role: 'warden', previous_protection_target: 'previous' },
    players: [
        { id: 'me', alive: true },
        { id: 'previous', alive: true },
        { id: 'neighbor', alive: true },
        { id: 'banished', alive: false },
    ],
};

await test('protection cooldown filters night targets without removing voting choices', () => {
    assert.deepEqual(
        eligibleTargets(state).map((p) => p.id),
        ['neighbor'],
    );
    assert.deepEqual(
        eligibleTargets({ ...state, phase: 'voting' }).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    assert.deepEqual(
        eligibleTargets({
            ...state,
            me: { ...state.me, previous_protection_target: null },
        }).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    assert.deepEqual(
        eligibleTargets({
            ...state,
            me: { ...state.me, role: 'lamplighter' },
        }).map((p) => p.id),
        ['previous', 'neighbor'],
    );
});

await test('limited abilities switch target pools only when selected and available', () => {
    const medium = {
        ...state,
        me: { id: 'me', role: 'medium', ability_used: false },
    };
    assert.deepEqual(eligibleTargets(medium), []);
    assert.deepEqual(
        eligibleTargets(medium, true).map((p) => p.id),
        ['banished'],
    );
    assert.deepEqual(
        eligibleTargets(
            { ...medium, me: { ...medium.me, ability_used: true } },
            true,
        ),
        [],
    );
    assert.deepEqual(
        eligibleTargets({ ...medium, phase: 'voting' }, true).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    const dream = {
        ...medium,
        me: { ...medium.me, role: 'dreamweaver', alignment: 'cult' },
    };
    assert.deepEqual(eligibleTargets(dream), []);
    assert.deepEqual(
        eligibleTargets(dream, true).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    assert.equal(nightActionLabel(dream, false, null), 'Chant for the ritual');
    assert.equal(
        nightActionLabel(dream, true, 'previous'),
        'Disrupt instead of chanting',
    );
    assert.equal(
        nightActionLabel(medium, true, 'banished'),
        'Contact the banished player',
    );
    const bell = { ...medium, me: { ...medium.me, role: 'bellkeeper' } };
    assert.deepEqual(eligibleTargets(bell, true), []);
    assert.equal(nightActionLabel(bell, true, null), 'Ring the bell');
    assert.equal(nightActionLabel(bell, false, null), 'Keep watch tonight');
});

await test('Oracle can save its investigation and cannot target at night after spending it', () => {
    const oracle = {
        ...state,
        me: {
            id: 'me',
            role: 'oracle',
            alignment: 'town',
            ability_used: false,
        },
    };
    assert.deepEqual(eligibleTargets(oracle), []);
    assert.equal(nightActionLabel(oracle, false, null), 'Keep watch tonight');
    assert.deepEqual(
        eligibleTargets(oracle, true).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    assert.equal(
        nightActionLabel(oracle, true, 'neighbor'),
        'Confirm investigation',
    );
    const spent = { ...oracle, me: { ...oracle.me, ability_used: true } };
    assert.deepEqual(eligibleTargets(spent, true), []);
    assert.deepEqual(
        eligibleTargets({ ...spent, phase: 'voting' }).map((p) => p.id),
        ['previous', 'neighbor'],
    );
});

await test('private ability results distinguish true alignment, failed actions, and prevented progress', () => {
    const result = { day: 2, target: 'Mara' };
    assert.equal(
        privateResultText({ ...result, kind: 'spirit', alignment: 'cult' }),
        "Mara's true alignment was cult.",
    );
    assert.match(
        privateResultText({ ...result, kind: 'disrupted' }),
        /night action was disrupted/,
    );
    assert.equal(
        privateResultText({ ...result, kind: 'disruption' }),
        'You sent a disruption to Mara instead of chanting.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'bell', prevented: 1 }),
        'Your bell prevented 1 ritual step.',
    );
    assert.match(
        privateResultText({ ...result, kind: 'bell', prevented: 0 }),
        /no ritual step could be prevented/,
    );
});

await test('legacy Oracle results and new observations have distinct explanations', () => {
    const result = { day: 1, target: 'Mara' };
    assert.equal(
        privateResultText({ ...result, alignment: 'cult' }),
        'Mara appeared cult.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'visits', visited: true }),
        'At least one other player was seen targeting Mara.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'visits', visited: false }),
        'No other player was seen targeting Mara.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'protection' }),
        'You protected Mara from new curses.',
    );
});

await test('illusion abilities require opting in and retain living targets; day roles keep watch', () => {
    for (const role of ['phantasm', 'counterfeiter']) {
        const current = {
            ...state,
            me: { id: 'me', role, alignment: 'cult', ability_used: false },
        };
        assert.deepEqual(eligibleTargets(current), []);
        assert.deepEqual(
            eligibleTargets(current, true).map((p) => p.id),
            ['previous', 'neighbor'],
        );
        assert.match(
            nightActionLabel(current, true, 'neighbor'),
            /instead of chanting/,
        );
        current.me.ability_used = true;
        assert.deepEqual(eligibleTargets(current, true), []);
    }
    assert.equal(
        nightActionLabel(
            { ...state, me: { role: 'exorcist', alignment: 'town' } },
            false,
            null,
        ),
        'Keep watch tonight',
    );
    assert.match(
        privateResultText({
            day: 1,
            kind: 'forgery',
            target: 'Neighbor',
            alignment: 'cult',
        }),
        /appear cult/,
    );
    assert.match(
        privateResultText({
            day: 1,
            kind: 'oath',
            target: 'Neighbor',
            kept: false,
        }),
        /No protection/,
    );
});

await test('Tracker and Herbalist controls choose only their legal action targets', () => {
    const tracker = { ...state, me: { id: 'me', role: 'tracker' } };
    assert.deepEqual(
        eligibleTargets(tracker).map((p) => p.id),
        ['previous', 'neighbor'],
    );
    assert.equal(
        nightActionLabel(tracker, false, 'neighbor'),
        'Confirm tracking',
    );
    const herbalist = { ...state, me: { id: 'me', role: 'herbalist' } };
    assert.deepEqual(eligibleTargets(herbalist, true), []);
    assert.equal(
        nightActionLabel(herbalist, true, null),
        'Protect the village',
    );
    assert.match(
        privateResultText({
            kind: 'tracking',
            day: 1,
            target: 'Mara',
            visited_target: 'Jane',
        }),
        /Mara was seen targeting Jane/,
    );
    assert.match(
        privateResultText({
            kind: 'tracking',
            day: 1,
            target: 'Mara',
            visited_target: null,
        }),
        /No outgoing visit was visible/,
    );
    assert.match(
        privateResultText({ kind: 'herbs', day: 1, target: 'The village' }),
        /all new curses/,
    );
});
