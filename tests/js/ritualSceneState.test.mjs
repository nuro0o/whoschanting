import assert from 'node:assert/strict';
import test from 'node:test';
import { ritualSceneState } from '../../resources/js/lib/ritualSceneState.ts';

const scene = (phase, tokens = 0, threshold = 10, winner = null) =>
    ritualSceneState({ phase, tokens, threshold, winner });

await test('new gatherings clear the previous summoning, including stale winner data', () => {
    for (const phase of ['lobby', 'reveal']) {
        const state = scene(phase, 12, 12, 'cult');
        assert.equal(state.progress, 0);
        assert.equal(state.litRunes, 0);
        assert.equal(state.cracks, 0);
        assert.equal(state.mist, 0);
        assert.equal(state.emergence, 0);
        assert.equal(state.night, false);
    }
});

await test('summoning strength scales with the public ritual ratio across room sizes', () => {
    for (const threshold of [2, 3, 4, 6, 8, 9, 10, 11, 12]) {
        const midpoint = scene('night', threshold / 2, threshold);
        const reference = scene('night', 5, 10);
        for (const key of ['progress', 'cracks', 'mist', 'emergence']) {
            assert.equal(
                midpoint[key],
                reference[key],
                `${key} at goal ${threshold}`,
            );
        }
        let previous = scene('night', 0, threshold);
        assert.equal(previous.runeCount, threshold);
        assert.equal(previous.emergence, 0);
        for (let tokens = 1; tokens <= threshold; tokens++) {
            const next = scene('night', tokens, threshold);
            assert.equal(next.litRunes, tokens);
            for (const key of [
                'progress',
                'cracks',
                'mist',
                'emergence',
                'litRunes',
            ]) {
                assert.ok(
                    next[key] >= previous[key],
                    `${key} decreased at ${tokens}/${threshold}`,
                );
            }
            previous = next;
        }
        assert.equal(previous.progress, 1);
        assert.equal(previous.litRunes, previous.runeCount);
    }
});

await test('the full ritual keeps final discussion and voting ominous without announcing cult victory', () => {
    for (const phase of ['discussion', 'voting']) {
        assert.equal(scene(phase, 9).night, false);
        const finalVote = scene(phase, 10);
        assert.equal(finalVote.night, true);
        assert.ok(finalVote.emergence > 0);
        assert.ok(finalVote.emergence < 1);
        // A stray winner value on an active phase cannot trigger the victory effect.
        assert.deepEqual(scene(phase, 10, 10, 'cult'), finalVote);
    }
    assert.equal(scene('night', 0).night, true);
    assert.equal(scene('finished', 10, 10, 'cult').emergence, 1);
});

await test('a Town win seals a full ritual; a Cult win can emerge even before the goal', () => {
    const town = scene('finished', 10, 10, 'town');
    assert.equal(town.calmed, true);
    assert.equal(town.night, false);
    assert.equal(town.cracks, 0);
    assert.equal(town.mist, 0);
    assert.equal(town.emergence, 0);
    // Existing game rules also allow a Cult win with one survivor on each team.
    const cult = scene('finished', 1, 10, 'cult');
    assert.equal(cult.calmed, false);
    assert.equal(cult.emergence, 1);
});

await test('invalid progress stays dormant and extreme goals cannot allocate unlimited runes', () => {
    for (const threshold of [0, -1, NaN, Infinity, undefined, null]) {
        const state = scene('night', 5, threshold);
        // Explicit undefined would receive this test helper's default, so invoke directly.
        const result =
            threshold === undefined
                ? ritualSceneState({
                      phase: 'night',
                      tokens: 5,
                      threshold,
                      winner: null,
                  })
                : state;
        assert.equal(result.progress, 0);
        assert.equal(result.emergence, 0);
        assert.ok(Number.isFinite(result.runeCount));
        assert.ok(result.runeCount >= 3 && result.runeCount <= 24);
    }
    for (const tokens of [NaN, Infinity, -Infinity]) {
        assert.equal(scene('night', tokens).progress, 0);
    }
    assert.equal(scene('night', -3).progress, 0);
    assert.equal(scene('night', 99).progress, 1);
    assert.ok(scene('night', 10000, 10000).runeCount <= 24);
});

await test('repeated public snapshots are stable and private role data cannot alter scenery', () => {
    const input = Object.freeze({
        phase: 'voting',
        tokens: 8,
        threshold: 10,
        winner: null,
    });
    const expected = ritualSceneState(input);
    assert.deepEqual(ritualSceneState(input), expected);
    assert.deepEqual(
        ritualSceneState({
            ...input,
            role: 'veilweaver',
            alignment: 'cult',
            mission: 'concord',
        }),
        expected,
    );
    assert.deepEqual(
        ritualSceneState({
            ...input,
            role: 'oracle',
            alignment: 'town',
            mission: null,
        }),
        expected,
    );
});
