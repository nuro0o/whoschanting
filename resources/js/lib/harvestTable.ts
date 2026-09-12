import * as THREE from 'three';

/** A complete, lazily constructed table. Static details share instanced draws. */
export function createHarvestTable(scene: THREE.Scene) {
    const root = new THREE.Group();
    root.name = 'Harvest gathering table';
    const geometryPool = new Set<THREE.BufferGeometry>();
    const materialPool = new Set<THREE.Material>();
    const texturePool = new Set<THREE.Texture>();
    const batches = new Map<
        string,
        {
            geometry: THREE.BufferGeometry;
            material: THREE.Material;
            matrices: THREE.Matrix4[];
        }
    >();
    const transform = new THREE.Object3D();
    function dispose() {
        scene.remove(root);
        root.children.forEach((child) => {
            if (child instanceof THREE.InstancedMesh) child.dispose();
        });
        geometryPool.forEach((value) => value.dispose());
        materialPool.forEach((value) => value.dispose());
        texturePool.forEach((value) => value.dispose());
        geometryPool.clear();
        materialPool.clear();
        texturePool.clear();
        root.clear();
    }
    const geometry = <T extends THREE.BufferGeometry>(value: T) => {
        geometryPool.add(value);
        return value;
    };
    function material(color: number, metalness = 0, roughness = 0.75) {
        const value = new THREE.MeshStandardMaterial({
            color,
            metalness,
            roughness,
        });
        materialPool.add(value);
        return value;
    }
    function part(
        shape: THREE.BufferGeometry,
        finish: THREE.Material,
        x = 0,
        y = 0,
        z = 0,
        sx = 1,
        sy = 1,
        sz = 1,
        rx = 0,
        ry = 0,
        rz = 0,
    ) {
        const key = `${shape.uuid}:${finish.uuid}`;
        let batch = batches.get(key);
        if (!batch) {
            batch = { geometry: shape, material: finish, matrices: [] };
            batches.set(key, batch);
        }
        transform.position.set(x, y, z);
        transform.rotation.set(rx, ry, rz);
        transform.scale.set(sx, sy, sz);
        transform.updateMatrix();
        batch.matrices.push(transform.matrix.clone());
    }
    function canvasTexture(
        width: number,
        height: number,
        paint: (ink: CanvasRenderingContext2D) => void,
    ) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ink = canvas.getContext('2d');
        if (!ink) throw new Error('Harvest textures unavailable');
        paint(ink);
        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = 4;
        texturePool.add(texture);
        return texture;
    }
    try {
        const walnut = material(0x80533b);
        const carved = material(0x42281e);
        const copper = material(0xb77c42, 0.7, 0.38);
        const iron = material(0x342b25, 0.65, 0.42);
        const textile = material(0xffffff, 0, 1);
        textile.side = THREE.DoubleSide;
        const orange = material(0xb6551b);
        const ochre = material(0xc38d37);
        const sage = material(0x727a49);
        const cream = material(0xd5bb83);
        const stem = material(0x555032);
        const wheat = material(0xb99953);
        const ember = material(0xffd28a);
        ember.emissive.setHex(0xff922d);
        ember.emissiveIntensity = 1.8;

        // Long, irregular walnut boards with restrained grain and butterfly joins.
        const timber = material(0xffffff, 0, 0.82);
        timber.map = canvasTexture(1024, 1024, (ink) => {
            ink.fillStyle = '#493629';
            ink.fillRect(0, 0, 1024, 1024);
            for (let plank = 0; plank < 9; plank++) {
                const y = plank * 114;
                ink.fillStyle = ['#513c2e', '#453428', '#4b3629', '#58402e'][
                    plank % 4
                ];
                ink.fillRect(0, y, 1024, 113);
                for (let line = 0; line < 48; line++) {
                    const gy = y + line * 2.4;
                    ink.strokeStyle = line % 4 ? '#180f0a30' : '#bc94642d';
                    ink.lineWidth = line % 7 === 0 ? 1.8 : 0.8;
                    ink.beginPath();
                    ink.moveTo(0, gy);
                    ink.bezierCurveTo(
                        260,
                        gy + Math.sin(line * 1.7 + plank) * 7,
                        720,
                        gy - Math.cos(line + plank) * 9,
                        1024,
                        gy + Math.sin(line) * 3,
                    );
                    ink.stroke();
                }
                ink.fillStyle = '#21180e';
                ink.fillRect(0, y, 1024, 1.4);
                ink.fillStyle = '#d5a66d36';
                ink.fillRect(0, y + 2, 1024, 1);
            }
            for (const [x, y] of [
                [170, 170],
                [710, 520],
                [370, 825],
                [895, 295],
            ]) {
                for (let ring = 1; ring < 13; ring++) {
                    ink.strokeStyle = ring % 3 ? '#211b1345' : '#bc946425';
                    ink.lineWidth = ring % 3 ? 1 : 1.5;
                    ink.beginPath();
                    ink.ellipse(
                        x,
                        y,
                        4 + ring * 4.6,
                        1 + ring * 0.95,
                        -0.025,
                        0,
                        Math.PI * 2,
                    );
                    ink.stroke();
                }
            }
            for (const [x, y] of [
                [215, 342],
                [735, 684],
                [430, 114],
                [845, 456],
            ]) {
                ink.fillStyle = '#332719';
                ink.beginPath();
                ink.moveTo(x - 9, y - 12);
                ink.lineTo(x + 9, y - 12);
                ink.lineTo(x + 4, y);
                ink.lineTo(x + 9, y + 12);
                ink.lineTo(x - 9, y + 12);
                ink.lineTo(x - 4, y);
                ink.closePath();
                ink.fill();
            }
        });
        textile.map = canvasTexture(1024, 256, (ink) => {
            ink.fillStyle = '#743a2e';
            ink.fillRect(0, 0, 1024, 256);
            for (let y = 0; y < 256; y += 3) {
                ink.fillStyle = y % 2 ? '#e1b37b16' : '#30191133';
                ink.fillRect(0, y, 1024, 1);
            }
            for (let x = 0; x < 1024; x += 3) {
                ink.fillStyle = x % 2 ? '#e1b37b13' : '#30191124';
                ink.fillRect(x, 0, 1, 256);
            }
            for (const y of [14, 31, 224, 241]) {
                ink.fillStyle = '#bd965e';
                ink.fillRect(0, y, 1024, y === 14 || y === 241 ? 3 : 1);
            }
            ink.strokeStyle = '#d5b173';
            ink.lineWidth = 1.5;
            for (let x = 4; x < 1024; x += 13) {
                for (const y of [23, 233]) {
                    ink.beginPath();
                    ink.moveTo(x, y - 2);
                    ink.lineTo(x + 5, y + 2);
                    ink.stroke();
                }
            }
            for (let x = 55; x < 1024; x += 76) {
                for (const y of [49, 207]) {
                    ink.fillStyle = '#bd965e';
                    ink.beginPath();
                    ink.moveTo(x, y - 5);
                    ink.lineTo(x + 4, y);
                    ink.lineTo(x, y + 5);
                    ink.lineTo(x - 4, y);
                    ink.closePath();
                    ink.fill();
                }
            }
        });

        function outline(scale = 1, scallop = 0) {
            const points: THREE.Vector2[] = [];
            for (let i = 0; i < 160; i++) {
                const a = (i / 160) * Math.PI * 2;
                const c = Math.cos(a),
                    s = Math.sin(a);
                const detail = 1 + scallop * Math.cos(a * 20);
                points.push(
                    new THREE.Vector2(
                        Math.sign(c) *
                            Math.abs(c) ** 0.84 *
                            5.02 *
                            scale *
                            detail,
                        Math.sign(s) *
                            Math.abs(s) ** 0.84 *
                            3.51 *
                            scale *
                            detail,
                    ),
                );
            }
            return points;
        }
        function slab(scale: number, thickness: number, scallop = 0) {
            const shape = new THREE.Shape(outline(scale, scallop));
            const value = geometry(
                new THREE.ExtrudeGeometry(shape, {
                    depth: thickness,
                    bevelEnabled: true,
                    bevelSegments: 2,
                    steps: 1,
                    bevelSize: 0.035,
                    bevelThickness: 0.025,
                    curveSegments: 1,
                }),
            );
            value.rotateX(-Math.PI / 2);
            return value;
        }
        part(slab(0.965, 0.36, 0.006), carved, 0, -0.56);
        part(slab(0.993, 0.065, 0.003), copper, 0, -0.19);
        part(slab(1, 0.145), walnut, 0, -0.115);
        const tabletop = geometry(
            new THREE.ShapeGeometry(new THREE.Shape(outline(0.983)), 1),
        );
        // Extruded sides and the tabletop have independent UVs so grain stays fine.
        const vertices = tabletop.getAttribute('position');
        const uv = tabletop.getAttribute('uv');
        for (let i = 0; i < vertices.count; i++) {
            uv.setXY(
                i,
                (vertices.getX(i) + 5.02) / 10.04,
                (vertices.getY(i) + 3.51) / 7.02,
            );
        }
        tabletop.rotateX(-Math.PI / 2);
        part(tabletop, timber, 0, 0.083);

        const box = geometry(new THREE.BoxGeometry(1, 1, 1));
        const sphere = geometry(new THREE.SphereGeometry(1, 12, 8));
        const cylinder = geometry(new THREE.CylinderGeometry(1, 1, 1, 12));
        const tapered = geometry(new THREE.CylinderGeometry(0.74, 1, 1, 8));
        const ring = geometry(new THREE.TorusGeometry(1, 0.055, 6, 32));
        // Repeated copper rivets and carved flutes make the apron a physical object.
        for (let i = 0; i < 40; i++) {
            const a = (i / 40) * Math.PI * 2;
            const c = Math.cos(a),
                s = Math.sin(a);
            const x = Math.sign(c) * Math.abs(c) ** 0.84 * 4.89;
            const z = Math.sign(s) * Math.abs(s) ** 0.84 * 3.42;
            part(sphere, copper, x, -0.245, z, 0.034, 0.034, 0.034);
            part(
                box,
                walnut,
                x * 0.987,
                -0.4,
                z * 0.987,
                0.065,
                0.2,
                0.065,
                0,
                -a,
            );
        }
        for (const x of [-3.3, 3.3]) {
            for (const z of [-1.8, 1.8]) {
                part(tapered, carved, x, -0.72, z, 0.24, 0.58, 0.24);
                part(cylinder, copper, x, -0.76, z, 0.185, 0.075, 0.185);
                part(sphere, walnut, x, -0.91, z, 0.29, 0.13, 0.29);
            }
        }
        // One continuous runner, gently hanging over both ends of the table.
        const runnerVertices: number[] = [],
            runnerUvs: number[] = [],
            runnerIndices: number[] = [];
        const sections = [
            [-5.09, -0.59],
            [-5.08, -0.14],
            [-4.94, 0.11],
            [-4.5, 0.113],
            [0, 0.113],
            [4.5, 0.113],
            [4.94, 0.11],
            [5.08, -0.14],
            [5.09, -0.59],
        ];
        sections.forEach(([x, y], i) => {
            runnerVertices.push(x, y, -0.76, x, y, 0.76);
            runnerUvs.push(
                i / (sections.length - 1),
                0,
                i / (sections.length - 1),
                1,
            );
            if (i) {
                const n = i * 2;
                runnerIndices.push(n - 2, n - 1, n, n - 1, n + 1, n);
            }
        });
        const runner = geometry(new THREE.BufferGeometry());
        runner.setAttribute(
            'position',
            new THREE.Float32BufferAttribute(runnerVertices, 3),
        );
        runner.setAttribute(
            'uv',
            new THREE.Float32BufferAttribute(runnerUvs, 2),
        );
        runner.setIndex(runnerIndices);
        runner.computeVertexNormals();
        part(runner, textile);
        for (const x of [-5.095, 5.095]) {
            for (let i = 0; i < 19; i++) {
                part(
                    box,
                    cream,
                    x,
                    -0.655,
                    -0.7 + i * 0.078,
                    0.018,
                    0.12,
                    0.018,
                    0.12 * Math.sin(i),
                );
            }
        }

        // Ribbed fruit is a single deformed sphere, not a pile of separate lobes.
        const pumpkin = geometry(new THREE.SphereGeometry(1, 40, 20));
        const pumpkinVertices = pumpkin.getAttribute('position');
        for (let i = 0; i < pumpkinVertices.count; i++) {
            const x = pumpkinVertices.getX(i),
                y = pumpkinVertices.getY(i),
                z = pumpkinVertices.getZ(i);
            const a = Math.atan2(z, x);
            const rib = 0.91 + 0.09 * Math.cos(a * 10);
            pumpkinVertices.setXYZ(
                i,
                x * rib,
                y * (0.83 - Math.abs(y) * 0.13),
                z * rib,
            );
        }
        pumpkin.computeVertexNormals();
        function fruit(
            x: number,
            z: number,
            size: number,
            finish: THREE.Material,
            tall = 1,
        ) {
            const y = 0.1 + size * 0.7 * tall;
            part(pumpkin, finish, x, y, z, size, size * tall, size, 0, x);
            part(
                tapered,
                stem,
                x + size * 0.03,
                y + size * 0.76 * tall,
                z,
                size * 0.09,
                size * 0.33,
                size * 0.09,
                0,
                0,
                -0.28,
            );
        }
        fruit(-3.74, 1.35, 0.49, orange);
        fruit(-4.2, 1.02, 0.27, cream, 1.15);
        fruit(-3.48, 1.81, 0.25, sage, 0.8);
        fruit(3.74, -1.35, 0.44, ochre, 1.13);
        fruit(4.2, -1.02, 0.27, orange);
        fruit(3.48, -1.81, 0.23, cream);

        function lantern(x: number, z: number) {
            const y = 0.11;
            part(box, iron, x, y + 0.055, z, 0.46, 0.11, 0.42);
            part(box, copper, x, y + 0.13, z, 0.4, 0.045, 0.36);
            part(cylinder, cream, x, y + 0.31, z, 0.1, 0.3, 0.1);
            part(sphere, ember, x, y + 0.53, z, 0.06, 0.13, 0.06);
            for (const dx of [-0.17, 0.17]) {
                for (const dz of [-0.15, 0.15]) {
                    part(
                        box,
                        copper,
                        x + dx,
                        y + 0.4,
                        z + dz,
                        0.026,
                        0.56,
                        0.026,
                    );
                }
            }
            part(box, copper, x, y + 0.68, z, 0.44, 0.055, 0.4);
            part(
                tapered,
                iron,
                x,
                y + 0.76,
                z,
                0.25,
                0.12,
                0.23,
                0,
                Math.PI / 4,
            );
            part(ring, copper, x, y + 0.92, z, 0.1, 0.13, 0.1);
            // Warm pools are surfaces, so the two lanterns add no shadow/light passes.
            const pool = new THREE.MeshBasicMaterial({
                map: glowTexture,
                transparent: true,
                opacity: 0.24,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            });
            materialPool.add(pool);
            part(
                geometry(new THREE.PlaneGeometry(1.8, 1.8)),
                pool,
                x,
                0.12,
                z,
                1,
                1,
                1,
                -Math.PI / 2,
            );
        }
        const glowTexture = canvasTexture(64, 64, (ink) => {
            const glow = ink.createRadialGradient(32, 32, 0, 32, 32, 32);
            glow.addColorStop(0, '#ffb657');
            glow.addColorStop(0.35, '#d56e2855');
            glow.addColorStop(1, '#a4410000');
            ink.fillStyle = glow;
            ink.fillRect(0, 0, 64, 64);
        });
        lantern(-3.9, -1.5);
        lantern(3.9, 1.5);

        // Low wheat and leaf sprigs nestle beside the fruit, away from the seal.
        for (const side of [-1, 1]) {
            for (let i = 0; i < 7; i++) {
                const angle = side * (0.4 + i * 0.15);
                const x = side * (3.04 + i * 0.055),
                    z = -side * (0.21 + i * 0.035);
                part(box, wheat, x, 0.16, z, 0.016, 0.014, 0.72, 0, angle);
                for (let j = 0; j < 4; j++) {
                    const distance = 0.18 + j * 0.07;
                    for (const branch of [-1, 1]) {
                        part(
                            sphere,
                            wheat,
                            x + Math.sin(angle) * distance + branch * 0.035,
                            0.175,
                            z + Math.cos(angle) * distance,
                            0.024,
                            0.016,
                            0.06,
                            0,
                            angle + branch * 0.55,
                        );
                    }
                }
            }
            for (let i = 0; i < 8; i++) {
                const x = side * (2.8 + (i % 4) * 0.24);
                const z = side * (0.7 + Math.sin(i * 2.1) * 0.55);
                part(
                    sphere,
                    i % 3 === 0 ? sage : i % 2 ? orange : ochre,
                    x,
                    0.14,
                    z,
                    0.09,
                    0.018,
                    0.2,
                    0,
                    i * 2.1,
                );
            }
        }
        for (const { geometry, material, matrices } of batches.values()) {
            const mesh = new THREE.InstancedMesh(
                geometry,
                material,
                matrices.length,
            );
            matrices.forEach((matrix, i) => mesh.setMatrixAt(i, matrix));
            mesh.instanceMatrix.needsUpdate = true;
            mesh.computeBoundingSphere();
            root.add(mesh);
        }
        scene.add(root);
        return {
            root,
            update(darkness: number) {
                ember.emissiveIntensity = 1.8 + darkness * 0.8;
            },
            dispose,
        };
    } catch (error) {
        dispose();
        throw error;
    }
}
