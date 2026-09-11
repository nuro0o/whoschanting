import assert from 'node:assert/strict';
import test from 'node:test';
import {
    ringDirection,
    ringTurn,
    rotateCurseRing,
    selectCurseObject,
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
