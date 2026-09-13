import * as THREE from 'three';
import { createPaidTableModel } from './paidTableModel.ts';

/** An oak council table, with a leather writing surface and cast brass furniture. */
export function createFoundersTable(scene: THREE.Scene) {
    const model = createPaidTableModel(scene, "Founder's Oak council table");
    const { geometry, material, texture, part } = model;
    try {
        const oak = material(0xffffff, 0, 0.74);
        const carved = material(0x43291c, 0, 0.87);
        const edge = material(0x916137, 0, 0.7);
        const brass = material(0xb69a55, 0.72, 0.37);
        const agedBrass = material(0x73623d, 0.65, 0.49);
        const leather = material(0xffffff, 0, 0.89);
        const book = material(0x233e32, 0, 0.87);
        const paper = material(0xc8b895, 0, 0.96);
        const wax = material(0x8a3027, 0, 0.6);
        const ink = material(0x171b19, 0.28, 0.32);
        const ivory = material(0xecd6a7, 0, 0.83);
        const flame = material(0xffdf9a, 0, 0.38);
        flame.emissive.setHex(0xffb958);
        flame.emissiveIntensity = 1.1;

        oak.map = texture(1024, 1024, (ctx) => {
            ctx.fillStyle = '#88603b';
            ctx.fillRect(0, 0, 1024, 1024);
            // Quarter-cut parquet runs toward the center, following the joinery.
            for (let quadrant = 0; quadrant < 4; quadrant++) {
                ctx.save();
                ctx.translate(512, 512);
                ctx.rotate((quadrant * Math.PI) / 2);
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.lineTo(-512, -512);
                ctx.lineTo(512, -512);
                ctx.closePath();
                ctx.clip();
                ctx.fillStyle = ['#8c653e', '#795436', '#986e45', '#805b38'][
                    quadrant
                ];
                ctx.fillRect(-512, -512, 1024, 512);
                for (let stripe = 0; stripe < 185; stripe++) {
                    const x = -512 + stripe * 5.6;
                    ctx.strokeStyle =
                        stripe % 4 === 0 ? '#d2a87438' : '#38241232';
                    ctx.lineWidth = stripe % 3 === 0 ? 1.4 : 0.7;
                    ctx.beginPath();
                    ctx.moveTo(x, -512);
                    ctx.bezierCurveTo(
                        x + Math.sin(stripe) * 13,
                        -350,
                        x - 9,
                        -160,
                        x + 3,
                        0,
                    );
                    ctx.stroke();
                }
                for (let plank = 0; plank < 8; plank++) {
                    ctx.fillStyle = '#34251542';
                    ctx.fillRect(-512 + plank * 144, -512, 2, 512);
                    ctx.fillStyle = '#e6bf822d';
                    ctx.fillRect(-509 + plank * 144, -512, 1, 512);
                }
                ctx.restore();
            }
        });
        leather.map = texture(1024, 768, (ctx) => {
            ctx.fillStyle = '#173e30';
            ctx.fillRect(0, 0, 1024, 768);
            // Fine deterministic leather pores, rather than a flat green fill.
            for (let i = 0; i < 15000; i++) {
                const x = (i * 73.31) % 1024;
                const y = (i * 47.17 + Math.sin(i) * 11) % 768;
                ctx.fillStyle = i % 3 === 0 ? '#8eaa7520' : '#081b1627';
                ctx.fillRect(x, y, i % 4 === 0 ? 2 : 1, 1);
            }
            // These octagons follow the leather's clipped corners exactly.
            const border = (inset: number) => {
                const cutX = 151;
                const cutY = 184;
                ctx.beginPath();
                ctx.moveTo(cutX + inset * 0.4, inset);
                ctx.lineTo(1024 - cutX - inset * 0.4, inset);
                ctx.lineTo(1024 - inset, cutY + inset * 0.4);
                ctx.lineTo(1024 - inset, 768 - cutY - inset * 0.4);
                ctx.lineTo(1024 - cutX - inset * 0.4, 768 - inset);
                ctx.lineTo(cutX + inset * 0.4, 768 - inset);
                ctx.lineTo(inset, 768 - cutY - inset * 0.4);
                ctx.lineTo(inset, cutY + inset * 0.4);
                ctx.closePath();
            };
            ctx.strokeStyle = '#b8a06c';
            ctx.lineWidth = 1.4;
            border(18);
            ctx.stroke();
            ctx.setLineDash([4, 5]);
            ctx.strokeStyle = '#cab681';
            border(28);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.strokeStyle = '#8f996148';
            border(38);
            ctx.stroke();
        });

        const outline = (width: number, depth: number, cut: number) => {
            const x = width / 2;
            const z = depth / 2;
            return [
                [-x + cut, -z],
                [x - cut, -z],
                [x, -z + cut],
                [x, z - cut],
                [x - cut, z],
                [-x + cut, z],
                [-x, z - cut],
                [-x, -z + cut],
            ].map(([px, pz]) => new THREE.Vector2(px, pz));
        };
        const slab = (
            width: number,
            depth: number,
            cut: number,
            height: number,
            bevel = 0.025,
        ) => {
            const shape = new THREE.Shape(outline(width, depth, cut));
            const value = geometry(
                new THREE.ExtrudeGeometry(shape, {
                    depth: height,
                    bevelEnabled: bevel > 0,
                    bevelSegments: 1,
                    steps: 1,
                    bevelSize: bevel,
                    bevelThickness: bevel,
                }),
            );
            const positions = value.getAttribute('position');
            const uv = value.getAttribute('uv');
            for (let i = 0; i < uv.count; i++) {
                uv.setXY(
                    i,
                    positions.getX(i) / width + 0.5,
                    positions.getY(i) / depth + 0.5,
                );
            }
            value.rotateX(-Math.PI / 2);
            return value;
        };
        // Crisp chamfered shoulders and three substantial layers of furniture.
        part(slab(9.83, 6.72, 1.57, 0.45), carved, 0, -0.62);
        part(slab(10.04, 6.94, 1.62, 0.052), agedBrass, 0, -0.17);
        part(slab(10.14, 7.02, 1.64, 0.18), oak, 0, -0.13);
        part(slab(8.15, 5.12, 1.22, 0.024, 0.008), brass, 0, 0.073);
        part(slab(8.06, 5.03, 1.2, 0.009, 0.008), leather, 0, 0.104);

        const box = geometry(new THREE.BoxGeometry(1, 1, 1));
        const cylinder = geometry(new THREE.CylinderGeometry(1, 1, 1, 16));
        const cone = geometry(new THREE.CylinderGeometry(0.54, 1, 1, 12));
        const sphere = geometry(new THREE.SphereGeometry(1, 12, 8));
        const torus = geometry(new THREE.TorusGeometry(1, 0.12, 6, 20));
        const vertices = outline(9.9, 6.8, 1.6);
        for (let i = 0; i < vertices.length; i++) {
            const a = vertices[i];
            const b = vertices[(i + 1) % vertices.length];
            const dx = b.x - a.x;
            const dz = b.y - a.y;
            const length = Math.hypot(dx, dz);
            const angle = -Math.atan2(dz, dx);
            // Recessed apron panels are individually framed and pegged.
            const panels = Math.max(1, Math.round(length / 1.15));
            for (let panel = 0; panel < panels; panel++) {
                const t = (panel + 0.5) / panels;
                const x = a.x + dx * t;
                const z = a.y + dz * t;
                part(
                    box,
                    edge,
                    x,
                    -0.39,
                    z,
                    length / panels - 0.1,
                    0.27,
                    0.055,
                    0,
                    angle,
                );
                part(
                    box,
                    carved,
                    x * 1.003,
                    -0.385,
                    z * 1.003,
                    length / panels - 0.2,
                    0.18,
                    0.045,
                    0,
                    angle,
                );
                part(
                    sphere,
                    brass,
                    x * 1.006,
                    -0.385,
                    z * 1.006,
                    0.025,
                    0.025,
                    0.025,
                );
            }
            part(
                box,
                brass,
                a.x,
                0.078,
                a.y,
                0.14,
                0.028,
                0.31,
                0,
                angle + Math.PI / 4,
            );
            for (const offset of [-0.08, 0.08]) {
                part(
                    sphere,
                    agedBrass,
                    a.x + Math.sin(angle) * offset,
                    0.099,
                    a.y + Math.cos(angle) * offset,
                    0.026,
                    0.009,
                    0.026,
                );
            }
        }

        // Small crown escutcheons are cast into the broad oak ends.
        for (const sign of [-1, 1]) {
            const z = sign * 3.03;
            part(cylinder, agedBrass, 0, 0.075, z, 0.275, 0.02, 0.275);
            part(torus, brass, 0, 0.094, z, 0.257, 0.257, 0.257, Math.PI / 2);
            part(box, brass, 0, 0.1, z + sign * 0.075, 0.26, 0.015, 0.065);
            for (let tip = -1; tip <= 1; tip++) {
                part(
                    box,
                    brass,
                    tip * 0.096,
                    0.1,
                    z - sign * 0.015,
                    0.057,
                    0.015,
                    tip === 0 ? 0.18 : 0.14,
                    0,
                    tip * 0.2,
                );
                part(
                    sphere,
                    brass,
                    tip * 0.096,
                    0.105,
                    z - sign * (tip === 0 ? 0.105 : 0.085),
                    0.031,
                    0.014,
                    0.031,
                );
            }
        }

        // Cast three-light candelabra occupy opposite outer shoulders.
        for (const [x, z] of [
            [-4.12, -1.08],
            [4.12, 1.08],
        ]) {
            part(cone, agedBrass, x, 0.19, z, 0.28, 0.15, 0.28);
            part(cylinder, brass, x, 0.285, z, 0.16, 0.07, 0.16);
            part(cone, brass, x, 0.5, z, 0.075, 0.4, 0.075);
            part(cylinder, brass, x, 0.735, z, 0.036, 0.19, 0.036);
            part(sphere, brass, x, 0.42, z, 0.11, 0.105, 0.11);
            for (const arm of [-1, 0, 1]) {
                const az = z + arm * 0.32;
                const height = arm === 0 ? 0.88 : 0.73;
                if (arm !== 0) {
                    part(
                        cylinder,
                        brass,
                        x,
                        0.57,
                        z + arm * 0.16,
                        0.033,
                        0.36,
                        0.033,
                        (arm * Math.PI) / 3,
                    );
                    part(cone, brass, x, 0.66, az, 0.045, 0.18, 0.045);
                }
                part(cone, brass, x, height - 0.065, az, 0.12, 0.07, 0.12);
                part(
                    cylinder,
                    ivory,
                    x,
                    height + 0.075,
                    az,
                    0.064,
                    0.23,
                    0.064,
                );
                part(sphere, flame, x, height + 0.25, az, 0.038, 0.092, 0.038);
            }
        }

        // A sealed council folio; the other writing corner carries an inkwell.
        const folioX = -4.04;
        const folioZ = 1.05;
        const folioAngle = -0.16;
        for (const y of [0.142, 0.294]) {
            part(
                box,
                book,
                folioX,
                y,
                folioZ,
                0.62,
                0.048,
                0.82,
                0,
                folioAngle,
            );
        }
        part(
            box,
            paper,
            folioX,
            0.218,
            folioZ,
            0.565,
            0.105,
            0.755,
            0,
            folioAngle,
        );
        part(
            box,
            brass,
            folioX,
            0.324,
            folioZ,
            0.066,
            0.018,
            0.83,
            0,
            folioAngle,
        );
        part(cylinder, wax, folioX, 0.344, folioZ + 0.045, 0.095, 0.022, 0.095);
        part(
            box,
            brass,
            folioX,
            0.361,
            folioZ + 0.045,
            0.055,
            0.006,
            0.022,
            0,
            -0.2,
        );
        part(cylinder, agedBrass, 4.12, 0.16, -1.05, 0.25, 0.064, 0.25);
        part(cone, ink, 4.12, 0.305, -1.05, 0.17, 0.24, 0.17);
        part(cylinder, brass, 4.12, 0.437, -1.05, 0.11, 0.04, 0.11);
        part(cylinder, ink, 4.12, 0.46, -1.05, 0.065, 0.009, 0.065);
        part(
            cylinder,
            edge,
            4.16,
            0.73,
            -1.07,
            0.013,
            0.62,
            0.013,
            0,
            0,
            -0.16,
        );
        part(
            sphere,
            ivory,
            4.2,
            0.88,
            -1.07,
            0.055,
            0.21,
            0.018,
            0.1,
            0,
            -0.16,
        );

        // Visible trestle feet anchor the weight below the octagonal slab.
        for (const x of [-3.65, 3.65]) {
            for (const z of [-1.95, 1.95]) {
                part(cone, carved, x, -0.86, z, 0.23, 0.56, 0.23);
                part(cylinder, brass, x, -1.1, z, 0.17, 0.055, 0.17);
            }
        }
        model.finish();
        return {
            root: model.root,
            update(darkness: number) {
                const night = Number.isFinite(darkness)
                    ? THREE.MathUtils.clamp(darkness, 0, 1)
                    : 0;
                flame.emissiveIntensity = 1.1 + night * 1.5;
                brass.emissive.setHex(0x624011);
                brass.emissiveIntensity = night * 0.1;
            },
            dispose: model.dispose,
        };
    } catch (error) {
        model.dispose();
        throw error;
    }
}
