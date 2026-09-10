import assert from 'node:assert/strict';
import test from 'node:test';
import {
    CoastalAudio,
    SoundCueTracker,
    parseSoundPreferences,
    defaultSoundPreferences,
} from '../../resources/js/lib/coastalAudio.ts';

function audioMock() {
    const contexts = [];
    class Context {
        state = 'suspended';
        currentTime = 0;
        sampleRate = 8000;
        destination = {};
        nodes = [];
        buffers = [];
        constructor() {
            contexts.push(this);
        }
        param() {
            return {
                value: 0,
                setValueAtTime(value) {
                    this.value = value;
                },
                setTargetAtTime(value) {
                    this.value = value;
                },
                linearRampToValueAtTime(value) {
                    this.value = value;
                },
                exponentialRampToValueAtTime(value) {
                    this.value = value;
                },
            };
        }
        node() {
            const node = {
                connect() {},
                disconnect() {
                    this.disconnected = true;
                },
            };
            this.nodes.push(node);
            return node;
        }
        source() {
            return Object.assign(this.node(), {
                start(time = 0) {
                    this.started = time;
                },
                stop(time = 0) {
                    this.stopped = time;
                },
            });
        }
        createGain() {
            return Object.assign(this.node(), { gain: this.param() });
        }
        createBiquadFilter() {
            return Object.assign(this.node(), {
                frequency: this.param(),
                Q: this.param(),
            });
        }
        createOscillator() {
            return Object.assign(this.source(), { frequency: this.param() });
        }
        createBufferSource() {
            return this.source();
        }
        createBuffer(_channels, length) {
            const data = new Float32Array(length);
            this.buffers.push(data);
            return { getChannelData: () => data };
        }
        async resume() {
            this.state = 'running';
            this.onstatechange?.();
        }
        async suspend() {
            this.state = 'suspended';
            this.onstatechange?.();
        }
        async close() {
            this.state = 'closed';
            this.onstatechange?.();
        }
    }
    return { contexts, Context };
}

await test('engine creates nothing until enabled, stops scheduled sources on pause, and closes cleanly', async () => {
    const original = globalThis.AudioContext;
    const { contexts, Context } = audioMock();
    globalThis.AudioContext = Context;
    const statuses = [];
    const engine = new CoastalAudio((active) => statuses.push(active));
    try {
        engine.update(defaultSoundPreferences, 'night');
        engine.play(['night']);
        assert.equal(contexts.length, 0);
        assert.equal(await engine.enable(), true);
        const context = contexts[0];
        const noise = context.buffers[0];
        assert.equal(Math.abs(noise[0]), 0);
        assert.equal(Math.abs(noise.at(-1)), 0);
        engine.play(['seal', 'town']);
        assert.ok(context.nodes.some((node) => node.started > 0));
        engine.pause();
        assert.equal(engine.active, false);
        const sources = context.nodes.filter((node) => node.start);
        assert.ok(
            sources.every((node) => node.stopped === 0 && node.disconnected),
        );
        const sourceCount = sources.length;
        engine.play(['ritual']);
        assert.equal(
            context.nodes.filter((node) => node.start).length,
            sourceCount,
        );
        await engine.enable();
        assert.equal(contexts.length, 1);
        assert.equal(
            context.nodes.filter((node) => node.start).length,
            sourceCount + 2,
        );
        engine.close();
        assert.equal(context.state, 'closed');
        assert.ok(context.nodes.every((node) => node.disconnected));
        assert.equal(statuses.at(-1), false);
    } finally {
        engine.close();
        globalThis.AudioContext = original;
    }
});

await test('effects and ambience can be independently silenced; phase changes retune existing sea', async () => {
    const original = globalThis.AudioContext;
    const { contexts, Context } = audioMock();
    globalThis.AudioContext = Context;
    const engine = new CoastalAudio(() => {});
    try {
        const preferences = { enabled: true, effects: 0, ambience: 25 };
        engine.update(preferences, 'night');
        await engine.enable();
        const context = contexts[0];
        const seaFilter = context.nodes.find((node) => node.Q);
        assert.equal(seaFilter.frequency.value, 650);
        const count = context.nodes.length;
        engine.play(['seal']);
        assert.equal(context.nodes.length, count);
        engine.update(preferences, 'discussion');
        assert.equal(seaFilter.frequency.value, 1050);
        assert.equal(context.nodes[2].gain.value, 0.25 * 0.28);
        engine.update(
            { ...preferences, effects: 45, ambience: 0 },
            'discussion',
        );
        assert.ok(
            context.nodes
                .filter((node) => node.start)
                .every((node) => node.disconnected),
        );
        engine.play(['seal']);
        assert.ok(context.nodes.length > count);
    } finally {
        engine.close();
        globalThis.AudioContext = original;
    }
});

await test('a rejected audio start is handled and closes its context', async () => {
    const original = globalThis.AudioContext;
    const { contexts, Context } = audioMock();
    Context.prototype.resume = () => Promise.reject(new Error('Unavailable'));
    globalThis.AudioContext = Context;
    const engine = new CoastalAudio(() => {});
    try {
        assert.equal(await engine.enable(), false);
        assert.equal(contexts[0].state, 'closed');
        assert.equal(engine.active, false);
    } finally {
        engine.close();
        globalThis.AudioContext = original;
    }
});

await test('pending browser suspension cannot restart ambience during a background state update', async () => {
    const original = globalThis.AudioContext;
    const { contexts, Context } = audioMock();
    Context.prototype.suspend = async () => {};
    globalThis.AudioContext = Context;
    const engine = new CoastalAudio(() => {});
    try {
        await engine.enable();
        const context = contexts[0];
        const count = context.nodes.length;
        engine.pause();
        assert.equal(context.state, 'running');
        assert.equal(engine.active, false);
        engine.update(defaultSoundPreferences, 'night');
        engine.play(['night']);
        assert.equal(context.nodes.length, count);
    } finally {
        engine.close();
        globalThis.AudioContext = original;
    }
});

await test('unmount while browser resume is pending cannot activate sound later', async () => {
    const original = globalThis.AudioContext;
    const { contexts, Context } = audioMock();
    let finishResume;
    Context.prototype.resume = () =>
        new Promise((resolve) => {
            finishResume = resolve;
        });
    globalThis.AudioContext = Context;
    const engine = new CoastalAudio(() => {});
    try {
        const started = engine.enable();
        engine.close();
        finishResume();
        assert.equal(await started, false);
        assert.equal(engine.active, false);
        assert.equal(contexts[0].state, 'closed');
        assert.ok(contexts[0].nodes.every((node) => node.disconnected));
    } finally {
        engine.close();
        globalThis.AudioContext = original;
    }
});

const state = (changes = {}) => ({
    phase: 'night',
    phaseId: 10,
    submitted: false,
    actionSerial: 0,
    seconds: 30,
    ritualTokens: 0,
    winner: null,
    alive: true,
    connected: true,
    ...changes,
});

await test('preferences validate stored data and migrate the old opt-in', () => {
    assert.deepEqual(parseSoundPreferences(null), defaultSoundPreferences);
    assert.equal(parseSoundPreferences(null, 'on').enabled, true);
    assert.deepEqual(parseSoundPreferences('{bad'), defaultSoundPreferences);
    assert.deepEqual(parseSoundPreferences('null'), defaultSoundPreferences);
    assert.deepEqual(
        parseSoundPreferences(
            '{"enabled":"yes","effects":"80","ambience":null}',
        ),
        defaultSoundPreferences,
    );
    assert.deepEqual(
        parseSoundPreferences('{"enabled":true,"effects":150,"ambience":-5}'),
        { enabled: true, effects: 100, ambience: 0, music: 25 },
    );
    assert.equal(parseSoundPreferences('{"effects":12.8}').effects, 13);
});

await test('mount and repeated polling remain silent, including a finished match', () => {
    const tracker = new SoundCueTracker();
    const finished = state({
        phase: 'finished',
        winner: 'cult',
        ritualTokens: 4,
    });
    assert.deepEqual(tracker.update(finished, true), []);
    assert.deepEqual(tracker.update(finished, true), []);
});

await test('action confirmation survives the last submission advancing the phase', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state(), true);
    const next = state({
        phase: 'discussion',
        phaseId: 11,
        actionSerial: 1,
        submitted: false,
    });
    assert.deepEqual(tracker.update(next, true), ['seal', 'dawn']);
    assert.deepEqual(tracker.update(next, true), []);
});

await test('submission poll changes do not leak role actions or play duplicate seals', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state(), true);
    assert.deepEqual(tracker.update(state({ submitted: true }), true), []);
    assert.deepEqual(
        tracker.update(state({ submitted: true, actionSerial: 1 }), true),
        ['seal'],
    );
});

await test('victory takes precedence over simultaneous ritual progress', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state({ ritualTokens: 3 }), true);
    assert.deepEqual(
        tracker.update(
            state({
                phase: 'finished',
                phaseId: 11,
                ritualTokens: 4,
                winner: 'cult',
                actionSerial: 1,
            }),
            true,
        ),
        ['seal', 'cult'],
    );
});

await test('ritual progress replaces dawn to avoid piling up atmospheric cues', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state(), true);
    assert.deepEqual(
        tracker.update(
            state({ phase: 'discussion', phaseId: 11, ritualTokens: 1 }),
            true,
        ),
        ['ritual'],
    );
});

await test('mute, background, and reconnect updates consume missed cues', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state(), true);
    const next = state({ phase: 'voting', phaseId: 11, ritualTokens: 1 });
    assert.deepEqual(tracker.update(next, false), []);
    assert.deepEqual(tracker.update(next, true), []);
    tracker.update({ ...next, connected: false }, true);
    const reconnected = state({ phaseId: 12, ritualTokens: 2 });
    assert.deepEqual(tracker.update(reconnected, true), []);
    assert.deepEqual(tracker.update(reconnected, true), []);
});

await test('deadline reminder occurs once per phase, only for living players needing action', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state({ seconds: 11 }), true);
    assert.deepEqual(tracker.update(state({ seconds: 10 }), true), [
        'reminder',
    ]);
    tracker.update(state({ seconds: 12 }), true);
    assert.deepEqual(tracker.update(state({ seconds: 9 }), true), []);
    for (const changes of [
        { alive: false },
        { submitted: true },
        { connected: false },
        { phase: 'discussion' },
    ]) {
        const other = new SoundCueTracker();
        other.update(state({ ...changes, seconds: 11 }), true);
        assert.deepEqual(
            other.update(state({ ...changes, seconds: 10 }), true),
            [],
        );
    }
});

await test('late load, expired countdown and hidden threshold never replay a reminder', () => {
    const tracker = new SoundCueTracker();
    assert.deepEqual(tracker.update(state({ seconds: 8 }), true), []);
    assert.deepEqual(tracker.update(state({ seconds: 7 }), true), []);
    tracker.update(state({ seconds: 11 }), true);
    assert.deepEqual(tracker.update(state({ seconds: 10 }), false), []);
    assert.deepEqual(tracker.update(state({ seconds: 9 }), true), []);
    const expired = new SoundCueTracker();
    expired.update(state({ seconds: 11 }), true);
    assert.deepEqual(expired.update(state({ seconds: 0 }), true), []);
});

await test('phase cues are distinct and a new phase can remind again', () => {
    const tracker = new SoundCueTracker();
    tracker.update(state(), true);
    assert.deepEqual(
        tracker.update(state({ phase: 'reveal', phaseId: 11 }), true),
        ['reveal'],
    );
    assert.deepEqual(
        tracker.update(state({ phase: 'night', phaseId: 12 }), true),
        ['night'],
    );
    assert.deepEqual(
        tracker.update(
            state({ phase: 'voting', phaseId: 13, seconds: 11 }),
            true,
        ),
        ['vote'],
    );
    assert.deepEqual(
        tracker.update(
            state({ phase: 'voting', phaseId: 13, seconds: 10 }),
            true,
        ),
        ['reminder'],
    );
});
