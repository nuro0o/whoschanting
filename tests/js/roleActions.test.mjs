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
        'Mara was targeted by at least one other player.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'visits', visited: false }),
        'Mara was not targeted by any other player.',
    );
    assert.equal(
        privateResultText({ ...result, kind: 'protection' }),
        'You protected Mara from new curses.',
    );
});
