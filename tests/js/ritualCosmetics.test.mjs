import assert from 'node:assert/strict';
import test from 'node:test';
import * as THREE from 'three';
import { createTableCosmetics } from '../../resources/js/lib/ritualCosmetics.ts';
import { createCosmeticEventStream } from '../../resources/js/lib/ritualSceneState.ts';

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

test('reduced motion shows a static 3D composition; effects stop after their duration', () => {
    const scene = new THREE.Scene();
    const cosmetics = createTableCosmetics(scene);
    cosmetics.play(banish, [{ id: 'p1', x: 0.2, y: 0.8, alive: false }]);
    const effect = scene.children[1];
    const pose = () =>
        effect.children.map((object) => [
            ...object.position.toArray(),
            ...object.rotation.toArray(),
        ]);
    cosmetics.update(0.1, true);
    const still = pose();
    cosmetics.update(1, true);
    assert.deepEqual(pose(), still);
    assert.equal(effect.visible, true);
    cosmetics.update(4, false);
    assert.equal(effect.visible, false);
    cosmetics.play(null, []);
    assert.equal(effect.visible, false);
    cosmetics.dispose();
});
