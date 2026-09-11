import assert from 'node:assert/strict';
import test from 'node:test';
import { WhisperAudio } from '../../resources/js/lib/whisperAudio.ts';

function setup(tracks = ['/whisper1.mp3', '/whisper2.mp3'], ...options) {
    const original = globalThis.Audio;
    const instances = [];
    class Audio {
        volume = 0;
        paused = true;
        constructor() {
            instances.push(this);
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
    }
    globalThis.Audio = Audio;
    const engine = new WhisperAudio(tracks, ...options);
    return {
        engine,
        instances,
        cleanup: () => {
            engine.close();
            globalThis.Audio = original;
        },
    };
}

await test('whispers need active sound and fresh events; polls and unmuting do not replay them', () => {
    const { engine, instances, cleanup } = setup();
    try {
        engine.update(1, false, 25);
        engine.update(1, true, 25);
        assert.equal(instances.length, 0);
        engine.update(2, true, 25);
        assert.equal(instances.length, 1);
        engine.update(2, true, 50);
        assert.equal(instances.length, 1);
        engine.update(2, false, 50);
        assert.ok(instances[0].paused && instances[0].released);
        engine.update(2, true, 50);
        assert.equal(instances.length, 1);
        engine.update(3, true, 0);
        engine.update(3, true, 25);
        assert.equal(instances.length, 1);
    } finally {
        cleanup();
    }
});

await test('ending an event releases its recording, consecutive clips vary, and errors stay silent', async () => {
    const { engine, instances, cleanup } = setup();
    try {
        engine.update(1, true, 25);
        const first = instances[0].src;
        engine.update(null, true, 25);
        assert.ok(instances[0].released);
        engine.update(2, true, 25);
        const second = instances[1].src;
        assert.notEqual(second, first);
        instances[1].onerror();
        engine.update(3, true, 25);
        assert.equal(instances[2].src, first);
        engine.close();
        await Promise.resolve();
        assert.ok(instances.every((audio) => audio.paused && audio.released));
        engine.update(4, true, 25);
        assert.equal(instances.length, 3);
    } finally {
        cleanup();
    }
});

await test('an empty whisper folder does not create audio or requests', () => {
    const { engine, instances, cleanup } = setup([]);
    try {
        engine.update(1, true, 25);
        assert.equal(instances.length, 0);
    } finally {
        cleanup();
    }
});

await test('a whisper fades in and out, then releases its timer and recording after three seconds', async () => {
    const originalInterval = globalThis.setInterval;
    const originalClear = globalThis.clearInterval;
    const originalNow = Date.now;
    let tick;
    let now = 10000;
    let cleared = false;
    globalThis.setInterval = (callback) => {
        tick = callback;
        return 1;
    };
    globalThis.clearInterval = () => {
        cleared = true;
    };
    Date.now = () => now;
    const { engine, instances, cleanup } = setup();
    try {
        engine.update(1, true, 25);
        assert.equal(instances[0].volume, 0);
        await Promise.resolve();
        now += 350;
        tick();
        assert.equal(instances[0].volume, 0.1);
        now = 12900;
        tick();
        assert.ok(instances[0].volume > 0 && instances[0].volume < 0.1);
        now = 13000;
        cleared = false;
        tick();
        assert.ok(cleared && instances[0].paused && instances[0].released);
    } finally {
        cleanup();
        globalThis.setInterval = originalInterval;
        globalThis.clearInterval = originalClear;
        Date.now = originalNow;
    }
});
