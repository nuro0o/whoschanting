import assert from 'node:assert/strict';
import test from 'node:test';
import {
    disturbanceLevel,
    RitualDisturbanceDirector,
} from '../../resources/js/lib/ritualDisturbances.ts';

const context = {
    phase: 'discussion',
    phaseId: 4,
    tokens: 8,
    threshold: 10,
    seconds: 80,
    allowed: true,
    reducedMotion: false,
};

await test('public progress increases disturbance levels and invalid values stay quiet', () => {
    for (const [tokens, level] of [
        [0, 0],
        [1, 0],
        [2, 1],
        [5, 2],
        [8, 3],
        [10, 3],
    ])
        assert.equal(disturbanceLevel(tokens, 10), level);
    for (const threshold of [0, -1, Infinity, NaN])
        assert.equal(disturbanceLevel(5, threshold), 0);
    assert.equal(disturbanceLevel(NaN, 10), 0);
});

await test('polling cannot restart or stack a disturbance, and higher rituals shorten the wait', () => {
    for (const [tokens, wait] of [
        [2, 28000],
        [5, 18000],
        [8, 10000],
    ]) {
        const director = new RitualDisturbanceDirector(() => 0);
        const state = { ...context, tokens };
        assert.equal(director.update(state, 0), null);
        assert.equal(director.update(state, wait - 1), null);
        const event = director.update(state, wait);
        assert.ok(event);
        assert.equal(director.update(state, wait + 200), event);
        assert.equal(director.update(state, event.expiresAt), null);
        const next = director.update(state, event.expiresAt + wait);
        assert.ok(next.id > event.id);
        assert.notEqual(next.kind, event.kind);
    }
});

await test('disabled, inactive, blocked, and deadline states clear events without replay', () => {
    for (const change of [
        { allowed: false },
        { phase: 'lobby' },
        { phase: 'reveal' },
        { phase: 'finished' },
        { seconds: 10 },
        { seconds: 0 },
        { seconds: null },
        { tokens: 0 },
    ]) {
        const director = new RitualDisturbanceDirector(() => 0);
        director.update(context, 0);
        assert.ok(director.update(context, 10000));
        assert.equal(director.update({ ...context, ...change }, 10100), null);
        assert.equal(director.update(context, 20000), null);
        assert.equal(director.update(context, 29999), null);
        assert.ok(director.update(context, 30000));
    }
});

await test('phase changes, reduced motion changes, and delayed callbacks discard stale effects', () => {
    const director = new RitualDisturbanceDirector(() => 0.8);
    director.update(context, 0);
    assert.equal(director.update(context, 90000), null);
    assert.equal(director.update({ ...context, phaseId: 5 }, 90001), null);
    const still = { ...context, phaseId: 5, reducedMotion: true };
    assert.equal(director.update(still, 90002), null);
    for (let time = 90002; time < 300000; time += 250) {
        const event = director.update(still, time);
        if (event) assert.ok(!['chat', 'shadow'].includes(event.kind));
    }
});

await test('secret role, player messages and identities cannot affect disturbances', () => {
    const first = new RitualDisturbanceDirector(() => 0.5);
    const second = new RitualDisturbanceDirector(() => 0.5);
    for (const time of [0, 14000, 14250]) {
        assert.deepEqual(
            first.update(
                { ...context, role: 'oracle', messages: ['innocent'] },
                time,
            ),
            second.update(
                { ...context, role: 'acolyte', messages: ['cultist'] },
                time,
            ),
        );
    }
});
