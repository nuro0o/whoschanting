import assert from 'node:assert/strict';
import test from 'node:test';
import * as THREE from 'three';
import { createPaidTableModel } from '../../resources/js/lib/paidTableModel.ts';
import { createFoundersTable } from '../../resources/js/lib/foundersTable.ts';
import { createMoonlitTable } from '../../resources/js/lib/moonlitTable.ts';

function canvasDocument(failAt = Infinity) {
    let count = 0;
    return {
        createElement(name) {
            assert.equal(name, 'canvas');
            const fails = ++count === failAt;
            return {
                getContext() {
                    if (fails) return null;
                    return new Proxy(
                        {},
                        {
                            get(_target, property) {
                                return String(property).endsWith('Gradient')
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

test('paid model disposal is idempotent and includes unused construction resources', (t) => {
    const previous = globalThis.document;
    globalThis.document = canvasDocument();
    t.after(() => {
        globalThis.document = previous;
    });
    const scene = new THREE.Scene();
    const model = createPaidTableModel(scene, 'test table');
    const geometry = model.geometry(new THREE.BoxGeometry());
    const material = model.material(0xffffff);
    const unused = model.material(0x333333);
    const texture = model.texture(8, 8, () => {});
    const counts = new Map(
        [geometry, material, unused, texture].map((value) => [value, 0]),
    );
    for (const resource of counts.keys())
        resource.addEventListener('dispose', () =>
            counts.set(resource, counts.get(resource) + 1),
        );
    model.part(geometry, material, 1, 2, 3);
    model.part(geometry, material, -1, 2, 3);
    assert.equal(
        scene.children.length,
        0,
        'construction is hidden until complete',
    );
    model.finish();
    model.finish();
    assert.equal(scene.children.length, 1);
    assert.equal(
        model.root.children.length,
        1,
        'matching parts share one draw',
    );
    assert.equal(model.root.children[0].count, 2);
    let instanceDisposals = 0;
    model.root.children[0].addEventListener(
        'dispose',
        () => instanceDisposals++,
    );
    model.dispose();
    model.dispose();
    assert.equal(scene.children.length, 0);
    assert.equal(instanceDisposals, 1);
    assert.ok([...counts.values()].every((count) => count === 1));
});

for (const { name, create } of [
    { name: 'Founder', create: createFoundersTable },
    { name: 'Moonlit', create: createMoonlitTable },
]) {
    test(`${name} furniture stays bounded and releases all rendered resources`, (t) => {
        const previous = globalThis.document;
        globalThis.document = canvasDocument();
        t.after(() => {
            globalThis.document = previous;
        });
        const scene = new THREE.Scene();
        const table = create(scene);
        assert.ok(
            table.root.children.length <= 45,
            'complete furniture must fit its draw budget',
        );
        const resources = new Set();
        const matrix = new THREE.Matrix4();
        const bounds = new THREE.Box3();
        for (const mesh of table.root.children) {
            assert.ok(mesh instanceof THREE.InstancedMesh);
            assert.ok(Number.isFinite(mesh.boundingSphere.radius));
            resources.add(mesh);
            resources.add(mesh.geometry);
            resources.add(mesh.material);
            if (mesh.material.map) resources.add(mesh.material.map);
            mesh.geometry.computeBoundingBox();
            for (let i = 0; i < mesh.count; i++) {
                mesh.getMatrixAt(i, matrix);
                bounds.union(
                    mesh.geometry.boundingBox.clone().applyMatrix4(matrix),
                );
            }
        }
        assert.ok(bounds.min.x >= -5.5 && bounds.max.x <= 5.5);
        assert.ok(bounds.min.z >= -4 && bounds.max.z <= 4);
        assert.ok(
            bounds.max.y < 2,
            'props must not overwhelm the player portraits',
        );
        const matrices = table.root.children.map((mesh) => [
            ...mesh.instanceMatrix.array,
        ]);
        for (let i = 0; i < 20; i++) {
            table.root.visible = i % 2 === 0;
            table.update(i % 2);
        }
        assert.deepEqual(
            table.root.children.map((mesh) => [...mesh.instanceMatrix.array]),
            matrices,
        );
        const released = new Set();
        resources.forEach((resource) =>
            resource.addEventListener('dispose', () => released.add(resource)),
        );
        table.dispose();
        assert.equal(scene.children.length, 0);
        assert.equal(released.size, resources.size);
    });

    test(`${name} texture failure disposes partially constructed materials`, (t) => {
        const previous = globalThis.document;
        globalThis.document = canvasDocument(1);
        t.after(() => {
            globalThis.document = previous;
        });
        let materials = 0;
        t.mock.method(THREE.Material.prototype, 'dispose', () => materials++);
        const scene = new THREE.Scene();
        assert.throws(() => create(scene), /textures unavailable/);
        assert.equal(scene.children.length, 0);
        assert.ok(materials > 0);
    });
}
