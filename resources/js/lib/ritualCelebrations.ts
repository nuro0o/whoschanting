import * as THREE from 'three';
import { celebrationDetail } from './ritualCosmeticTiming.ts';

const TAU = Math.PI * 2;
const phase = (time: number, start: number, end: number) => {
    const t = Math.max(0, Math.min(1, (time - start) / (end - start)));
    return t * t * (3 - 2 * t);
};

/** Table-wide victory productions with one reusable, fully owned geometry pool. */
export function createPaidCelebrations(parent: THREE.Group) {
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.MeshStandardMaterial>();
    const root = new THREE.Group();
    root.name = 'Paid victory stage';
    root.visible = false;
    root.position.y = 0.26;
    parent.add(root);
    const group = (name: string, owner: THREE.Object3D = root) => {
        const value = new THREE.Group();
        value.name = name;
        owner.add(value);
        return value;
    };
    const geometry = <T extends THREE.BufferGeometry>(value: T): T => {
        geometries.add(value);
        return value;
    };
    const paint = (color: number, emissive: number, metalness = 0.3) => {
        const value = new THREE.MeshStandardMaterial({
            color,
            emissive,
            emissiveIntensity: 0.55,
            metalness,
            roughness: 0.38,
            transparent: true,
            side: THREE.DoubleSide,
        });
        materials.add(value);
        return value;
    };
    const mesh = (
        shape: THREE.BufferGeometry,
        material: THREE.Material,
        owner: THREE.Object3D,
        name = '',
    ) => {
        const value = new THREE.Mesh(shape, material);
        value.name = name;
        owner.add(value);
        return value;
    };
    const gold = paint(0xf3cc77, 0x946019, 0.8);
    const ivory = paint(0xffedb9, 0x977330, 0.5);
    const brass = paint(0x92632c, 0x33220d, 0.8);
    const jade = paint(0x416b51, 0x173d2b, 0.45);
    const silver = paint(0xd0d9f8, 0x6e78b6, 0.75);
    const pearl = paint(0xf1edff, 0xaca4e9, 0.25);
    const indigo = paint(0x343b70, 0x292b59, 0.75);
    const lilac = paint(0xaaa3e3, 0x6559a3, 0.35);
    const copper = paint(0xb87a41, 0x583213, 0.7);
    const amber = paint(0xffc764, 0xb5671b, 0.15);
    const leaves = paint(0xd29149, 0x754219, 0.3);
    const firefly = paint(0xffe7a0, 0xe6a634, 0);
    const ring = geometry(new THREE.TorusGeometry(1, 0.025, 6, 64));
    const bead = geometry(new THREE.OctahedronGeometry(0.055));
    const coin = geometry(new THREE.CylinderGeometry(0.075, 0.075, 0.018, 12));
    const leaf = geometry(new THREE.SphereGeometry(1, 8, 6));
    const rod = geometry(new THREE.CylinderGeometry(0.016, 0.016, 1, 5));
    const disc = geometry(new THREE.CylinderGeometry(1, 1, 0.08, 64));

    // CORONATION: a broad laurel silhouette, assembled crown and a gilded dais.
    const coronation = group('Crownfall coronation');
    const dais = group('Royal medallion dais', coronation);
    mesh(disc, brass, dais).scale.set(1.2, 1.8, 1.2);
    const inlay = mesh(disc, jade, dais);
    inlay.scale.set(1.08, 1, 1.08);
    inlay.position.y = 0.09;
    for (const radius of [0.85, 1.12]) {
        const border = mesh(ring, gold, dais);
        border.rotation.x = -Math.PI / 2;
        border.position.y = 0.14;
        border.scale.setScalar(radius);
    }
    const laurel = group('Unfurling victory laurels', coronation);
    const laurelLeaves: THREE.Mesh[] = [];
    for (const side of [-1, 1]) {
        const branch = new THREE.CatmullRomCurve3([
            new THREE.Vector3(0, 0.2, -0.2),
            new THREE.Vector3(side * 1.25, 0.55, -0.35),
            new THREE.Vector3(side * 1.7, 1.55, -0.4),
            new THREE.Vector3(side * 1.22, 2.65, -0.4),
        ]);
        mesh(
            geometry(new THREE.TubeGeometry(branch, 24, 0.025, 5, false)),
            brass,
            laurel,
        );
        for (let i = 0; i < 11; i++) {
            const point = branch.getPoint((i + 1) / 12);
            for (const outward of [-1, 1]) {
                const petal = mesh(leaf, i % 3 ? gold : jade, laurel);
                petal.position.copy(point);
                petal.position.x += side * outward * 0.13;
                petal.rotation.z = side * (outward * 0.75 - i * 0.045);
                petal.scale.set(0.13, 0.28, 0.045);
                laurelLeaves.push(petal);
            }
        }
    }
    const royalCrown = group('Assembled royal crown', coronation);
    const crownParts: THREE.Group[] = [];
    const crownBand = geometry(
        new THREE.CylinderGeometry(0.84, 0.78, 0.2, 64, 1, true),
    );
    mesh(crownBand, gold, royalCrown).position.y = 0.1;
    for (const y of [0, 0.21]) {
        const rim = mesh(ring, ivory, royalCrown);
        rim.rotation.x = -Math.PI / 2;
        rim.scale.setScalar(0.82);
        rim.position.y = y;
    }
    const pointShape = new THREE.Shape();
    pointShape.moveTo(-0.28, 0);
    pointShape.lineTo(-0.19, 0.25);
    pointShape.lineTo(0, 0.67);
    pointShape.lineTo(0.19, 0.25);
    pointShape.lineTo(0.28, 0);
    pointShape.closePath();
    const crownPoint = geometry(
        new THREE.ExtrudeGeometry(pointShape, {
            depth: 0.07,
            bevelEnabled: false,
        }),
    );
    for (let i = 0; i < 8; i++) {
        const angle = (i / 8) * TAU;
        const piece = group('Crown fragment', royalCrown);
        piece.rotation.y = angle;
        mesh(crownPoint, gold, piece).position.set(0, 0.16, 0.79);
        const jewel = mesh(bead, i % 2 ? jade : ivory, piece);
        jewel.position.set(0, 0.75, 0.84);
        jewel.scale.setScalar(1.7);
        crownParts.push(piece);
    }
    const rays = group('Coronation pennants', coronation);
    const pennantShape = new THREE.Shape();
    pennantShape.moveTo(-0.07, 0);
    pennantShape.lineTo(0.07, 0);
    pennantShape.lineTo(0.13, 0.52);
    pennantShape.lineTo(0, 0.43);
    pennantShape.lineTo(-0.13, 0.52);
    pennantShape.closePath();
    const pennantGeometry = geometry(new THREE.ShapeGeometry(pennantShape));
    for (let i = 0; i < 12; i++) {
        const angle = (i / 12) * TAU;
        const pennant = mesh(pennantGeometry, i % 2 ? ivory : gold, rays);
        pennant.position.set(
            Math.sin(angle) * 1.25,
            1.42 + Math.cos(angle) * 1.25,
            -0.55,
        );
        pennant.rotation.z = -angle;
    }
    const coins = Array.from({ length: 32 }, () =>
        mesh(coin, gold, coronation, 'Falling victory coin'),
    );

    // MOONRISE: moon reflected on water, with an astronomical phase halo.
    const moonrise = group('Moonrise celestial revelation');
    const pool = group('Silver reflecting pool', moonrise);
    mesh(disc, indigo, pool).scale.set(1.55, 0.5, 1.55);
    const poolRim = mesh(ring, silver, pool);
    poolRim.scale.setScalar(1.57);
    poolRim.rotation.x = -Math.PI / 2;
    poolRim.position.y = 0.05;
    const ripples = Array.from({ length: 4 }, () => {
        const ripple = mesh(ring, lilac, pool);
        ripple.rotation.x = -Math.PI / 2;
        ripple.position.y = 0.055;
        return ripple;
    });
    const moonBody = group('Rising pearl moon', moonrise);
    const sphere = geometry(new THREE.SphereGeometry(0.76, 32, 20));
    const fullMoon = mesh(sphere, pearl, moonBody);
    fullMoon.scale.z = 0.2;
    const crescentShape = new THREE.Shape();
    crescentShape.moveTo(0, 1);
    crescentShape.bezierCurveTo(-1.36, 0.94, -1.36, -0.94, 0, -1);
    crescentShape.bezierCurveTo(-0.76, -0.66, -0.76, 0.66, 0, 1);
    const crescent = geometry(
        new THREE.ExtrudeGeometry(crescentShape, {
            depth: 0.09,
            bevelEnabled: false,
            curveSegments: 32,
        }),
    );
    const moonCrescent = mesh(crescent, silver, moonBody);
    moonCrescent.scale.setScalar(1.05);
    moonCrescent.position.set(0.31, 0, 0.2);
    const reflection = mesh(sphere, lilac, pool, 'Pearl moon reflection');
    reflection.rotation.x = -Math.PI / 2;
    reflection.scale.set(0.75, 0.85, 0.012);
    reflection.position.set(0, 0.065, 0.18);
    const halo = group('Aligned lunar phase halo', moonrise);
    halo.position.set(0, 1.92, -0.2);
    const haloArc = mesh(
        geometry(new THREE.TorusGeometry(1.48, 0.013, 5, 80, Math.PI * 1.72)),
        silver,
        halo,
    );
    haloArc.rotation.z = Math.PI * 0.14;
    const phases: THREE.Group[] = [];
    for (let i = 0; i < 7; i++) {
        const satellite = group('Orbital moon phase', halo);
        if (i === 3)
            mesh(sphere, pearl, satellite).scale.set(0.25, 0.25, 0.055);
        else {
            const sliver = mesh(crescent, i % 2 ? lilac : silver, satellite);
            sliver.scale.setScalar(0.21);
            sliver.rotation.z = i < 3 ? 0 : Math.PI;
        }
        phases.push(satellite);
    }
    const starlight = Array.from({ length: 40 }, () =>
        mesh(bead, pearl, moonrise, 'Returning pearl starlight'),
    );

    // WISH RELEASE: ribbed paper bodies, metal caps, luminous cores and tassels.
    const festival = group('Lantern Festival wish release');
    const lanterns: {
        group: THREE.Group;
        paper: THREE.MeshStandardMaterial;
    }[] = [];
    const paperProfile = [
        new THREE.Vector2(0.11, -0.27),
        new THREE.Vector2(0.22, -0.2),
        new THREE.Vector2(0.27, 0),
        new THREE.Vector2(0.22, 0.2),
        new THREE.Vector2(0.11, 0.27),
    ];
    const paperGeometry = geometry(new THREE.LatheGeometry(paperProfile, 12));
    const capGeometry = geometry(
        new THREE.CylinderGeometry(0.12, 0.12, 0.04, 12),
    );
    const ribGeometry = geometry(
        new THREE.TubeGeometry(
            new THREE.CatmullRomCurve3(
                paperProfile.map((p) => new THREE.Vector3(p.x + 0.006, p.y, 0)),
            ),
            12,
            0.009,
            4,
            false,
        ),
    );
    for (let i = 0; i < 12; i++) {
        const lantern = group('Crafted wish lantern', festival);
        const paper = paint(
            i % 3 === 0 ? 0xffecd0 : i % 3 === 1 ? 0xeaaa63 : 0xe9c38b,
            0xe38e28,
            0,
        );
        mesh(paperGeometry, paper, lantern, 'Pleated paper body');
        for (let rib = 0; rib < 8; rib++)
            mesh(ribGeometry, copper, lantern).rotation.y = (rib / 8) * TAU;
        for (const y of [-0.27, 0.27])
            mesh(capGeometry, copper, lantern).position.y = y;
        const core = mesh(bead, firefly, lantern, 'Lantern luminous core');
        core.position.y = -0.29;
        core.scale.setScalar(0.9);
        const handle = mesh(ring, copper, lantern);
        handle.scale.setScalar(0.11);
        handle.position.y = 0.33;
        const tassel = mesh(rod, amber, lantern);
        tassel.scale.y = 0.17;
        tassel.position.y = -0.38;
        lanterns.push({ group: lantern, paper });
    }
    const autumn = Array.from({ length: 18 }, () =>
        mesh(leaf, leaves, festival, 'Drifting autumn leaf'),
    );
    const wishes = Array.from({ length: 32 }, () =>
        mesh(bead, firefly, festival, 'Festival firefly'),
    );

    function reset() {
        root.visible =
            coronation.visible =
            moonrise.visible =
            festival.visible =
                false;
    }
    return {
        reset,
        update(effect: string, elapsed: number, reduced: boolean) {
            reset();
            root.visible = true;
            const t = reduced ? 3.8 : elapsed;
            const duration = celebrationDetail(effect)?.duration ?? 3.2;
            const fade =
                phase(t, 0, 0.35) * (1 - phase(t, duration - 0.8, duration));
            materials.forEach((material) => {
                material.opacity = fade;
            });
            if (effect === 'crownfall') {
                coronation.visible = true;
                const reveal = phase(t, 0, 1.2);
                const assemble = phase(t, 0.7, 2.3);
                const settle = phase(t, 2.6, 3.15);
                const flourish = phase(t, 3.15, 3.65);
                dais.position.y = 0.05 + reveal * 0.3;
                dais.scale.setScalar(0.5 + reveal * 0.5);
                laurel.scale.setScalar(0.2 + reveal * 0.8);
                laurelLeaves.forEach((petal, i) => {
                    const open = phase(
                        t,
                        0.08 + (Math.floor(i / 2) % 11) * 0.055,
                        0.7 + (Math.floor(i / 2) % 11) * 0.06,
                    );
                    petal.scale.set(0.13 * open, 0.28 * open, 0.045);
                });
                royalCrown.position.y = 1.3 + (1 - settle) * 0.6;
                royalCrown.rotation.y = (1 - assemble) * 0.4;
                royalCrown.scale.setScalar(0.5 + assemble * 0.5);
                crownParts.forEach((piece, i) => {
                    const angle = (i / 8) * TAU;
                    piece.position.set(
                        Math.sin(angle) * (1 - assemble) * 0.9,
                        (1 - assemble) * (0.4 + (i % 3) * 0.2),
                        Math.cos(angle) * (1 - assemble) * 0.9,
                    );
                });
                rays.visible = t >= 3.15;
                rays.scale.setScalar(0.7 + flourish * 0.3);
                coins.forEach((particle, i) => {
                    const fall = phase(
                        t,
                        3.15 + (i % 8) * 0.07,
                        4.65 + (i % 5) * 0.1,
                    );
                    const angle = i * 2.4;
                    const radius = 1.25 + (i % 5) * 0.19;
                    particle.visible = t > 3.15 + (i % 8) * 0.07;
                    particle.position.set(
                        Math.cos(angle) * radius,
                        2.8 - fall * 2.6 + (i % 3) * 0.12,
                        Math.sin(angle) * radius * 0.75,
                    );
                    particle.rotation.set(fall * 5 + i, angle, fall * 2);
                });
            } else if (effect === 'moonrise') {
                moonrise.visible = true;
                const rise = phase(t, 0.5, 2.6);
                const align = phase(t, 1.7, 3.2);
                pool.scale.setScalar(0.3 + phase(t, 0, 0.8) * 0.7);
                moonBody.position.y = 0.12 + rise * 1.86;
                moonBody.scale.setScalar(0.08 + rise * 0.92);
                moonBody.rotation.z = (1 - rise) * -0.35;
                halo.scale.setScalar(0.35 + align * 0.65);
                halo.rotation.z = (1 - align) * -0.4;
                halo.visible = t >= 1.4;
                phases.forEach((satellite, i) => {
                    const angle = -Math.PI * 0.78 + (i / 6) * Math.PI * 1.56;
                    satellite.position.set(
                        Math.sin(angle) * 1.48,
                        Math.cos(angle) * 1.48,
                        0.055,
                    );
                    satellite.scale.setScalar(0.15 + align * 0.85);
                });
                ripples.forEach((ripple, i) =>
                    ripple.scale.setScalar(
                        0.28 + ((t * 0.18 + i * 0.23) % 1) * 1.18,
                    ),
                );
                starlight.forEach((particle, i) => {
                    const fall = phase(
                        t,
                        3.2 + (i % 8) * 0.07,
                        4.65 + (i % 6) * 0.1,
                    );
                    const angle = i * 2.4;
                    const radius = 0.45 + (i % 7) * 0.14;
                    particle.visible = t >= 3.2 + (i % 8) * 0.07;
                    particle.position.set(
                        Math.cos(angle) * radius * (1 - fall * 0.15),
                        2.9 - fall * 2.75 + (i % 3) * 0.08,
                        Math.sin(angle) * radius,
                    );
                    particle.scale.setScalar(
                        0.5 + Math.sin(fall * Math.PI) * 0.6,
                    );
                    particle.rotation.set(0, t * 0.25 + i, Math.PI / 4);
                });
            } else {
                festival.visible = true;
                const lift = phase(t, 1.2, 3);
                const canopy = phase(t, 3, 4.4);
                lanterns.forEach(({ group: lantern, paper }, i) => {
                    const ignite = phase(t, 0.15 + i * 0.09, 0.45 + i * 0.09);
                    const angle = (i / 12) * TAU + lift * 0.3;
                    const radius =
                        1.45 - lift * 0.42 + canopy * (0.7 + (i % 2) * 0.4);
                    lantern.position.set(
                        Math.cos(angle) * radius,
                        0.42 +
                            lift * (1.25 + (i % 3) * 0.22) +
                            canopy * (0.3 + (i % 2) * 0.28),
                        Math.sin(angle) * radius * 0.85,
                    );
                    lantern.rotation.set(
                        Math.sin(t * 0.7 + i) * 0.07 * lift,
                        angle,
                        Math.cos(t * 0.6 + i) * 0.08 * lift,
                    );
                    lantern.scale.setScalar(0.7 + ignite * 0.3);
                    paper.emissiveIntensity = 0.03 + ignite * 0.82;
                    paper.opacity = fade * (0.4 + ignite * 0.6);
                });
                autumn.forEach((petal, i) => {
                    const drift = phase(t, 2.5 + (i % 4) * 0.1, 5.1);
                    const angle = i * 2.4 + drift * 0.55;
                    const radius = 0.65 + (i % 5) * 0.28 + drift * 0.25;
                    petal.visible = t > 2.5;
                    petal.position.set(
                        Math.cos(angle) * radius,
                        2.25 - drift * (1.25 + (i % 3) * 0.2),
                        Math.sin(angle) * radius,
                    );
                    petal.rotation.set(drift * 1.8 + i, angle, drift * 2 + i);
                    petal.scale.set(0.07, 0.16, 0.02);
                });
                wishes.forEach((particle, i) => {
                    const angle = i * 2.4 + t * 0.12;
                    const radius = 0.7 + (i % 7) * 0.2;
                    particle.position.set(
                        Math.cos(angle) * radius,
                        0.18 +
                            lift * (0.35 + (i % 6) * 0.32) +
                            Math.sin(t * 0.5 + i) * 0.08,
                        Math.sin(angle) * radius,
                    );
                    particle.scale.setScalar(
                        (0.25 + (i % 3) * 0.12) * phase(t, 0.4, 1.4),
                    );
                });
            }
        },
        dispose() {
            parent.remove(root);
            geometries.forEach((value) => value.dispose());
            materials.forEach((value) => value.dispose());
        },
    };
}
