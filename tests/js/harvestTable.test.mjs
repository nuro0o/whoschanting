import assert from 'node:assert/strict';
import test from 'node:test';
import * as THREE from 'three';
import { createHarvestTable } from '../../resources/js/lib/harvestTable.ts';

function canvasDocument(failAt = Infinity) {
    let canvases = 0;
    return {
        createElement(name) {
            assert.equal(name, 'canvas');
            const fails = ++canvases === failAt;
            return {
                getContext() {
                    if (fails) return null;
                    return new Proxy(
                        {},
                        {
                            get(_target, name) {
                                return name === 'createRadialGradient'
                                    ? () => ({ addColorStop() {} })
                                    : () => {};
                            },
                        },
                    );
                },
            };
        },
    };
}

test('Harvest batches its static furnishings and releases every GPU resource', (t) => {
    const previous = globalThis.document;
    globalThis.document = canvasDocument();
    t.after(() => {
        globalThis.document = previous;
    });
    const scene = new THREE.Scene();
    const harvest = createHarvestTable(scene);
    assert.ok(
        harvest.root.children.length < 40,
        'furnishings must stay within a modest draw budget',
    );
    const resources = new Set();
    let instances = 0;
    for (const mesh of harvest.root.children) {
        assert.ok(mesh instanceof THREE.InstancedMesh);
        instances += mesh.count;
        assert.ok(Number.isFinite(mesh.boundingSphere.radius));
        resources.add(mesh);
        resources.add(mesh.geometry);
        resources.add(mesh.material);
        if (mesh.material.map) resources.add(mesh.material.map);
    }
    assert.ok(
        instances > 250,
        'the scene should contain its complete furnishings',
    );
    const matrices = harvest.root.children.map((mesh) => [
        ...mesh.instanceMatrix.array,
    ]);
    for (let i = 0; i < 20; i++) {
        harvest.root.visible = i % 2 === 0;
        harvest.update(i % 2);
    }
    assert.deepEqual(
        harvest.root.children.map((mesh) => [...mesh.instanceMatrix.array]),
        matrices,
    );
    const disposed = new Set();
    resources.forEach((resource) =>
        resource.addEventListener('dispose', () => disposed.add(resource)),
    );
    harvest.dispose();
    assert.equal(scene.children.length, 0);
    assert.equal(harvest.root.children.length, 0);
    assert.equal(disposed.size, resources.size);
    harvest.dispose();
});

test('texture initialization failure cleans up a partially built Harvest table', (t) => {
    const previous = globalThis.document;
    globalThis.document = canvasDocument(3);
    t.after(() => {
        globalThis.document = previous;
    });
    let geometries = 0,
        materials = 0,
        textures = 0;
    t.mock.method(THREE.BufferGeometry.prototype, 'dispose', () => {
        geometries++;
    });
    t.mock.method(THREE.Material.prototype, 'dispose', () => {
        materials++;
    });
    t.mock.method(THREE.Texture.prototype, 'dispose', () => {
        textures++;
    });
    const scene = new THREE.Scene();
    assert.throws(
        () => createHarvestTable(scene),
        /Harvest textures unavailable/,
    );
    assert.equal(scene.children.length, 0);
    assert.ok(geometries > 0);
    assert.ok(materials > 0);
    assert.equal(textures, 2);
});
