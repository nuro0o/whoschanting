import assert from 'node:assert/strict';
import test from 'node:test';
import * as THREE from 'three';
import { createTableCosmetics } from '../../resources/js/lib/ritualCosmetics.ts';
import { createCosmeticEventStream } from '../../resources/js/lib/ritualSceneState.ts';
import {
    banishmentDetails,
    celebrationDetails,
    cosmeticDuration,
    cosmeticPlaybackMilliseconds,
} from '../../resources/js/lib/ritualCosmeticTiming.ts';

const time = '2026-09-12T12:00:00.000Z';
const banish = {
    id: 'banish',
    kind: 'banishment',
    player_id: 'p1',
    effect: 'lunar_rift',
    created_at: time,
};
const victory = {
    id: 'victory',
    kind: 'celebration',
    player_id: 'p2',
    effect: 'crownfall',
    created_at: time,
};

test('initial snapshots and reconnects never replay historical effects', () => {
    const stream = createCosmeticEventStream();
    assert.deepEqual(stream.consume('match', [banish, victory], time), []);
    assert.deepEqual(stream.consume('match', [banish, victory], time), []);
    assert.deepEqual(
        createCosmeticEventStream().consume('match', [banish, victory], time),
        [],
    );
});

test('final banishment and victory are queued once in server order across polls', () => {
    const stream = createCosmeticEventStream();
    stream.consume('match', [], time);
    assert.deepEqual(stream.consume('match', [banish, victory], time), [
        banish,
        victory,
    ]);
    assert.deepEqual(
        stream.consume('match', structuredClone([banish, victory]), time),
        [],
    );
});

test('late updates, invalid dates and a new match cannot replay old events', () => {
    const stream = createCosmeticEventStream();
    stream.consume('match', [], time);
    assert.deepEqual(
        stream.consume('match', [banish], '2026-09-12T12:00:21Z'),
        [],
    );
    assert.deepEqual(
        stream.consume('match', [{ ...victory, created_at: 'invalid' }], time),
        [],
    );
    assert.deepEqual(stream.consume(null, [], time), []);
    assert.deepEqual(stream.consume('new-match', [banish, victory], time), []);
});

test('3D effects reuse a bounded geometry pool and dispose all owned resources', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    const count = () => {
        let objects = 0;
        scene.traverse(() => objects++);
        return objects;
    };
    const initial = count();
    const geometries = new Set();
    const materials = new Set();
    for (const name of ['classic', 'founders_oak', 'moonlit', 'harvest'])
        cosmetics.table(name);
    for (const name of [
        'classic',
        'crownfall',
        'moonrise',
        'lantern_festival',
    ]) {
        cosmetics.play({ ...victory, id: name, effect: name }, []);
        cosmetics.update(0.3, false);
        scene.traverse((object) => {
            if (object.geometry) geometries.add(object.geometry);
            if (object.material) materials.add(object.material);
        });
        assert.equal(count(), initial);
    }
    for (const name of Object.keys(banishmentDetails)) {
        for (let repeat = 0; repeat < 3; repeat++) {
            cosmetics.play(
                { ...banish, id: `${name}-${repeat}`, effect: name },
                [],
            );
            for (let step = 0; step < 51; step++) cosmetics.update(0.1, false);
            assert.equal(count(), initial);
        }
    }
    let disposedGeometries = 0;
    let disposedMaterials = 0;
    geometries.forEach((geometry) =>
        geometry.addEventListener('dispose', () => disposedGeometries++),
    );
    materials.forEach((material) =>
        material.addEventListener('dispose', () => disposedMaterials++),
    );
    cosmetics.dispose();
    assert.equal(scene.children.length, 0);
    assert.equal(disposedGeometries, geometries.size);
    assert.equal(disposedMaterials, materials.size);
});

test('reduced motion freezes every descendant and material of each distinct production', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    const effect = scene.getObjectByName('Table cosmetic event');
    assert.ok(effect, 'the event composition is mounted in the scene');
    for (const name of [
        'classic',
        ...Object.keys(banishmentDetails),
        'crownfall',
        'moonrise',
        'lantern_festival',
    ]) {
        const event = {
            ...banish,
            id: name,
            effect: name,
            kind: ['crownfall', 'moonrise', 'lantern_festival'].includes(name)
                ? 'celebration'
                : 'banishment',
        };
        cosmetics.play(event, [{ id: 'p1', x: 0.2, y: 0.8, alive: false }]);
        cosmetics.update(0.1, true);
        const still = pose(effect);
        cosmetics.update(1, true);
        cosmetics.update(20, true);
        assert.deepEqual(pose(effect), still, name);
        assert.equal(effect.visible, true);
        cosmetics.update(cosmeticDuration(event) + 0.1, false);
        assert.equal(effect.visible, false);
    }
    cosmetics.play(null, []);
    assert.equal(effect.visible, false);
    cosmetics.dispose();
});

function pose(root, visibleOnly = false) {
    const result = [];
    const visit = (object) => {
        result.push([
            object.name,
            object.visible,
            ...object.position.toArray(),
            ...object.rotation.toArray(),
            ...object.scale.toArray(),
            object.material?.opacity,
        ]);
    };
    if (visibleOnly) root.traverseVisible(visit);
    else root.traverse(visit);
    return result;
}

test('each paid effect lasts for its shared duration and keeps a quiet queue tail', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    for (const name of [
        'classic',
        ...Object.keys(banishmentDetails),
        ...Object.keys(celebrationDetails),
    ]) {
        const event = {
            ...(Object.hasOwn(celebrationDetails, name) ? victory : banish),
            id: name,
            effect: name,
        };
        const duration = cosmeticDuration(event);
        assert.equal(
            cosmeticPlaybackMilliseconds(event),
            Math.round(duration * 1000) + 200,
        );
        cosmetics.play(event, []);
        cosmetics.update(duration - 0.02, false);
        assert.equal(
            scene.children[0].visible,
            true,
            `${name} must finish before queue advances`,
        );
        cosmetics.update(0.03, false);
        assert.equal(scene.children[0].visible, false, `${name} must end`);
    }
    assert.equal(cosmeticDuration({ ...banish, effect: 'unknown' }), 3.2);
    assert.equal(
        cosmeticDuration({ ...victory, effect: 'gilded_vortex' }),
        3.2,
    );
    assert.equal(cosmeticDuration({ ...banish, effect: 'crownfall' }), 3.2);
    assert.equal(cosmeticDuration({ ...victory, effect: 'unknown' }), 3.2);
    cosmetics.dispose();
});

test('paid compositions reset across types, null and repeated play without replaying an event ID', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    const events = [
        ...Object.keys(banishmentDetails).map((effect) => ({
            ...banish,
            effect,
        })),
        ...Object.keys(celebrationDetails).map((effect) => ({
            ...victory,
            effect,
        })),
        { ...banish, effect: 'classic' },
        { ...victory, effect: 'classic' },
        { ...banish, effect: 'crownfall' },
        { ...victory, effect: 'lunar_rift' },
    ];
    for (const entry of events) {
        const name = `${entry.kind}-${entry.effect}`;
        const event = { ...entry, id: `fresh-${name}` };
        cosmetics.play(event, []);
        cosmetics.update(1.8, false);
        const expected = pose(scene, true);
        cosmetics.play({ ...event }, []);
        cosmetics.update(0, false);
        assert.deepEqual(
            pose(scene, true),
            expected,
            'same event ID must not restart',
        );
        for (const otherEvent of events) {
            const other = `${otherEvent.kind}-${otherEvent.effect}`;
            cosmetics.play({ ...otherEvent, id: `other-${name}-${other}` }, []);
            cosmetics.update(3.6, false);
            cosmetics.play(null, []);
            assert.equal(scene.children[0].visible, false);
            cosmetics.play({ ...event, id: `replay-${name}-${other}` }, []);
            cosmetics.update(1.8, false);
            assert.deepEqual(
                pose(scene, true),
                expected,
                `${other} must not contaminate ${name}`,
            );
        }
    }
    cosmetics.dispose();
});

test('victories use centered distinct stages and retain their own choreography', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    const stages = [
        'Crownfall coronation',
        'Moonrise celestial revelation',
        'Lantern Festival wish release',
    ];
    const names = Object.keys(celebrationDetails);
    names.forEach((name, index) => {
        cosmetics.play({ ...victory, id: name, effect: name }, [
            { id: 'p2', x: 0.1, y: 0.8, alive: true },
        ]);
        cosmetics.update(1, false);
        const early = pose(scene, true);
        stages.forEach((stage, i) =>
            assert.equal(scene.getObjectByName(stage).visible, index === i),
        );
        assert.equal(
            scene.getObjectByName('Paid banishment stage').visible,
            false,
        );
        assert.equal(
            scene.getObjectByName('Classic banishment and celebrations')
                .visible,
            false,
        );
        assert.equal(scene.getObjectByName('Paid victory stage').position.x, 0);
        assert.equal(scene.getObjectByName('Paid victory stage').position.z, 0);
        cosmetics.update(2.8, false);
        assert.notDeepEqual(
            pose(scene, true),
            early,
            `${name} must progress through its stages`,
        );
        if (name === 'crownfall') {
            assert.equal(
                scene.getObjectByName('Assembled royal crown').position.y,
                1.3,
            );
            assert.equal(
                scene.getObjectByName('Coronation pennants').visible,
                true,
            );
        } else if (name === 'moonrise') {
            assert.equal(
                scene.getObjectByName('Rising pearl moon').position.y,
                1.98,
            );
            assert.equal(
                scene.getObjectByName('Aligned lunar phase halo').scale.x,
                1,
            );
        } else {
            assert.ok(
                scene.getObjectByName('Crafted wish lantern').position.y > 1.6,
            );
            assert.ok(
                scene.getObjectByName('Pleated paper body').material
                    .emissiveIntensity > 0.8,
            );
        }
    });
    cosmetics.dispose();
});

test('paid banishments have separate stage silhouettes and motion paths', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    const paths = [];
    const stages = ['Gilded tribunal', 'Lunar eclipse doorway', 'Harvest pyre'];
    Object.keys(banishmentDetails).forEach((name, index) => {
        cosmetics.play({ ...banish, id: name, effect: name }, []);
        cosmetics.update(2.4, false);
        stages.forEach((stage, i) =>
            assert.equal(scene.getObjectByName(stage).visible, index === i),
        );
        paths.push(scene.getObjectByName('Condemned pawn').position.toArray());
    });
    assert.notDeepEqual(paths[0], paths[1]);
    assert.notDeepEqual(paths[0], paths[2]);
    assert.notDeepEqual(paths[1], paths[2]);
    cosmetics.dispose();
});
