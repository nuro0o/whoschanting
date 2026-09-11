import assert from 'node:assert/strict';
import test from 'node:test';
import { WhisperAtmosphere } from '../../resources/js/lib/whisperAtmosphere.ts';

const context = {
    phase: 'discussion',
    phaseId: 1,
    tokens: 2,
    threshold: 10,
    seconds: 60,
    allowed: true,
    volume: 25,
    eventId: null,
};
function setup(tracks = { ambient: ['/murmurs.mp3'], oneoff: ['/scare.mp3'] }) {
    const original = globalThis.Audio;
    const recordings = [];
    globalThis.Audio = class {
        paused = true;
        constructor() {
            recordings.push(this);
        }
        play() {
            this.paused = false;
            return Promise.resolve();
        }
        pause() {
            this.paused = true;
        }
        removeAttribute() {
            this.src = '';
        }
        load() {
            this.released = true;
        }
    };
    const engine = new WhisperAtmosphere(tracks, () => 0);
    return {
        engine,
        recordings,
        cleanup: () => {
            engine.close();
            globalThis.Audio = original;
        },
    };
}

await test('ambient whispers wait for public progress and oneoffs interrupt without overlapping or replaying', () => {
    const { engine, recordings, cleanup } = setup();
    try {
        engine.update({ ...context, tokens: 0 }, 0);
        engine.update(context, 1000);
        engine.update(context, 35999);
        assert.equal(recordings.length, 0);
        engine.update(context, 36000);
        assert.equal(recordings[0].src, '/murmurs.mp3');
        engine.update({ ...context, eventId: 1 }, 37000);
        assert.ok(recordings[0].paused && recordings[0].released);
        assert.equal(recordings[1].src, '/scare.mp3');
        engine.update({ ...context, eventId: 1 }, 38000);
        engine.update(context, 41000);
        assert.equal(recordings.length, 2);
        assert.equal(
            recordings[1].paused,
            false,
            'visual expiry must not truncate the recording',
        );
        engine.update({ ...context, allowed: false, eventId: 2 }, 42000);
        assert.ok(recordings.every((a) => a.paused));
        engine.update({ ...context, eventId: 2 }, 43000);
        assert.equal(recordings.length, 2, 'suppressed scares cannot replay');
    } finally {
        cleanup();
    }
});

await test('higher ritual starts ambient sooner; phase endings, mute and scene suppression stop audio', () => {
    for (const suppressed of [
        { allowed: false },
        { phase: 'finished' },
        { seconds: 10 },
        { volume: 0 },
        { tokens: 0 },
        { threshold: 0 },
    ]) {
        const { engine, recordings, cleanup } = setup();
        try {
            const high = { ...context, tokens: 8 };
            engine.update(high, 1000);
            engine.update(high, 17000);
            assert.equal(recordings.length, 1);
            engine.update({ ...high, ...suppressed }, 18000);
            assert.ok(recordings[0].paused && recordings[0].released);
        } finally {
            cleanup();
        }
    }
});

await test('late callbacks and phase changes reschedule rather than playing a stale whisper', () => {
    const { engine, recordings, cleanup } = setup();
    try {
        engine.update(context, 1000);
        engine.update(context, 90000);
        assert.equal(recordings.length, 0);
        engine.update({ ...context, phaseId: 2 }, 137000);
        assert.equal(recordings.length, 0);
        engine.update({ ...context, phaseId: 2 }, 172000);
        assert.equal(recordings.length, 1);
        engine.close();
        engine.update({ ...context, eventId: 1 }, 173000);
        assert.equal(recordings.length, 1);
    } finally {
        cleanup();
    }
});

await test('missing folders stay silent', () => {
    const { engine, recordings, cleanup } = setup({ ambient: [], oneoff: [] });
    try {
        engine.update(context, 1000);
        engine.update(context, 36000);
        engine.update({ ...context, eventId: 1 }, 37000);
        assert.equal(recordings.length, 0);
    } finally {
        cleanup();
    }
});
