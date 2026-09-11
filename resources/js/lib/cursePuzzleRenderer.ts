import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import type { CurseChallenge } from './chanting';
import { ringTurn } from './cursePuzzleState';

export interface CursePuzzleRenderer {
    update: (answer: string[], disabled: boolean) => void;
    resetView: () => void;
    dispose: () => void;
}

/** Presentation only: answers remain in Vue and are checked by the server. */
export function createCursePuzzle(
    host: HTMLElement,
    challenge: CurseChallenge,
    initialAnswer: string[],
    initialDisabled: boolean,
    choose: (id: string | number) => void,
    failed: () => void,
): CursePuzzleRenderer {
    const spec = challenge.scene;
    if (!spec) throw new Error('Missing puzzle scene');
    const fogDensity =
        spec.difficulty === 1 ? 0.015 : spec.difficulty === 2 ? 0.03 : 0.05;
    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    const renderer = new THREE.WebGLRenderer({
        canvas,
        antialias: true,
        alpha: true,
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
    renderer.setClearColor(0x101c22, 1);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    const scene = new THREE.Scene();
    const camera = new THREE.OrthographicCamera(-6, 6, 5, -5, 0.1, 60);
    const tallest = Math.max(
        0,
        ...(spec.objects ?? []).map((object) => object.height + 1.2),
    );
    const centerY = spec.kind === 'rings' ? 0.5 : tallest / 2;
    const plinthRadius =
        spec.kind === 'rings'
            ? 5
            : Math.max(
                  3.2,
                  ...(spec.objects ?? []).map(
                      (object) => Math.hypot(object.x, object.z) + 0.8,
                  ),
              );
    camera.position.set(
        0,
        (spec.kind === 'rings' ? 13 : 10) + centerY,
        spec.kind === 'rings' ? 7 : 12,
    );
    camera.lookAt(0, centerY, 0);
    const controls = new OrbitControls(camera, canvas);
    controls.target.set(0, centerY, 0);
    controls.enablePan = false;
    controls.enableZoom = false;
    controls.enableRotate = spec.kind !== 'rings';
    controls.minPolarAngle = Math.PI / 8;
    controls.maxPolarAngle = Math.PI / 2.8;
    controls.rotateSpeed = 0.65;
    controls.update();
    controls.saveState();
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    const textures = new Set<THREE.Texture>();
    const targets: THREE.Object3D[] = [];
    const rings: THREE.Group[] = [];
    const lights = new Map<string, THREE.MeshStandardMaterial>();
    const badges = new Map<string, THREE.Sprite>();
    const labels: {
        sprite: THREE.Sprite;
        width: number;
        height: number;
        anchor?: THREE.Vector3;
        leader?: THREE.Line;
    }[] = [];
    // Include the plinth and the complete silhouette of every object, including
    // billboard labels. Fit again when orbiting so tall lanterns stay in view.
    const framingPoints: THREE.Vector3[] = [];
    for (let index = 0; index < 32; index++) {
        const angle = (index * Math.PI) / 16;
        framingPoints.push(
            new THREE.Vector3(
                Math.sin(angle) * (plinthRadius + 0.2),
                -0.5,
                Math.cos(angle) * (plinthRadius + 0.2),
            ),
        );
    }
    for (const object of spec.objects ?? []) {
        framingPoints.push(new THREE.Vector3(object.x, 0, object.z));
        framingPoints.push(
            new THREE.Vector3(object.x, object.height + 0.93, object.z),
        );
    }
    if (spec.kind === 'rings')
        framingPoints.push(new THREE.Vector3(0, 0.9, -4.5));
    let disabled = initialDisabled;
    let disposed = false;
    let frame = 0;
    let resize: ResizeObserver | undefined;

    function draw() {
        if (disposed || frame || document.hidden) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            if (!disposed) renderer.render(scene, camera);
        });
    }
    function material(color: number, glow = 0): THREE.MeshStandardMaterial {
        const value = new THREE.MeshStandardMaterial({
            color,
            roughness: 0.65,
            metalness: 0.35,
            emissive: glow,
        });
        materials.add(value);
        return value;
    }
    function mesh(
        geometry: THREE.BufferGeometry,
        surface: THREE.Material,
        parent: THREE.Object3D = scene,
    ) {
        geometries.add(geometry);
        const object = new THREE.Mesh(geometry, surface);
        parent.add(object);
        return object;
    }
    function textSprite(
        label: string,
        fontSize = 14,
        avoidOverlap = false,
    ): THREE.Sprite {
        const art = document.createElement('canvas');
        const context = art.getContext('2d');
        if (!context) throw new Error('Puzzle labels unavailable');
        const density = 3;
        context.font = `600 ${fontSize * density}px Georgia, serif`;
        const pixelWidth =
            Math.ceil(context.measureText(label).width / density) + 10;
        const pixelHeight = fontSize + 8;
        art.width = pixelWidth * density;
        art.height = pixelHeight * density;
        context.fillStyle = '#142128';
        context.fillRect(0, 0, art.width, art.height);
        context.strokeStyle = '#8a758f';
        context.lineWidth = density;
        context.strokeRect(1.5, 1.5, art.width - 3, art.height - 3);
        context.fillStyle = '#fff0d1';
        context.font = `600 ${fontSize * density}px Georgia, serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(label, art.width / 2, art.height / 2);
        const texture = new THREE.CanvasTexture(art);
        texture.colorSpace = THREE.SRGBColorSpace;
        textures.add(texture);
        const surface = new THREE.SpriteMaterial({
            map: texture,
            depthTest: false,
        });
        materials.add(surface);
        const sprite = new THREE.Sprite(surface);
        sprite.renderOrder = 3;
        scene.add(sprite);
        let leader: THREE.Line | undefined;
        if (avoidOverlap) {
            const geometry = new THREE.BufferGeometry().setFromPoints([
                new THREE.Vector3(),
                new THREE.Vector3(),
            ]);
            geometries.add(geometry);
            const lineMaterial = new THREE.LineBasicMaterial({
                color: 0xc4acc9,
                transparent: true,
                opacity: 0.8,
                depthTest: false,
            });
            materials.add(lineMaterial);
            leader = new THREE.Line(geometry, lineMaterial);
            leader.renderOrder = 2;
            scene.add(leader);
        }
        labels.push({ sprite, width: pixelWidth, height: pixelHeight, leader });
        return sprite;
    }
    function target(object: THREE.Object3D, id: string | number) {
        object.userData.choice = id;
        targets.push(object);
    }
    function update(answer: string[], nextDisabled: boolean) {
        disabled = nextDisabled;
        rings.forEach((ring, index) => {
            const state = spec?.rings?.[index];
            if (state)
                ring.rotation.y =
                    (-(state.start + ringTurn(state, answer[index])) *
                        Math.PI) /
                    2;
        });
        lights.forEach((surface, id) => {
            const selected = answer.includes(id);
            surface.color.setHex(
                selected
                    ? 0x90e1ca
                    : spec?.kind === 'lanterns'
                      ? 0xd9b86e
                      : 0x8c749b,
            );
            surface.emissive.setHex(selected ? 0x318b74 : 0x33283d);
            surface.emissiveIntensity = selected ? 1.4 : 0.5;
        });
        badges.forEach((badge, id) => {
            badge.visible = answer.includes(id);
        });
        if (scene.fog instanceof THREE.FogExp2) {
            scene.fog.density =
                fogDensity *
                (1 -
                    Math.min(answer.length / challenge.answer_length, 1) * 0.7);
        }
        draw();
    }
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let down: { x: number; y: number; pointer: number } | undefined;
    function pointerDown(event: PointerEvent) {
        if (event.button === 0 && event.isPrimary)
            down = {
                x: event.clientX,
                y: event.clientY,
                pointer: event.pointerId,
            };
    }
    function pointerUp(event: PointerEvent) {
        const start = down;
        down = undefined;
        if (
            disabled ||
            !start ||
            start.pointer !== event.pointerId ||
            Math.hypot(event.clientX - start.x, event.clientY - start.y) > 7
        )
            return;
        const rect = canvas.getBoundingClientRect();
        pointer.set(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            (-(event.clientY - rect.top) / rect.height) * 2 + 1,
        );
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(targets, false)[0];
        if (hit) choose(hit.object.userData.choice as string | number);
    }
    function cancelPointer() {
        down = undefined;
    }
    function contextLost(event: Event) {
        event.preventDefault();
        failed();
    }
    function fit() {
        const width = Math.max(host.clientWidth, 1);
        const height = Math.max(host.clientHeight, 1);
        const ratio = width / height;
        camera.updateMatrixWorld();
        let minX = Infinity;
        let maxX = -Infinity;
        let minY = Infinity;
        let maxY = -Infinity;
        for (const point of framingPoints) {
            const projected = point
                .clone()
                .applyMatrix4(camera.matrixWorldInverse);
            minX = Math.min(minX, projected.x);
            maxX = Math.max(maxX, projected.x);
            minY = Math.min(minY, projected.y);
            maxY = Math.max(maxY, projected.y);
        }
        const halfWidth = Math.max(
            (maxX - minX) / 2 + 0.5,
            ((maxY - minY) / 2 + 0.5) * ratio,
        );
        const centerX = (minX + maxX) / 2;
        const centerY = (minY + maxY) / 2;
        camera.left = centerX - halfWidth;
        camera.right = centerX + halfWidth;
        camera.top = centerY + halfWidth / ratio;
        camera.bottom = centerY - halfWidth / ratio;
        camera.updateProjectionMatrix();
        const unit = (camera.right - camera.left) / width;
        const right = new THREE.Vector3().setFromMatrixColumn(
            camera.matrixWorld,
            0,
        );
        const up = new THREE.Vector3().setFromMatrixColumn(
            camera.matrixWorld,
            1,
        );
        const occupied: {
            x: number;
            y: number;
            width: number;
            height: number;
        }[] = [];
        for (const label of labels) {
            label.anchor ??= label.sprite.position.clone();
            label.sprite.position.copy(label.anchor);
            label.sprite.scale.set(label.width * unit, label.height * unit, 1);
            if (!label.leader) continue;
            const projected = label.anchor.clone().project(camera);
            const originX = ((projected.x + 1) * width) / 2;
            const originY = ((1 - projected.y) * height) / 2;
            let x = originX;
            let y = originY;
            for (let attempt = 0; attempt < 30; attempt++) {
                const offset =
                    Math.ceil(attempt / 2) *
                    (label.height + 3) *
                    (attempt % 2 ? -1 : 1);
                y = Math.max(
                    label.height / 2 + 3,
                    Math.min(height - label.height / 2 - 3, originY + offset),
                );
                x = Math.max(
                    label.width / 2 + 3,
                    Math.min(width - label.width / 2 - 3, originX),
                );
                if (
                    !occupied.some(
                        (other) =>
                            Math.abs(x - other.x) <
                                (label.width + other.width) / 2 + 3 &&
                            Math.abs(y - other.y) <
                                (label.height + other.height) / 2 + 3,
                    )
                )
                    break;
            }
            occupied.push({ x, y, width: label.width, height: label.height });
            label.sprite.position
                .addScaledVector(right, (x - originX) * unit)
                .addScaledVector(up, (originY - y) * unit);
            label.leader.geometry.setFromPoints([
                label.anchor,
                label.sprite.position,
            ]);
            label.leader.visible = Math.hypot(x - originX, y - originY) > 3;
        }
        renderer.setSize(width, height, false);
        draw();
    }
    function dispose() {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        resize?.disconnect();
        document.removeEventListener('visibilitychange', draw);
        controls.removeEventListener('change', fit);
        controls.dispose();
        canvas.removeEventListener('pointerdown', pointerDown);
        canvas.removeEventListener('pointerup', pointerUp);
        canvas.removeEventListener('pointercancel', cancelPointer);
        canvas.removeEventListener('pointerleave', cancelPointer);
        canvas.removeEventListener('webglcontextlost', contextLost);
        geometries.forEach((value) => value.dispose());
        materials.forEach((value) => value.dispose());
        textures.forEach((value) => value.dispose());
        renderer.dispose();
        renderer.forceContextLoss();
        canvas.remove();
    }
    try {
        scene.add(new THREE.HemisphereLight(0xc4e5e5, 0x40334d, 2.6));
        const key = new THREE.DirectionalLight(0xf3dfaa, 3);
        key.position.set(-4, 10, 6);
        scene.add(key);
        const fill = new THREE.PointLight(0x60c4b2, 24, 20);
        fill.position.set(2, 4, -4);
        scene.add(fill);
        const stone = material(0x34424a);
        const edge = material(0x695576);
        const gold = material(0xdbba78, 0x6a4317);
        const plinth = mesh(
            new THREE.CylinderGeometry(
                plinthRadius,
                plinthRadius + 0.15,
                0.42,
                64,
            ),
            stone,
        );
        plinth.position.y = -0.28;
        const rim = mesh(
            new THREE.TorusGeometry(plinthRadius - 0.06, 0.045, 8, 96),
            gold,
        );
        rim.rotation.x = Math.PI / 2;
        rim.position.y = -0.035;
        for (let index = 0; index < 24; index++) {
            const notch = mesh(
                new THREE.BoxGeometry(0.04, 0.035, index % 3 ? 0.12 : 0.24),
                edge,
            );
            const angle = (index * Math.PI) / 12;
            notch.position.set(
                Math.sin(angle) * (plinthRadius - 0.3),
                0.01,
                Math.cos(angle) * (plinthRadius - 0.3),
            );
            notch.rotation.y = angle;
        }
        if (spec.kind === 'rings') {
            const north = textSprite('NORTH', 12);
            north.position.set(0, 0.9, -4.5);
            const beam = mesh(
                new THREE.BoxGeometry(0.065, 0.04, 4.1),
                material(0x73cdbb, 0x306f64),
            );
            beam.position.set(0, 0.065, -2.15);
            const list = spec.rings ?? [];
            list.forEach((ring, index) => {
                const radius =
                    0.8 + (index * 3.05) / Math.max(list.length - 1, 1);
                const group = new THREE.Group();
                scene.add(group);
                rings.push(group);
                const surface = material(index % 2 ? 0x8a7298 : 0x675875);
                const band = mesh(
                    new THREE.TorusGeometry(radius, 0.17, 12, 96),
                    surface,
                    group,
                );
                band.rotation.x = Math.PI / 2;
                band.position.y = 0.2;
                target(band, index);
                const notch = mesh(
                    new THREE.BoxGeometry(0.21, 0.17, 0.45),
                    gold,
                    group,
                );
                notch.position.set(0, 0.36, -radius);
                target(notch, index);
                const label = textSprite(String(index + 1), 12);
                const labelAngle = index % 2 ? Math.PI / 5 : -Math.PI / 5;
                label.position.set(
                    radius * Math.cos(labelAngle),
                    0.45,
                    radius * Math.sin(labelAngle),
                );
                target(label, index);
            });
            const seal = mesh(new THREE.OctahedronGeometry(0.28), gold);
            seal.position.y = 0.38;
        } else {
            if (spec.kind === 'lanterns')
                scene.fog = new THREE.FogExp2(0x87929e, fogDensity);
            (spec.objects ?? []).forEach((object) => {
                const option = challenge.options.find(
                    (item) => item.id === object.option_id,
                );
                const surface = material(
                    spec.kind === 'towers' ? 0x8c749b : 0xd9b86e,
                    0x33283d,
                );
                lights.set(object.option_id, surface);
                const height = object.height;
                const body = mesh(
                    new THREE.CylinderGeometry(
                        spec.kind === 'towers' ? 0.34 : 0.25,
                        0.4,
                        height,
                        spec.kind === 'towers' ? 6 : 8,
                    ),
                    spec.kind === 'towers' ? stone : edge,
                );
                body.position.set(object.x, height / 2, object.z);
                target(body, object.option_id);
                const beacon = mesh(
                    new THREE.CylinderGeometry(
                        0.38,
                        0.38,
                        spec.kind === 'towers' ? 0.16 : 0.48,
                        8,
                    ),
                    surface,
                );
                beacon.position.set(object.x, height + 0.12, object.z);
                target(beacon, object.option_id);
                const roof = mesh(new THREE.ConeGeometry(0.48, 0.25, 8), gold);
                roof.position.set(
                    object.x,
                    height + (spec.kind === 'towers' ? 0.32 : 0.49),
                    object.z,
                );
                target(roof, object.option_id);
                const label = textSprite(option?.label ?? '?', 14, true);
                label.position.set(object.x, height + 0.93, object.z);
                target(label, object.option_id);
                const lit = textSprite('LIT', 10);
                lit.position.set(object.x, 0.17, object.z + 0.55);
                badges.set(object.option_id, lit);
            });
        }
        controls.addEventListener('change', fit);
        canvas.addEventListener('pointerdown', pointerDown);
        canvas.addEventListener('pointerup', pointerUp);
        canvas.addEventListener('pointercancel', cancelPointer);
        canvas.addEventListener('pointerleave', cancelPointer);
        canvas.addEventListener('webglcontextlost', contextLost);
        document.addEventListener('visibilitychange', draw);
        host.append(canvas);
        resize = new ResizeObserver(fit);
        resize.observe(host);
        fit();
        update(initialAnswer, initialDisabled);
        return {
            update,
            resetView: () => {
                controls.reset();
                draw();
            },
            dispose,
        };
    } catch (error) {
        dispose();
        throw error;
    }
}
