import * as THREE from 'three';
import { createPaidTableModel } from './paidTableModel.ts';

/** A carved lunar altar, with its astronomy kept outside the playing area. */
export function createMoonlitTable(scene: THREE.Scene) {
    const model = createPaidTableModel(scene, 'Moonlit observatory table');
    const { geometry, material, texture, part } = model;

    try {
        const stone = material(0x29334d, 0.16, 0.64);
        const darkStone = material(0x141d30, 0.12, 0.76);
        const silver = material(0xa9b8cf, 0.74, 0.34);
        const pewter = material(0x536681, 0.62, 0.44);
        const moonstone = material(0xc0c9ed, 0.28, 0.29);
        const amethyst = material(0x7968ab, 0.36, 0.31);
        moonstone.flatShading = true;
        amethyst.flatShading = true;
        const ivory = material(0xd9d8e5, 0.05, 0.78);
        const light = material(0xe2e5ff, 0.08, 0.3);
        light.emissive.setHex(0xa3aeff);
        light.emissiveIntensity = 0.8;
        moonstone.emissive.setHex(0x6875b6);
        moonstone.emissiveIntensity = 0.06;

        function outline(scale = 1) {
            const points: THREE.Vector2[] = [];
            for (let i = 0; i < 192; i++) {
                const angle = (i / 192) * Math.PI * 2;
                // Twelve shallow lobes give the whole slab a sculpted silhouette.
                const scallop = 1 - 0.018 * (1 - Math.cos(angle * 12));
                points.push(
                    new THREE.Vector2(
                        Math.cos(angle) * 5.05 * scale * scallop,
                        Math.sin(angle) * 3.55 * scale * scallop,
                    ),
                );
            }
            return points;
        }

        function slab(scale: number, depth: number) {
            const shape = geometry(
                new THREE.ExtrudeGeometry(new THREE.Shape(outline(scale)), {
                    depth,
                    bevelEnabled: true,
                    bevelSegments: 2,
                    bevelSize: 0.035,
                    bevelThickness: 0.025,
                    steps: 1,
                    curveSegments: 1,
                }),
            );
            shape.rotateX(-Math.PI / 2);
            return shape;
        }

        part(slab(0.948, 0.33), darkStone, 0, -0.56);
        part(slab(0.974, 0.07), pewter, 0, -0.25);
        part(slab(1, 0.2), stone, 0, -0.16);

        const surface = material(0xffffff, 0.12, 0.66);
        surface.map = texture(1024, 768, (ink) => {
            ink.fillStyle = '#202b44';
            ink.fillRect(0, 0, 1024, 768);
            // Broad mineral clouds, deterministic fine grain and restrained veins.
            for (let i = 0; i < 46; i++) {
                const x = (i * 317 + 79) % 1100;
                const y = (i * 193 + 37) % 820;
                const radius = 70 + ((i * 43) % 170);
                const cloud = ink.createRadialGradient(x, y, 0, x, y, radius);
                cloud.addColorStop(0, i % 3 ? '#a0a3bb0b' : '#050c2130');
                cloud.addColorStop(1, '#202b4400');
                ink.fillStyle = cloud;
                ink.fillRect(x - radius, y - radius, radius * 2, radius * 2);
            }
            for (let i = 0; i < 16000; i++) {
                ink.fillStyle = i % 2 ? '#d4dcf208' : '#0209170a';
                ink.fillRect((i * 173.31) % 1024, (i * 97.73) % 768, 1.2, 1.2);
            }
            for (let i = 0; i < 12; i++) {
                const x = i * 121 - 120;
                ink.strokeStyle = '#8e9db013';
                ink.lineWidth = i % 3 ? 0.7 : 1.4;
                ink.beginPath();
                ink.moveTo(x, 0);
                ink.bezierCurveTo(x + 140, 200, x - 140, 480, x + 240, 768);
                ink.stroke();
            }
            for (const inset of [0, 9]) {
                ink.strokeStyle = inset ? '#a8b9d24a' : '#b1c6e187';
                ink.lineWidth = inset ? 0.8 : 1.6;
                ink.beginPath();
                ink.ellipse(
                    512,
                    384,
                    459 - inset,
                    322 - inset,
                    0,
                    0,
                    Math.PI * 2,
                );
                ink.stroke();
            }
            // An instrument's calibrated edge, with longer marks at the hours.
            for (let i = 0; i < 120; i++) {
                const a = (i / 120) * Math.PI * 2;
                const length = i % 5 ? 4 : 10;
                ink.strokeStyle = i % 5 ? '#c3d1e73c' : '#c3d1e790';
                ink.lineWidth = 1;
                ink.beginPath();
                ink.moveTo(512 + Math.cos(a) * 445, 384 + Math.sin(a) * 308);
                ink.lineTo(
                    512 + Math.cos(a) * (445 - length),
                    384 + Math.sin(a) * (308 - length),
                );
                ink.stroke();
            }
            // The lunar cycle sits on the border, leaving the ritual dial clear.
            for (let i = 0; i < 16; i++) {
                const a = (i / 16) * Math.PI * 2;
                const x = 512 + Math.cos(a) * 412;
                const y = 384 + Math.sin(a) * 280;
                ink.save();
                ink.translate(x, y);
                ink.fillStyle = '#b8c9e0';
                ink.beginPath();
                ink.arc(0, 0, 8, 0, Math.PI * 2);
                ink.fill();
                const phase = i % 8;
                if (phase !== 4) {
                    ink.save();
                    ink.beginPath();
                    ink.arc(0, 0, 8.3, 0, Math.PI * 2);
                    ink.clip();
                    ink.fillStyle = '#26314a';
                    ink.beginPath();
                    ink.ellipse(
                        (phase < 4 ? 1 : -1) * phaseDistance(phase),
                        0,
                        8,
                        8.2,
                        0,
                        0,
                        Math.PI * 2,
                    );
                    ink.fill();
                    ink.restore();
                }
                ink.strokeStyle = '#b9c9e378';
                ink.lineWidth = 0.8;
                ink.beginPath();
                ink.arc(0, 0, 8.5, 0, Math.PI * 2);
                ink.stroke();
                ink.restore();
            }
            // Small mapped constellations only on the unoccupied outer shoulders.
            for (const side of [-1, 1]) {
                const stars = [
                    [0, 0],
                    [30, -17],
                    [58, 3],
                    [80, -31],
                    [107, -19],
                ];
                for (const z of [-1, 1]) {
                    ink.save();
                    ink.translate(512 + side * 252, 384 + z * 168);
                    ink.strokeStyle = '#91a8d359';
                    ink.lineWidth = 1;
                    ink.beginPath();
                    stars.forEach(([x, y], index) => {
                        if (index === 0) ink.moveTo(x * side, y * z);
                        else ink.lineTo(x * side, y * z);
                    });
                    ink.stroke();
                    stars.forEach(([x, y], index) => {
                        ink.fillStyle = '#c5d4efb3';
                        ink.beginPath();
                        ink.arc(
                            x * side,
                            y * z,
                            index % 2 ? 1.8 : 2.8,
                            0,
                            Math.PI * 2,
                        );
                        ink.fill();
                    });
                    ink.restore();
                }
            }
        });

        const top = geometry(
            new THREE.ShapeGeometry(new THREE.Shape(outline(0.988))),
        );
        const positions = top.getAttribute('position');
        const uvs = top.getAttribute('uv');
        for (let i = 0; i < positions.count; i++) {
            uvs.setXY(
                i,
                (positions.getX(i) + 5.05) / 10.1,
                (positions.getY(i) + 3.55) / 7.1,
            );
        }
        top.rotateX(-Math.PI / 2);
        part(top, surface, 0, 0.071);

        const rimShape = new THREE.Shape(outline(0.996));
        rimShape.holes.push(new THREE.Path(outline(0.987).reverse()));
        const rim = geometry(new THREE.ShapeGeometry(rimShape));
        rim.rotateX(-Math.PI / 2);
        part(rim, silver, 0, 0.075);

        const box = geometry(new THREE.BoxGeometry(1, 1, 1));
        const sphere = geometry(new THREE.SphereGeometry(1, 12, 8));
        const cylinder = geometry(new THREE.CylinderGeometry(1, 1, 1, 24));
        const plinth = geometry(new THREE.CylinderGeometry(0.72, 1, 1, 8));
        const crystal = geometry(
            new THREE.LatheGeometry(
                [
                    new THREE.Vector2(0, -0.5),
                    new THREE.Vector2(0.6, -0.5),
                    new THREE.Vector2(0.5, 0.2),
                    new THREE.Vector2(0, 0.5),
                ],
                6,
            ),
        );
        const gem = geometry(new THREE.OctahedronGeometry(1));
        const torus = geometry(new THREE.TorusGeometry(1, 0.035, 6, 48));
        // Vertical silver scoring catches the light along the deep stone apron.
        for (let i = 0; i < 72; i++) {
            const a = (i / 72) * Math.PI * 2;
            const scallop = 1 - 0.018 * (1 - Math.cos(a * 12));
            const x = Math.cos(a) * 4.79 * scallop;
            const z = Math.sin(a) * 3.37 * scallop;
            part(
                box,
                pewter,
                x,
                -0.385,
                z,
                0.023,
                i % 6 ? 0.16 : 0.24,
                0.028,
                0,
                -a,
            );
            if (i % 6 === 0)
                part(
                    gem,
                    moonstone,
                    x * 1.025,
                    -0.09,
                    z * 1.025,
                    0.07,
                    0.06,
                    0.045,
                    0,
                    -a,
                );
        }
        for (const x of [-2.8, 2.8]) {
            part(plinth, darkStone, x, -0.87, 0, 0.82, 0.77, 1.45);
            part(plinth, pewter, x, -1.22, 0, 0.85, 0.07, 1.5);
        }

        const crescentShape = new THREE.Shape();
        crescentShape.absarc(0, 0, 0.4, 1.15, Math.PI * 2 - 1.15, false);
        crescentShape.quadraticCurveTo(
            -0.2,
            0,
            Math.cos(1.15) * 0.4,
            Math.sin(1.15) * 0.4,
        );
        const crescent = geometry(
            new THREE.ExtrudeGeometry(crescentShape, {
                depth: 0.075,
                bevelEnabled: true,
                bevelSize: 0.015,
                bevelThickness: 0.015,
                bevelSegments: 2,
                curveSegments: 32,
                steps: 1,
            }),
        );
        // Two sculptural candle holders and two mineral displays balance the ends.
        for (const side of [-1, 1]) {
            const x = side * 4.02;
            const z = side * -1.18;
            part(cylinder, darkStone, x, 0.12, z, 0.4, 0.08, 0.32);
            part(cylinder, silver, x, 0.167, z, 0.34, 0.024, 0.27);
            part(
                crescent,
                silver,
                x,
                0.57,
                z,
                1,
                1,
                1,
                0,
                side < 0 ? Math.PI : 0,
            );
            part(cylinder, silver, x + side * 0.13, 0.36, z, 0.13, 0.06, 0.13);
            part(cylinder, ivory, x + side * 0.13, 0.51, z, 0.081, 0.25, 0.081);
            part(sphere, light, x + side * 0.13, 0.683, z, 0.038, 0.09, 0.038);

            const clusterZ = side * 1.28;
            part(cylinder, pewter, x, 0.115, clusterZ, 0.39, 0.045, 0.35);
            part(torus, silver, x, 0.15, clusterZ, 0.34, 0.29, 1, -Math.PI / 2);
            for (let i = 0; i < 7; i++) {
                const a = i * 2.4;
                const radius = i === 0 ? 0 : 0.16 + (i % 2) * 0.06;
                const height = i === 0 ? 0.64 : 0.23 + (i % 3) * 0.095;
                part(
                    crystal,
                    i % 3 ? amethyst : moonstone,
                    x + Math.cos(a) * radius,
                    0.145 + height / 2,
                    clusterZ + Math.sin(a) * radius,
                    i === 0 ? 0.23 : 0.17,
                    height,
                    i === 0 ? 0.23 : 0.17,
                    i === 0 ? 0 : Math.sin(a) * 0.19,
                    a,
                    i === 0 ? 0 : Math.cos(a) * 0.19,
                );
            }
        }

        model.finish();
        return {
            root: model.root,
            update(darkness: number) {
                const night = THREE.MathUtils.clamp(darkness, 0, 1);
                light.emissiveIntensity = 0.8 + night * 1.25;
                moonstone.emissiveIntensity = 0.06 + night * 0.35;
            },
            dispose: model.dispose,
        };
    } catch (error) {
        model.dispose();
        throw error;
    }
}

function phaseDistance(phase: number) {
    return [0, 3, 7.5, 12, 16, 12, 7.5, 3][phase];
}
