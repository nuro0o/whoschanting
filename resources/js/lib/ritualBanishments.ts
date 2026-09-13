import * as THREE from 'three';

const TAU = Math.PI * 2;
const clamp = (value: number) => Math.max(0, Math.min(1, value));
const phase = (time: number, start: number, end: number) => {
    const t = clamp((time - start) / (end - start));
    return t * t * (3 - 2 * t);
};

/** Three miniature stage productions. All geometry is allocated once per table. */
export function createPaidBanishments(parent: THREE.Group) {
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.MeshStandardMaterial>();
    const root = new THREE.Group();
    root.name = 'Paid banishment stage';
    root.visible = false;
    parent.add(root);
    const group = (name: string, owner: THREE.Group = root) => {
        const result = new THREE.Group();
        result.name = name;
        owner.add(result);
        return result;
    };
    const geometry = <T extends THREE.BufferGeometry>(value: T): T => {
        geometries.add(value);
        return value;
    };
    const material = (color: number, emissive: number, metalness = 0.3) => {
        const value = new THREE.MeshStandardMaterial({
            color,
            emissive,
            emissiveIntensity: 0.65,
            metalness,
            roughness: 0.36,
            transparent: true,
            depthWrite: false,
            side: THREE.DoubleSide,
        });
        materials.add(value);
        return value;
    };
    const mesh = (
        shape: THREE.BufferGeometry,
        paint: THREE.Material,
        owner: THREE.Object3D,
        name = '',
    ) => {
        const value = new THREE.Mesh(shape, paint);
        value.name = name;
        owner.add(value);
        return value;
    };
    const gold = material(0xffdd8a, 0xb77a16, 0.75);
    const brass = material(0x84602a, 0x473214, 0.85);
    const goldDust = material(0xffe6a8, 0xbf8a2b, 0.65);
    const silver = material(0xd9dcff, 0x828dda, 0.7);
    const violet = material(0x7971c5, 0x534bb1);
    const night = material(0x100e29, 0x15102b, 0.05);
    const stars = material(0xe5ecff, 0xb5c8ff);
    const copper = material(0xc4712d, 0x733515, 0.7);
    const flame = material(0xffbb49, 0xe47816);
    const hot = material(0xffe1a0, 0xdc9a37);
    const embers = material(0xf39a37, 0xc45116);
    const ash = material(0x9a8773, 0x21190e);
    const pawnMaterial = material(0x48625c, 0x172623, 0.15);
    pawnMaterial.depthWrite = true;
    const pawn = group('Condemned pawn');
    mesh(
        geometry(new THREE.ConeGeometry(0.26, 0.7, 16)),
        pawnMaterial,
        pawn,
    ).position.y = 0.35;
    mesh(
        geometry(new THREE.SphereGeometry(0.145, 16, 10)),
        pawnMaterial,
        pawn,
    ).position.y = 0.88;
    const torus = geometry(new THREE.TorusGeometry(1, 0.025, 6, 64));
    const arc = geometry(
        new THREE.TorusGeometry(1, 0.038, 6, 40, Math.PI * 0.82),
    );
    const shard = geometry(new THREE.OctahedronGeometry(0.065));
    const coin = geometry(new THREE.CylinderGeometry(0.085, 0.085, 0.025, 12));
    const bar = geometry(new THREE.BoxGeometry(0.035, 0.025, 0.13));

    const tribunal = group('Gilded tribunal');
    const seals = [0.68, 1.02, 1.18].map((radius, i) => {
        const value = mesh(torus, i === 1 ? brass : gold, tribunal);
        value.rotation.x = -Math.PI / 2;
        value.scale.setScalar(radius);
        value.position.y = 0.018 + i * 0.008;
        return value;
    });
    const engravings = group('Engraved floor seal', tribunal);
    for (let i = 0; i < 32; i++) {
        const angle = (i / 32) * TAU;
        const rune = mesh(bar, i % 4 === 0 ? gold : brass, engravings);
        rune.position.set(Math.cos(angle) * 1.1, 0.035, Math.sin(angle) * 1.1);
        rune.rotation.y = -angle + Math.PI / 2;
        rune.scale.z = i % 4 === 0 ? 1.35 : 0.65;
    }
    const armillary = group('Segmented armillary cage', tribunal);
    const orbits = Array.from({ length: 3 }, (_, i) => {
        const orbit = group(`Tribunal orbit ${i + 1}`, armillary);
        const first = mesh(arc, gold, orbit);
        const second = mesh(arc, brass, orbit);
        first.rotation.z = i * 0.4;
        second.rotation.z = Math.PI + i * 0.4;
        return orbit;
    });
    const crown = group('Suspended crown', tribunal);
    const band = mesh(torus, gold, crown);
    band.rotation.x = Math.PI / 2;
    band.scale.setScalar(0.4);
    const tooth = geometry(new THREE.ConeGeometry(0.095, 0.3, 4));
    for (let i = 0; i < 8; i++) {
        const angle = (i / 8) * TAU;
        const point = mesh(tooth, gold, crown);
        point.position.set(Math.cos(angle) * 0.37, 0.1, Math.sin(angle) * 0.37);
    }
    const stamp = mesh(
        geometry(new THREE.CylinderGeometry(0.48, 0.48, 0.07, 32)),
        gold,
        tribunal,
        'Final gold seal',
    );
    const goldParticles = Array.from({ length: 28 }, (_, i) =>
        mesh(i % 3 ? coin : shard, goldDust, tribunal),
    );

    const eclipse = group('Lunar eclipse doorway');
    const portal = group('Eclipse aperture', eclipse);
    portal.position.set(0.35, 1.25, 0);
    const disc = mesh(
        geometry(new THREE.CircleGeometry(1, 64)),
        night,
        portal,
        'Dark portal interior',
    );
    disc.scale.set(0.8, 1.25, 1);
    const crescentShape = new THREE.Shape();
    crescentShape.absarc(0, 0, 1, -Math.PI / 2, Math.PI / 2, false);
    crescentShape.quadraticCurveTo(0.93, 0, 0, -1);
    const crescentGeometry = geometry(
        new THREE.ShapeGeometry(crescentShape, 40),
    );
    const rightMoon = mesh(
        crescentGeometry,
        silver,
        portal,
        'Right eclipse crescent',
    );
    const leftMoon = mesh(
        crescentGeometry,
        silver,
        portal,
        'Left eclipse crescent',
    );
    leftMoon.rotation.z = Math.PI;
    rightMoon.position.z = leftMoon.position.z = 0.025;
    const portalRim = mesh(torus, violet, portal);
    portalRim.position.z = -0.025;
    portalRim.scale.set(0.81, 1.26, 1);
    const lunarParticles = Array.from({ length: 20 }, (_, i) =>
        mesh(shard, i % 4 ? stars : violet, eclipse),
    );
    const constellation = group('Broken constellation', eclipse);
    const starPoints = Array.from(
        { length: 9 },
        (_, i) =>
            new THREE.Vector3(
                Math.cos(i * 2.4) * (1.05 + i * 0.035) + 0.35,
                1.4 + Math.sin(i * 2.4) * 1.35,
                0.08,
            ),
    );
    const linkShape = geometry(new THREE.CylinderGeometry(0.009, 0.009, 1, 4));
    starPoints.forEach((point, i) => {
        mesh(shard, stars, constellation).position.copy(point);
        if (!i || i % 3 === 0) return;
        const previous = starPoints[i - 1];
        const link = mesh(linkShape, violet, constellation);
        link.position.copy(point).add(previous).multiplyScalar(0.5);
        link.scale.y = point.distanceTo(previous);
        link.quaternion.setFromUnitVectors(
            new THREE.Vector3(0, 1, 0),
            point.clone().sub(previous).normalize(),
        );
    });

    const pyre = group('Harvest pyre');
    const leafShape = new THREE.Shape();
    leafShape.moveTo(0, -0.25);
    leafShape.quadraticCurveTo(-0.25, 0, 0, 0.32);
    leafShape.quadraticCurveTo(0.25, 0.06, 0, -0.25);
    const leaf = geometry(new THREE.ShapeGeometry(leafShape, 5));
    const wreath = group('Kindled autumn wreath', pyre);
    const petals = Array.from({ length: 14 }, (_, i) => {
        const angle = (i / 14) * TAU;
        const value = mesh(leaf, i % 2 ? copper : embers, wreath);
        value.position.set(
            Math.cos(angle) * 0.93,
            0.035,
            Math.sin(angle) * 0.93,
        );
        value.rotation.set(-Math.PI / 2, 0, -angle);
        value.scale.set(1.2, 1.4, 1);
        return value;
    });
    // Tapered mesh ribbons give fire a continuous silhouette without sprites.
    const ribbonShape = (turns: number, offset: number) => {
        const positions: number[] = [];
        const indices: number[] = [];
        for (let i = 0; i <= 56; i++) {
            const t = i / 56;
            const angle = t * TAU * turns + offset;
            const radius = 0.8 * (1 - t) + 0.09;
            const width = Math.sin(t * Math.PI) * 0.17 + 0.014;
            for (const side of [-1, 1])
                positions.push(
                    Math.cos(angle) * (radius + width * side),
                    t * 2.65,
                    Math.sin(angle) * (radius + width * side),
                );
            if (i < 56) {
                const a = i * 2;
                indices.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
            }
        }
        const result = new THREE.BufferGeometry();
        result.setAttribute(
            'position',
            new THREE.Float32BufferAttribute(positions, 3),
        );
        result.setIndex(indices);
        result.computeVertexNormals();
        return geometry(result);
    };
    const fire = group('Twisting flame ribbons', pyre);
    for (let i = 0; i < 4; i++)
        mesh(ribbonShape(1.35, (i / 4) * TAU), i % 2 ? hot : flame, fire);
    const autumnParticles = Array.from({ length: 40 }, (_, i) =>
        mesh(i % 3 === 0 ? leaf : shard, i % 3 === 0 ? copper : embers, pyre),
    );
    const ashes = Array.from({ length: 14 }, () => mesh(leaf, ash, pyre));

    function reset() {
        root.visible = false;
        tribunal.visible = eclipse.visible = pyre.visible = false;
        pawn.visible = false;
    }

    return {
        reset,
        update(
            effect: string,
            seconds: number,
            origin: THREE.Vector3,
            reduced: boolean,
        ) {
            reset();
            root.visible = true;
            const t = reduced
                ? effect === 'gilded_vortex'
                    ? 2
                    : effect === 'lunar_rift'
                      ? 1.8
                      : 2.2
                : seconds;
            root.position.set(origin.x * 0.22, 0.29, origin.z * 0.22);
            pawn.visible = true;
            pawn.rotation.set(0, 0, 0);
            pawn.scale.setScalar(1);
            pawnMaterial.opacity = 1;
            const arrival = 1 - phase(t, 0, 0.8);
            pawn.position.set(
                (origin.x - root.position.x) * arrival,
                0,
                (origin.z - root.position.z) * arrival,
            );

            if (effect === 'gilded_vortex') {
                tribunal.visible = true;
                const summon = phase(t, 0, 0.9);
                const lift = phase(t, 0.9, 2.6);
                const compress = phase(t, 2.6, 3.25);
                const tail = 1 - phase(t, 3.8, 4.8);
                gold.opacity = brass.opacity = summon * tail;
                goldDust.opacity = phase(t, 3.1, 3.35) * tail;
                tribunal.scale.setScalar(0.75 + summon * 0.25);
                engravings.rotation.y = t * 0.16;
                seals.forEach((seal, i) =>
                    seal.scale.setScalar(
                        [0.68, 1.02, 1.18][i] * (1 + compress * 0.13),
                    ),
                );
                armillary.visible = t < 3.35;
                armillary.position.y = 0.75 + lift * 0.3;
                armillary.scale.setScalar(
                    Math.max(0.02, summon * (1 - compress)),
                );
                orbits.forEach((orbit, i) =>
                    orbit.rotation.set(
                        (i * Math.PI) / 3 + t * 0.7,
                        t * (i % 2 ? -0.8 : 0.8),
                        i * 0.7,
                    ),
                );
                crown.position.y = 2.25 + lift * 0.2 - compress * 2.35;
                crown.rotation.y = t * 0.55;
                crown.scale.setScalar(1 - compress * 0.68);
                pawn.position.y = lift * 0.95 * (1 - compress);
                pawn.scale.set(
                    1 - compress * 0.8,
                    Math.max(0.01, 1 - compress),
                    1 - compress * 0.8,
                );
                pawn.rotation.y = lift * Math.PI * 1.5;
                pawn.visible = t < 3.25;
                stamp.visible = t >= 3.1;
                stamp.position.y = 0.055;
                stamp.scale.setScalar(phase(t, 3.1, 3.35));
                goldParticles.forEach((particle, i) => {
                    const burst = phase(t, 3.2, 4.7);
                    const angle = i * 2.4;
                    const radius = 0.3 + burst * (0.7 + (i % 5) * 0.15);
                    particle.position.set(
                        Math.cos(angle) * radius,
                        0.08 +
                            Math.sin(burst * Math.PI) * (0.3 + (i % 4) * 0.2),
                        Math.sin(angle) * radius,
                    );
                    particle.rotation.set(burst * 6 + i, angle, burst * 4);
                    particle.scale.setScalar(0.7 + (i % 3) * 0.2);
                });
            } else if (effect === 'lunar_rift') {
                eclipse.visible = true;
                const open = phase(t, 0, 1.1);
                const pull = phase(t, 1.1, 2.9);
                const close = phase(t, 2.9, 3.55);
                const tail = 1 - phase(t, 3.75, 4.6);
                silver.opacity = violet.opacity = open * tail;
                night.opacity = 0.96 * open * (1 - close);
                stars.opacity = open * tail;
                portal.scale.set(
                    Math.max(0.005, open * (1 - close)),
                    0.75 + open * 0.25,
                    1,
                );
                portal.visible = t < 3.58;
                leftMoon.position.x = -0.08;
                rightMoon.position.x = 0.08;
                leftMoon.scale.set(0.8, 1.25, 1);
                rightMoon.scale.set(0.8, 1.25, 1);
                pawn.position.x += -0.85 * open + pull * 1.2;
                pawn.position.y = pull * 0.64;
                pawn.position.z += 0.18 - pull * 0.28;
                pawn.rotation.z = -pull * 0.85;
                pawn.scale.set(
                    Math.max(0.01, 1 - phase(t, 2.2, 2.95)),
                    1 - pull * 0.3,
                    1 - pull * 0.8,
                );
                pawnMaterial.opacity = 1 - phase(t, 2.4, 2.95);
                pawn.visible = t < 2.95;
                constellation.rotation.z = (1 - open) * -0.35 + close * 0.12;
                constellation.scale.setScalar(
                    0.85 + open * 0.15 + close * 0.05,
                );
                lunarParticles.forEach((particle, i) => {
                    const angle = i * 2.4 + t * 0.12;
                    const radius =
                        1.1 + (i % 4) * 0.12 - pull * 0.4 + close * 0.5;
                    particle.position.set(
                        0.35 + Math.cos(angle) * radius,
                        1.25 + Math.sin(angle) * radius * 1.05,
                        0.12 + Math.sin(i) * 0.12,
                    );
                    particle.scale.setScalar(0.4 + (i % 3) * 0.2);
                    particle.rotation.set(0, 0, angle);
                });
            } else {
                pyre.visible = true;
                const ignite = phase(t, 0, 1);
                const lift = phase(t, 1, 2.7);
                const scatter = phase(t, 2.7, 3.15);
                const drift = phase(t, 3, 4.8);
                const tail = 1 - phase(t, 4.15, 5);
                copper.opacity = ignite * tail;
                flame.opacity = hot.opacity = ignite * (1 - phase(t, 2.9, 3.6));
                embers.opacity = ignite * tail;
                ash.opacity = phase(t, 3.4, 4.2) * tail;
                wreath.rotation.y = lift * 0.35;
                petals.forEach((petal, i) => {
                    petal.position.y = 0.035 + Math.sin(i * 1.7) * 0.03 * lift;
                    petal.scale.set(1.2, 1.4 - scatter * 0.5, 1);
                });
                fire.rotation.y = t * 1.15;
                fire.scale.set(
                    0.65 + lift * 0.35 + scatter * 0.25,
                    0.08 + ignite * 0.65 + lift * 0.35,
                    0.65 + lift * 0.35 + scatter * 0.25,
                );
                pawn.position.y = lift * 1.3;
                pawn.rotation.set(0, lift * Math.PI * 2, lift * 0.12);
                pawn.scale.setScalar(1 - scatter * 0.95);
                pawnMaterial.opacity = 1 - scatter;
                pawn.visible = t < 3.15;
                autumnParticles.forEach((particle, i) => {
                    const angle = i * 2.4 + t * (1 - drift) * 0.85;
                    const height = (i % 9) / 9;
                    const radius =
                        (0.85 - height * 0.45) * (0.7 + ignite * 0.3) +
                        drift * (0.5 + (i % 4) * 0.18);
                    particle.position.set(
                        Math.cos(angle) * radius,
                        0.08 +
                            height * (0.4 + lift * 2.3) * (1 - drift) +
                            Math.sin(drift * Math.PI) * 0.75,
                        Math.sin(angle) * radius,
                    );
                    particle.rotation.set(t * 0.65 + i, angle, i + drift * 3);
                    particle.scale.setScalar(
                        i % 3 === 0
                            ? 0.32 + scatter * 0.22
                            : 0.55 + height * 0.3,
                    );
                });
                ashes.forEach((particle, i) => {
                    const angle = i * 2.4;
                    const radius = 0.5 + (i % 5) * 0.22;
                    particle.position.set(
                        Math.cos(angle) * radius,
                        0.035 + (1 - drift) * (0.6 + (i % 4) * 0.2),
                        Math.sin(angle) * radius,
                    );
                    particle.rotation.set(
                        -Math.PI / 2 + (1 - drift) * i,
                        0,
                        angle + drift,
                    );
                    particle.scale.setScalar(0.12 + (i % 3) * 0.04);
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
