import assert from 'node:assert/strict';
import test from 'node:test';
import {
    eligibleTargets,
    privateResultText,
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
