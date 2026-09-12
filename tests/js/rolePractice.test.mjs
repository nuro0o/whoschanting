import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createRolePractice,
    resolvePracticeBell,
    resolvePracticeForgery,
    resolvePracticeTracking,
    rolePracticeReducer,
} from '../../resources/js/lib/rolePractice.ts';

function reveal(role, choice) {
    return rolePracticeReducer(
        rolePracticeReducer(createRolePractice(role), {
            type: 'choose',
            choice,
        }),
        { type: 'confirm' },
    );
}

test('forgery sets either apparent alignment without changing allegiance, and costs the chant', () => {
    for (const alignment of ['town', 'cult']) {
        const result = resolvePracticeForgery(alignment);
        assert.equal(result.apparent, alignment);
        assert.equal(result.actual, 'town');
        assert.equal(result.chanting, false);
        assert.equal(result.abilitySpent, true);
    }
});

test('tracking preserves disrupted attempts while concealment and untargeted actions look identical', () => {
    const disrupted = resolvePracticeTracking('mara');
    assert.equal(disrupted.disrupted, true);
    assert.equal(disrupted.visible, 'Nell');
    const hidden = resolvePracticeTracking('ivo');
    assert.equal(hidden.attempted, 'Bram');
    assert.equal(hidden.concealed, true);
    assert.equal(hidden.visible, null);
    const idle = resolvePracticeTracking('nell');
    assert.equal(idle.attempted, null);
    assert.equal(idle.visible, hidden.visible);
});

test('a bell delays the final vote against one earned step but cannot stop two', () => {
    assert.deepEqual(resolvePracticeBell(true, 1), {
        prevented: 1,
        tokens: 5,
        finalVote: false,
        abilitySpent: true,
    });
    assert.deepEqual(resolvePracticeBell(false, 1), {
        prevented: 0,
        tokens: 6,
        finalVote: true,
        abilitySpent: false,
    });
    assert.deepEqual(resolvePracticeBell(true, 2), {
        prevented: 1,
        tokens: 6,
        finalVote: true,
        abilitySpent: true,
    });
});

test('ringing on a quiet night or while disrupted spends the charge without removing progress', () => {
    assert.deepEqual(resolvePracticeBell(true, 0), {
        prevented: 0,
        tokens: 5,
        finalVote: false,
        abilitySpent: true,
    });
    assert.deepEqual(resolvePracticeBell(true, 1, true), {
        prevented: 0,
        tokens: 6,
        finalVote: true,
        abilitySpent: true,
    });
    assert.equal(resolvePracticeBell(false, 0).abilitySpent, false);
});

test('all role choices require confirmation, permit changing selections, and freeze after reveal', () => {
    for (const [role, first, second] of [
        ['counterfeiter', 'town', 'cult'],
        ['tracker', 'mara', 'ivo'],
        ['bellkeeper', 'ring', 'save'],
    ]) {
        const start = createRolePractice(role);
        assert.equal(rolePracticeReducer(start, { type: 'confirm' }), start);
        assert.equal(
            rolePracticeReducer(start, { type: 'choose', choice: 'invalid' }),
            start,
        );
        let state = rolePracticeReducer(start, {
            type: 'choose',
            choice: first,
        });
        state = rolePracticeReducer(state, { type: 'choose', choice: second });
        assert.equal(state.step, 'choose');
        assert.equal(state.choice, second);
        assert.equal(start.choice, null);
        state = rolePracticeReducer(state, { type: 'confirm' });
        assert.equal(state.step, 'reveal');
        assert.equal(
            rolePracticeReducer(state, { type: 'choose', choice: first }),
            state,
        );
        assert.equal(rolePracticeReducer(state, { type: 'confirm' }), state);
    }
});

test('tracking interpretations cannot be skipped; mistaken certainty can be corrected', () => {
    const start = createRolePractice('tracker');
    assert.equal(
        rolePracticeReducer(start, { type: 'interpret', certain: false }),
        start,
    );
    for (const target of ['mara', 'ivo', 'nell']) {
        const revealed = reveal('tracker', target);
        const wrong = rolePracticeReducer(revealed, {
            type: 'interpret',
            certain: true,
        });
        assert.equal(wrong.step, 'reveal');
        assert.ok(wrong.feedback.length > 0);
        const correct = rolePracticeReducer(wrong, {
            type: 'interpret',
            certain: false,
        });
        assert.equal(correct.step, 'complete');
        assert.equal(
            rolePracticeReducer(correct, { type: 'interpret', certain: true }),
            correct,
        );
    }
});

test('bell setup changes before confirmation only and restarting clears all prior choices', () => {
    const start = createRolePractice('bellkeeper');
    const changed = rolePracticeReducer(start, {
        type: 'set-earned',
        earned: 2,
    });
    assert.equal(changed.earned, 2);
    assert.equal(
        rolePracticeReducer(start, { type: 'set-earned', earned: 99 }),
        start,
    );
    const revealed = rolePracticeReducer(
        rolePracticeReducer(changed, { type: 'choose', choice: 'ring' }),
        { type: 'confirm' },
    );
    assert.equal(
        rolePracticeReducer(revealed, { type: 'set-earned', earned: 0 }),
        revealed,
    );
    assert.deepEqual(rolePracticeReducer(revealed, { type: 'restart' }), start);
    for (const [role, choice] of [
        ['counterfeiter', 'cult'],
        ['tracker', 'ivo'],
    ]) {
        const state = reveal(role, choice);
        assert.equal(
            rolePracticeReducer(state, { type: 'set-earned', earned: 2 }),
            state,
        );
        assert.deepEqual(
            rolePracticeReducer(state, { type: 'restart' }),
            createRolePractice(role),
        );
    }
});
