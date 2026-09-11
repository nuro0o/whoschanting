import assert from 'node:assert/strict';
import test from 'node:test';
import { Group, OrthographicCamera, Vector3 } from 'three';
import {
    poseRimTentacle,
    rimTentacleCount,
    rimTentaclePath,
} from '../../resources/js/lib/ritualTentacles.ts';

await test('rim tentacles leave the entire central seal clear at every allowed camera angle', () => {
    const camera = new OrthographicCamera(-6, 6, 5, -5, 0.1, 60);
    const arm = new Group();
    // Covers the enlarged brass seal, plus tube thickness and suction cups.
    const sealRadius = 2;
    const limbRadius = 0.2;
    for (const polar of [Math.PI / 9, Math.PI / 4, Math.PI / 3]) {
        for (let view = 0; view < 32; view++) {
            const azimuth = (view / 32) * Math.PI * 2;
            camera.position.set(
                14 * Math.sin(polar) * Math.sin(azimuth),
                14 * Math.cos(polar),
                14 * Math.sin(polar) * Math.cos(azimuth),
            );
            camera.lookAt(0, 0, 0);
            camera.updateMatrixWorld();
            const seal = new Vector3(0, 0.34, 0).applyMatrix4(
                camera.matrixWorldInverse,
            );
            const protectedRadius = sealRadius + limbRadius / Math.cos(polar);
            for (let index = 0; index < rimTentacleCount; index++) {
                const path = rimTentaclePath(index);
                for (const growth of [0, 0.25, 0.68, 1]) {
                    for (const time of [0, 2.5, 5]) {
                        poseRimTentacle(arm, index, growth, time, false);
                        arm.updateMatrixWorld();
                        for (let sample = 0; sample <= 40; sample++) {
                            const point = path
                                .getPoint(sample / 40)
                                .applyMatrix4(arm.matrixWorld)
                                .applyMatrix4(camera.matrixWorldInverse);
                            const clearance = Math.hypot(
                                (point.x - seal.x) / protectedRadius,
                                (point.y - seal.y) /
                                    (protectedRadius * Math.cos(polar)),
                            );
                            assert.ok(
                                clearance > 1,
                                `Limb ${index} overlaps the seal at view ${view}, polar ${polar}, growth ${growth}`,
                            );
                        }
                    }
                }
            }
        }
    }
});

await test('emergence and reduced motion keep each limb attached to the same rim point', () => {
    for (let index = 0; index < rimTentacleCount; index++) {
        const arm = new Group();
        poseRimTentacle(arm, index, 0.1, 0, false);
        const anchor = arm.position.clone();
        poseRimTentacle(arm, index, 1, 200, false);
        assert.deepEqual(arm.position, anchor);
        poseRimTentacle(arm, index, 1, 0, true);
        arm.updateMatrix();
        const still = arm.matrix.clone();
        poseRimTentacle(arm, index, 1, 100, true);
        arm.updateMatrix();
        assert.deepEqual(arm.matrix, still);
    }
});
