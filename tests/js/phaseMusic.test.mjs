import assert from 'node:assert/strict';
import test from 'node:test';
import { PhaseMusic } from '../../resources/js/lib/phaseMusic.ts';

function setup(
    tracks = {
        day: ['/day1.mp3', '/day2.mp3', '/day3.mp3'],
        night: ['/night1.mp3'],
    },
) {
    const original = globalThis.Audio;
    const instances = [];
    const issues = [];
    class Audio {
        paused = true;
        ended = false;
        currentTime = 0;
        plays = 0;
        constructor() {
            instances.push(this);
        }
        play() {
            this.paused = false;
            this.plays++;
            return Promise.resolve();
        }
        pause() {
            this.paused = true;
        }
        getAttribute() {
            return this.src;
        }
        removeAttribute() {
            this.src = '';
        }
        load() {
            this.released = true;
        }
        finish() {
            this.ended = true;
            this.onended?.();
        }
    }
    globalThis.Audio = Audio;
    const engine = new PhaseMusic(tracks, (message) => issues.push(message));
    return {
        engine,
        instances,
        issues,
        cleanup: () => {
            engine.close();
            globalThis.Audio = original;
        },
    };
}

await test('music needs activation, preserves progress within daytime phases, and switches at night', async () => {
    const { engine, instances, cleanup } = setup();
    try {
        engine.update('lobby', 25, false);
        assert.equal(instances.length, 0);
        engine.update('lobby', 25, true);
        const day = instances[0];
        day.currentTime = 18;
        engine.update('reveal', 25, true);
        engine.update('discussion', 25, true);
        assert.equal(instances.length, 1);
        assert.equal(day.currentTime, 18);
        assert.equal(day.volume, 0.25 * 0.35);
        assert.equal(day.plays, 1);
        engine.update('voting', 25, true);
        assert.equal(instances.length, 1);
        assert.equal(day.currentTime, 18);
        assert.equal(day.volume, 0.25 * 0.35);
        assert.equal(day.plays, 1);
        engine.update('night', 25, true);
        assert.ok(day.paused && day.released);
        assert.equal(instances[1].src, '/night1.mp3');
        assert.equal(instances[1].volume, 0.25);
        engine.update('finished', 25, true);
        assert.ok(instances[1].paused && instances[1].released);
    } finally {
        cleanup();
    }
});

await test('shuffle plays every track before repeating and avoids repeats between cycles', () => {
    const { engine, instances, cleanup } = setup();
    try {
        engine.update('discussion', 25, true);
        const played = [];
        for (let i = 0; i < 12; i++) {
            const audio = instances.at(-1);
            played.push(audio.src);
            audio.finish();
        }
        for (let i = 0; i < played.length; i += 3)
            assert.equal(new Set(played.slice(i, i + 3)).size, 3);
        assert.ok(played.every((url, i) => i === 0 || url !== played[i - 1]));
        engine.update('night', 25, true);
        instances.at(-1).finish();
        assert.equal(instances.at(-1).src, '/night1.mp3');
    } finally {
        cleanup();
    }
});

await test('muting or backgrounding pauses; resuming continues; stale callbacks cannot restart after close', async () => {
    const { engine, instances, cleanup } = setup();
    try {
        engine.update('night', 25, true);
        const audio = instances[0];
        const staleEnded = audio.onended;
        audio.currentTime = 42;
        engine.update('night', 0, true);
        assert.ok(audio.paused);
        engine.update('night', 50, true);
        assert.equal(audio.volume, 0.5);
        assert.equal(audio.currentTime, 42);
        engine.update('night', 50, false);
        assert.ok(audio.paused);
        engine.update('night', 50, true);
        assert.equal(instances.length, 1);
        engine.close();
        staleEnded();
        engine.update('night', 50, true);
        await Promise.resolve();
        assert.equal(instances.length, 1);
        assert.ok(audio.paused && audio.released);
    } finally {
        cleanup();
    }
});

await test('missing files are skipped once without retry loops and empty playlists are silent', async () => {
    const { engine, instances, issues, cleanup } = setup({
        day: ['/broken.mp3', '/good.mp3'],
        night: [],
    });
    try {
        engine.update('night', 25, true);
        assert.equal(instances.length, 0);
        engine.update('voting', 25, true);
        instances.at(-1).onerror();
        assert.equal(instances.length, 2);
        instances.at(-1).onerror();
        for (let i = 0; i < 10; i++) engine.update('voting', 25, true);
        assert.equal(instances.length, 2);
        assert.match(issues.at(-1), /unavailable/);
        await Promise.resolve();
    } finally {
        cleanup();
    }
});

await test('autoplay rejection is handled without retries on every state update', async () => {
    const { engine, instances, issues, cleanup } = setup();
    try {
        globalThis.Audio.prototype.play = function () {
            this.plays++;
            return Promise.reject(
                new DOMException('Gesture required', 'NotAllowedError'),
            );
        };
        engine.update('night', 25, true);
        await new Promise((resolve) => setImmediate(resolve));
        for (let i = 0; i < 10; i++) engine.update('night', 25, true);
        assert.equal(instances[0].plays, 1);
        assert.match(issues.at(-1), /could not start/);
        engine.update('night', 25, false);
        engine.update('night', 25, true);
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(instances[0].plays, 2);
    } finally {
        cleanup();
    }
});
