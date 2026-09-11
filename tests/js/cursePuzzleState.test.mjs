import assert from 'node:assert/strict';
import test from 'node:test';
import {
    ringDirection,
    ringTurn,
    rotateCurseRing,
    selectCurseObject,
    nextGuidedLantern,
    isNextCurseSeal,
} from '../../resources/js/lib/cursePuzzleState.ts';

const challenge = {
    kind: 'rings',
    title: '',
    instruction: '',
    clues: [],
    answer_length: 2,
    options: [
        { id: 'a', label: 'A' },
        { id: 'b', label: 'B' },
        { id: 'c', label: 'C' },
    ],
    scene: {
        kind: 'rings',
        rings: [
            { label: 'Inner', start: 3, options: ['i0', 'i1', 'i2', 'i3'] },
            { label: 'Outer', start: 1, options: ['o0', 'o1', 'o2', 'o3'] },
        ],
    },
};

await test('ring interactions initialize every slot and preserve scene order', () => {
    const answer = rotateCurseRing(challenge, [], 1);
    assert.deepEqual(answer, ['i0', 'o1']);
    assert.deepEqual(rotateCurseRing(challenge, answer, 0), ['i1', 'o1']);
    assert.deepEqual(answer, ['i0', 'o1']);
});

await test('ring quarter turns wrap and north corresponds to the visible start offset', () => {
    let answer = [];
    for (let i = 0; i < 4; i++) answer = rotateCurseRing(challenge, answer, 0);
    assert.deepEqual(answer, ['i0', 'o0']);
    assert.equal(ringDirection(3, 1), 'North');
    assert.equal(ringDirection(1, 2), 'West');
});

await test('undo snapshots and clear faithfully restore visual turns', () => {
    const before = rotateCurseRing(challenge, [], 0);
    const after = rotateCurseRing(challenge, before, 1);
    assert.equal(ringTurn(challenge.scene.rings[1], after[1]), 1);
    assert.equal(ringTurn(challenge.scene.rings[1], before[1]), 0);
    assert.equal(ringTurn(challenge.scene.rings[0], undefined), 0);
});

await test('replacement ring IDs cannot carry stale rotation to a new challenge', () => {
    assert.equal(ringTurn(challenge.scene.rings[0], 'old-challenge-id'), 0);
    assert.deepEqual(rotateCurseRing(challenge, ['old-id'], 1), ['i0', 'o1']);
});

await test('objects cannot repeat, exceed the answer length or inject unknown IDs', () => {
    const first = selectCurseObject(challenge, [], 'b');
    assert.deepEqual(first, ['b']);
    assert.equal(selectCurseObject(challenge, first, 'b'), first);
    assert.equal(selectCurseObject(challenge, first, 'unknown'), first);
    const full = selectCurseObject(challenge, first, 'a');
    assert.deepEqual(full, ['b', 'a']);
    assert.equal(selectCurseObject(challenge, full, 'c'), full);
});

const guided = {
    ...challenge,
    kind: 'lanterns',
    answer_length: 3,
    options: [
        { id: 'three', label: '3' },
        { id: 'one', label: '1' },
        { id: 'two', label: '2' },
    ],
    scene: { kind: 'lanterns', difficulty: 1, guided: true },
};

await test('guided lanterns reject out-of-order clicks without losing the correct prefix', () => {
    const empty = [];
    assert.equal(selectCurseObject(guided, empty, 'three'), empty);
    assert.equal(nextGuidedLantern(guided, empty).label, '1');
    const first = selectCurseObject(guided, empty, 'one');
    assert.deepEqual(first, ['one']);
    assert.equal(selectCurseObject(guided, first, 'three'), first);
    assert.equal(nextGuidedLantern(guided, first).label, '2');
    assert.deepEqual(selectCurseObject(guided, first, 'two'), ['one', 'two']);
});

await test('guided lanterns finish with no next hint, and clearing starts from one', () => {
    const answer = ['one', 'two'].reduce(
        (state, id) => selectCurseObject(guided, state, id),
        [],
    );
    const full = selectCurseObject(guided, answer, 'three');
    assert.deepEqual(full, ['one', 'two', 'three']);
    assert.equal(nextGuidedLantern(guided, full), undefined);
    assert.equal(selectCurseObject(guided, full, 'three'), full);
    assert.equal(nextGuidedLantern(guided, []).id, 'one');
});

await test('unguided and legacy lanterns still accept arbitrary order for server checking', () => {
    for (const scene of [
        undefined,
        { kind: 'lanterns' },
        { kind: 'lanterns', difficulty: 2, guided: false },
    ]) {
        const unguided = { ...guided, scene };
        assert.deepEqual(selectCurseObject(unguided, [], 'three'), ['three']);
        assert.equal(nextGuidedLantern(unguided, []), undefined);
    }
});

const firstSeal = {
    id: 'first',
    type: 'mist',
    day: 2,
    level: 1,
    stage: 1,
    stages: 3,
};
await test('new IDs advancing the same curse retain the active seal flow', () => {
    assert.equal(
        isNextCurseSeal(firstSeal, { ...firstSeal, id: 'next', stage: 2 }),
        true,
    );
    assert.equal(
        isNextCurseSeal(firstSeal, { ...firstSeal, id: 'latest', stage: 3 }),
        true,
    );
    assert.equal(isNextCurseSeal(firstSeal, { ...firstSeal }), false);
});

await test('unrelated replacement and legacy curses cannot be mistaken for the next seal', () => {
    for (const changes of [
        { day: 3 },
        { type: 'puzzle' },
        { level: 2 },
        { stages: 2 },
        { stage: 1 },
        { stage: 4 },
    ]) {
        assert.equal(
            isNextCurseSeal(firstSeal, {
                ...firstSeal,
                id: 'new',
                stage: 2,
                ...changes,
            }),
            false,
        );
    }
    const legacy = { ...firstSeal, stage: undefined, stages: undefined };
    assert.equal(isNextCurseSeal(legacy, { ...legacy, id: 'new' }), false);
});
