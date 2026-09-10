import assert from 'node:assert/strict';
import test from 'node:test';
import {
    ritualNightStage,
    usesNightAtmosphere,
} from '../../resources/js/lib/ritualAtmosphere.ts';

test('ten-step ritual stays quiet after the first task and advances at each 20% milestone', () => {
    const expected = [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6];
    expected.forEach((stage, tokens) => {
        assert.equal(ritualNightStage(tokens, 10), stage, `${tokens}/10`);
    });
});

test('short rituals skip intermediate artwork in proportion to completion', () => {
    assert.deepEqual(
        [0, 1, 2, 3].map((tokens) => ritualNightStage(tokens, 3)),
        [1, 2, 4, 6],
    );
    assert.deepEqual(
        [0, 1, 2].map((tokens) => ritualNightStage(tokens, 2)),
        [1, 3, 6],
    );
    assert.equal(ritualNightStage(1, 1), 6);
});

test('artwork changes at the milestone, never before, with the final scene reserved for completion', () => {
    const milestones = [20, 40, 60, 80, 100];
    milestones.forEach((percent, index) => {
        assert.equal(ritualNightStage(percent - 0.01, 100), index + 1);
        assert.equal(ritualNightStage(percent, 100), index + 2);
    });
    assert.equal(ritualNightStage(999, 1000), 5);
    assert.equal(ritualNightStage(1000, 1000), 6);
});

test('empty and malformed game state stays quiet and valid progress is clamped', () => {
    for (const threshold of [
        0,
        -1,
        NaN,
        Infinity,
        -Infinity,
        undefined,
        null,
    ]) {
        assert.equal(ritualNightStage(3, threshold), 1);
    }
    for (const tokens of [NaN, Infinity, -Infinity, undefined, null]) {
        assert.equal(ritualNightStage(tokens, 10), 1);
    }
    assert.equal(ritualNightStage(-3, 10), 1);
    assert.equal(ritualNightStage(0, 10), 1);
    assert.equal(ritualNightStage(11, 10), 6);
});

test('normal dawn clears the night, but the final ritual keeps night through discussion and voting', () => {
    assert.equal(usesNightAtmosphere('night', false), true);
    for (const phase of ['discussion', 'voting']) {
        assert.equal(usesNightAtmosphere(phase, false), false);
        assert.equal(usesNightAtmosphere(phase, true), true);
    }
});

test('lobby, role reveal, and the finished game reset the atmosphere even if the final-vote flag remains set', () => {
    for (const phase of ['lobby', 'reveal', 'finished']) {
        assert.equal(usesNightAtmosphere(phase, true), false);
    }
});
