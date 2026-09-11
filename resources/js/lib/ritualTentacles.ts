import { CatmullRomCurve3, Vector3, type Object3D } from 'three';

export const rimTentacleCount = 8;

/** Short, outward curls leave the center clear even at the lowest table view. */
export function rimTentaclePath(index: number): CatmullRomCurve3 {
    const height = 0.95 + (index % 3) * 0.1;
    return new CatmullRomCurve3([
        new Vector3(0, -0.16, 0),
        new Vector3(0.1, 0.16, 0.035),
        new Vector3(0.32, height * 0.7, 0.055),
        new Vector3(0.49, height, 0.015),
        new Vector3(0.25, height * 0.95, -0.02),
        new Vector3(0.19, height * 0.76, -0.025),
    ]);
}

/** Grow each limb in place; camera orbit must never move its attachment. */
export function poseRimTentacle(
    arm: Object3D,
    index: number,
    emergence: number,
    time: number,
    reducedMotion: boolean,
): void {
    const angle = ((index + 0.5) / rimTentacleCount) * Math.PI * 2;
    const growth = Math.max(0, Math.min(1, emergence));
    arm.position.set(Math.cos(angle) * 4.97, -0.18, Math.sin(angle) * 3.57);
    arm.rotation.set(
        0,
        -angle,
        reducedMotion ? 0 : Math.sin(time * 0.6 + index * 1.7) * 0.045,
    );
    arm.scale.set(
        0.8 + growth * 0.2,
        Math.max(0.001, growth),
        0.8 + growth * 0.2,
    );
}
