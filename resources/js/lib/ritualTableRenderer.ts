import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import {
    placeTableChatBubble,
    wrapTableChat,
    type TableChatBubble,
    type TableChatRect,
} from './tableChat';
import type {
    RitualSceneSeat,
    RitualSceneState,
    RitualTableDisplay,
} from './ritualSceneState';

export interface RitualTableRenderer {
    update: (state: RitualSceneState, seats: RitualSceneSeat[]) => void;
    updateDisplay: (display?: RitualTableDisplay) => void;
    updateBubbles: (bubbles: TableChatBubble[]) => void;
    dispose: () => void;
    resetView: () => void;
}

/** Loaded only after the viewer chooses 3D. Nothing here owns game state. */
export function createRitualTable(
    host: HTMLElement,
    initial: RitualSceneState,
    initialSeats: RitualSceneSeat[],
    failed: () => void,
    interactive = false,
    projectSeats?: (positions: { left: string; top: string }[]) => void,
    initialDisplay?: RitualTableDisplay,
): RitualTableRenderer {
    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    const context = canvas.getContext('webgl2', {
        alpha: true,
        antialias: true,
    });
    if (!context) throw new Error('Table visuals unavailable');
    const renderer = new THREE.WebGLRenderer({
        canvas,
        context,
        alpha: true,
        antialias: true,
    });
    const scene = new THREE.Scene();
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    const textures = new Set<THREE.Texture>();
    let disposed = false;
    let frame = 0;
    let visible = true;
    let lastFrame = 0;
    let clock = 0;
    let state = initial;
    let seats = initialSeats;
    let display = initialDisplay;
    let projectionKey = '';
    let darkness = initial.night ? 1 : 0;
    let emergence = initial.emergence;
    let schedule = () => {};
    let changeMotion = () => {};
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let resizeObserver: ResizeObserver | undefined;
    let intersectionObserver: IntersectionObserver | undefined;
    const camera = new THREE.OrthographicCamera(-6, 6, 5, -5, 0.1, 60);
    camera.position.set(0, 10, 10);
    camera.lookAt(0, 0, 0);
    const controls = new OrbitControls(camera, canvas);
    controls.enabled = interactive;
    controls.enablePan = false;
    controls.enableRotate = interactive;
    controls.minPolarAngle = Math.PI / 9;
    controls.maxPolarAngle = Math.PI / 3;
    controls.minZoom = 0.75;
    controls.maxZoom = 1.65;
    controls.rotateSpeed = 0.65;
    controls.zoomSpeed = 0.7;
    controls.saveState();
    const cameraChanged = () => schedule();
    controls.addEventListener('change', cameraChanged);
    function keydown(event: KeyboardEvent) {
        if (!interactive || event.altKey || event.ctrlKey || event.metaKey)
            return;
        const offset = camera.position.clone().sub(controls.target);
        const spherical = new THREE.Spherical().setFromVector3(offset);
        switch (event.key) {
            case 'ArrowLeft':
                spherical.theta -= 0.12;
                break;
            case 'ArrowRight':
                spherical.theta += 0.12;
                break;
            case 'ArrowUp':
                spherical.phi -= 0.09;
                break;
            case 'ArrowDown':
                spherical.phi += 0.09;
                break;
            case '+':
            case '=':
                camera.zoom = Math.min(controls.maxZoom, camera.zoom * 1.1);
                break;
            case '-':
            case '_':
                camera.zoom = Math.max(controls.minZoom, camera.zoom / 1.1);
                break;
            case 'Home':
                event.preventDefault();
                controls.reset();
                return;
            default:
                return;
        }
        event.preventDefault();
        spherical.phi = THREE.MathUtils.clamp(
            spherical.phi,
            controls.minPolarAngle,
            controls.maxPolarAngle,
        );
        camera.position
            .copy(controls.target)
            .add(new THREE.Vector3().setFromSpherical(spherical));
        camera.updateProjectionMatrix();
        controls.update();
        schedule();
    }
    if (interactive) {
        canvas.removeAttribute('aria-hidden');
        canvas.tabIndex = 0;
        canvas.setAttribute('role', 'group');
        canvas.setAttribute(
            'aria-label',
            'Table camera. Drag to orbit, scroll to zoom. Arrow keys orbit, plus and minus zoom, Home resets.',
        );
        canvas.addEventListener('keydown', keydown);
    }
    function mesh<G extends THREE.BufferGeometry, M extends THREE.Material>(
        geometry: G,
        material: M,
        parent: THREE.Object3D = scene,
    ) {
        geometries.add(geometry);
        materials.add(material);
        const object = new THREE.Mesh(geometry, material);
        parent.add(object);
        return object;
    }
    function standard(color: number, metalness = 0, roughness = 0.7) {
        const material = new THREE.MeshStandardMaterial({
            color,
            metalness,
            roughness,
        });
        materials.add(material);
        return material;
    }
    function dispose() {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        resizeObserver?.disconnect();
        intersectionObserver?.disconnect();
        document.removeEventListener('visibilitychange', schedule);
        motion.removeEventListener('change', changeMotion);
        canvas.removeEventListener('webglcontextlost', contextLost);
        canvas.removeEventListener('keydown', keydown);
        controls.removeEventListener('change', cameraChanged);
        controls.dispose();
        geometries.forEach((geometry) => geometry.dispose());
        materials.forEach((material) => material.dispose());
        textures.forEach((texture) => texture.dispose());
        renderer.dispose();
        renderer.forceContextLoss();
        canvas.remove();
    }
    function contextLost(event: Event) {
        event.preventDefault();
        dispose();
        failed();
    }

    try {
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        renderer.setClearColor(0x000000, 0);
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.35;
        canvas.addEventListener('webglcontextlost', contextLost);
        host.append(canvas);

        const speech = new Map<
            string,
            {
                bubble: TableChatBubble;
                sprite: THREE.Sprite;
                texture: THREE.CanvasTexture;
                ink: CanvasRenderingContext2D;
                lines: string[];
                lineLimit: number;
                leader: THREE.Line<
                    THREE.BufferGeometry,
                    THREE.LineBasicMaterial
                >;
            }
        >();
        function removeSpeech(id: string) {
            const entry = speech.get(id);
            if (!entry) return;
            scene.remove(entry.sprite, entry.leader);
            entry.leader.geometry.dispose();
            entry.leader.material.dispose();
            geometries.delete(entry.leader.geometry);
            materials.delete(entry.leader.material);
            entry.sprite.material.dispose();
            entry.texture.dispose();
            materials.delete(entry.sprite.material);
            textures.delete(entry.texture);
            speech.delete(id);
        }
        function paintSpeech(
            entry: typeof speech extends Map<string, infer T> ? T : never,
        ) {
            const { ink, lines, bubble } = entry;
            const width = ink.canvas.width;
            const height = ink.canvas.height;
            ink.clearRect(0, 0, width, height);
            ink.fillStyle = '#eee3c8';
            ink.strokeStyle = '#bda46b';
            ink.lineWidth = 3;
            ink.beginPath();
            ink.roundRect(3, 3, width - 6, height - 6, 22);
            ink.fill();
            ink.stroke();
            ink.fillStyle = '#435e56';
            ink.font = 'bold 28px Georgia, serif';
            ink.textBaseline = 'top';
            ink.fillText(
                wrapTableChat(
                    bubble.name,
                    (text) => ink.measureText(text).width,
                    450,
                    1,
                )[0] ?? '',
                28,
                21,
            );
            ink.fillStyle = '#243c36';
            ink.font = '34px Georgia, serif';
            lines.forEach((line, index) =>
                ink.fillText(line, 28, 64 + index * 43),
            );
            entry.texture.needsUpdate = true;
        }
        function updateBubbles(next: TableChatBubble[]) {
            if (disposed) return;
            for (const [id] of speech) {
                if (!next.some((bubble) => bubble.id === id)) removeSpeech(id);
            }
            for (const bubble of next) {
                if (speech.has(bubble.id) || bubble.expiresAt <= Date.now())
                    continue;
                const paper = document.createElement('canvas');
                paper.width = 512;
                const ink = paper.getContext('2d');
                if (!ink) continue;
                ink.font = '34px Georgia, serif';
                const lines = wrapTableChat(
                    bubble.body,
                    (text) => ink.measureText(text).width,
                    456,
                    3,
                );
                paper.height = 80 + lines.length * 43;
                const texture = new THREE.CanvasTexture(paper);
                texture.colorSpace = THREE.SRGBColorSpace;
                const material = new THREE.SpriteMaterial({
                    map: texture,
                    depthTest: false,
                    depthWrite: false,
                    toneMapped: false,
                });
                materials.add(material);
                textures.add(texture);
                const sprite = new THREE.Sprite(material);
                sprite.renderOrder = 20;
                sprite.center.set(0.5, 0);
                const leaderGeometry = new THREE.BufferGeometry();
                leaderGeometry.setAttribute(
                    'position',
                    new THREE.Float32BufferAttribute(new Float32Array(6), 3),
                );
                const leaderMaterial = new THREE.LineBasicMaterial({
                    color: 0xd5bd85,
                    depthTest: false,
                    depthWrite: false,
                    toneMapped: false,
                    transparent: true,
                    opacity: 0.95,
                });
                const leader = new THREE.Line(leaderGeometry, leaderMaterial);
                leader.frustumCulled = false;
                leader.renderOrder = 19;
                geometries.add(leaderGeometry);
                materials.add(leaderMaterial);
                scene.add(sprite, leader);
                const entry = {
                    bubble,
                    sprite,
                    texture,
                    ink,
                    lines,
                    lineLimit: 3,
                    leader,
                };
                speech.set(bubble.id, entry);
                paintSpeech(entry);
            }
            schedule();
        }
        function positionSpeech() {
            const width = Math.max(1, host.clientWidth);
            const height = Math.max(1, host.clientHeight);
            const mobile = window.innerWidth <= 900;
            const worldPerPixel =
                (camera.top - camera.bottom) / height / camera.zoom;
            const up = new THREE.Vector3(0, 1, 0).applyQuaternion(
                camera.quaternion,
            );
            const right = new THREE.Vector3(1, 0, 0).applyQuaternion(
                camera.quaternion,
            );
            const project = (point: THREE.Vector3) => {
                const projected = point.clone().project(camera);
                return {
                    x: ((projected.x + 1) * width) / 2,
                    y: ((1 - projected.y) * height) / 2,
                };
            };
            const seatPoint = (seat: RitualSceneSeat) =>
                new THREE.Vector3(
                    (seat.x - 0.5) * 10,
                    0.9,
                    (seat.y - 0.5) * 7.2,
                );
            const obstacles: TableChatRect[] = mobile
                ? []
                : seats.map((seat) => {
                      const point = project(seatPoint(seat));
                      return {
                          x: point.x - 48,
                          y: point.y - 53,
                          width: 96,
                          height: 108,
                      };
                  });
            // Protect the whole brass dial, including its foreshortened text.
            if (display) {
                const points = Array.from({ length: 16 }, (_, index) => {
                    const angle = (index / 16) * Math.PI * 2;
                    return project(
                        new THREE.Vector3(
                            Math.cos(angle) * 1.35,
                            0.36,
                            Math.sin(angle) * 1.35,
                        ),
                    );
                });
                const x = Math.min(...points.map((point) => point.x));
                const y = Math.min(...points.map((point) => point.y));
                obstacles.push({
                    x,
                    y,
                    width: Math.max(...points.map((point) => point.x)) - x,
                    height: Math.max(...points.map((point) => point.y)) - y,
                });
            }
            let shown = 0;
            // Newest speech gets first choice of space. Compact tables show one
            // complete message; older entries remain in the ordinary transcript.
            for (const [id, entry] of [...speech].reverse()) {
                const seat = seats.find(
                    (seat) => seat.id === entry.bubble.playerId,
                );
                if (!seat || entry.bubble.expiresAt <= Date.now()) {
                    removeSpeech(id);
                    continue;
                }
                const { sprite, leader } = entry;
                const lineLimit = mobile ? 2 : 3;
                if (entry.lineLimit !== lineLimit) {
                    entry.ink.font = '34px Georgia, serif';
                    entry.lines = wrapTableChat(
                        entry.bubble.body,
                        (text) => entry.ink.measureText(text).width,
                        456,
                        lineLimit,
                    );
                    entry.ink.canvas.height = 80 + entry.lines.length * 43;
                    entry.lineLimit = lineLimit;
                    paintSpeech(entry);
                }
                sprite.visible = leader.visible = false;
                if (mobile && shown >= 1) continue;
                const pixels = Math.min(
                    width - 16,
                    (mobile ? 184 : 194) * camera.zoom,
                );
                const bubbleHeight = (pixels * entry.ink.canvas.height) / 512;
                const anchorWorld = seatPoint(seat).addScaledVector(
                    up,
                    (mobile ? 8 : 53) * worldPerPixel,
                );
                const anchor = project(anchorWorld);
                const placed = placeTableChatBubble(
                    anchor,
                    { width: pixels, height: bubbleHeight },
                    { width, height },
                    obstacles,
                );
                if (!placed) continue;
                shown++;
                obstacles.push(placed);
                sprite.visible = leader.visible = true;
                sprite.scale.set(
                    pixels * worldPerPixel,
                    bubbleHeight * worldPerPixel,
                    1,
                );
                sprite.position
                    .copy(anchorWorld)
                    .addScaledVector(
                        right,
                        (placed.x + pixels / 2 - anchor.x) * worldPerPixel,
                    )
                    .addScaledVector(
                        up,
                        (anchor.y - placed.y - bubbleHeight) * worldPerPixel,
                    );
                const endX = Math.max(
                    placed.x + 12,
                    Math.min(placed.x + pixels - 12, anchor.x),
                );
                const endY = Math.max(
                    placed.y + 8,
                    Math.min(placed.y + bubbleHeight - 2, anchor.y),
                );
                const end = anchorWorld
                    .clone()
                    .addScaledVector(right, (endX - anchor.x) * worldPerPixel)
                    .addScaledVector(up, (anchor.y - endY) * worldPerPixel);
                const vertices = leader.geometry.getAttribute('position');
                vertices.setXYZ(0, anchorWorld.x, anchorWorld.y, anchorWorld.z);
                vertices.setXYZ(1, end.x, end.y, end.z);
                vertices.needsUpdate = true;
            }
        }

        const ambient = new THREE.HemisphereLight(0xc6ded0, 0x20252b, 2.2);
        scene.add(ambient);
        const warm = new THREE.DirectionalLight(0xffd39a, 3.5);
        warm.position.set(-3, 6, 5);
        scene.add(warm);
        const edge = new THREE.DirectionalLight(0x8bced0, 2.5);
        edge.position.set(4, 3, -4);
        scene.add(edge);
        const ritualLight = new THREE.PointLight(0x7bf2c4, 0, 7, 2);
        ritualLight.position.set(0, 1, 0);
        scene.add(ritualLight);

        const woodCanvas = document.createElement('canvas');
        woodCanvas.width = woodCanvas.height = 512;
        const ink = woodCanvas.getContext('2d');
        if (ink) {
            ink.fillStyle = '#4c4030';
            ink.fillRect(0, 0, 512, 512);
            for (let i = 0; i < 450; i++) {
                const y = (i * 23.71) % 512;
                ink.strokeStyle = i % 3 ? '#9a805227' : '#161c1c65';
                ink.lineWidth = i % 5 === 0 ? 2 : 0.6;
                ink.beginPath();
                ink.moveTo(0, y);
                ink.bezierCurveTo(
                    140,
                    y + Math.sin(i) * 18,
                    310,
                    y - Math.cos(i) * 20,
                    512,
                    y + Math.sin(i * 2) * 8,
                );
                ink.stroke();
            }
            for (let y = 0; y < 512; y += 85) {
                ink.fillStyle = '#0c12137a';
                ink.fillRect(0, y, 512, 2);
            }
        }
        const woodTexture = new THREE.CanvasTexture(woodCanvas);
        woodTexture.colorSpace = THREE.SRGBColorSpace;
        textures.add(woodTexture);
        const wood = standard(0xc3b39a);
        wood.map = woodTexture;
        const brass = standard(0x91815a, 0.72, 0.42);
        const black = standard(0x111f20, 0.25);
        const oval = new THREE.Group();
        oval.scale.x = 1.4;
        scene.add(oval);
        const base = mesh(
            new THREE.CylinderGeometry(3.63, 3.48, 0.44, 96),
            standard(0x282920),
            oval,
        );
        base.position.y = -0.27;
        const lip = mesh(
            new THREE.CylinderGeometry(3.67, 3.67, 0.1, 96),
            brass,
            oval,
        );
        lip.position.y = -0.05;
        const surface = mesh(
            new THREE.CylinderGeometry(3.56, 3.6, 0.12, 96),
            wood,
            oval,
        );
        surface.position.y = 0.035;
        const rim = mesh(
            new THREE.TorusGeometry(3.46, 0.028, 8, 96),
            brass,
            oval,
        );
        rim.rotation.x = Math.PI / 2;
        rim.position.y = 0.105;
        const innerRim = mesh(
            new THREE.TorusGeometry(3.32, 0.012, 6, 96),
            brass,
            oval,
        );
        innerRim.rotation.x = Math.PI / 2;
        innerRim.position.y = 0.11;

        // Physical seal and inlaid rune strokes, kept small enough for every seat.
        const seal = mesh(
            new THREE.CylinderGeometry(1.15, 1.2, 0.13, 64),
            black,
        );
        seal.position.y = 0.17;
        const sealRing = mesh(
            new THREE.TorusGeometry(1.18, 0.045, 8, 64),
            brass,
        );
        sealRing.rotation.x = Math.PI / 2;
        sealRing.position.y = 0.25;
        const innerSeal = mesh(
            new THREE.TorusGeometry(0.76, 0.018, 8, 64),
            brass,
        );
        innerSeal.rotation.x = Math.PI / 2;
        innerSeal.position.y = 0.25;
        const portalMaterial = new THREE.MeshBasicMaterial({
            color: 0x74ddb6,
            transparent: true,
            opacity: 0.05,
        });
        const portal = mesh(new THREE.CircleGeometry(0.73, 64), portalMaterial);
        portal.rotation.x = -Math.PI / 2;
        portal.position.y = 0.242;

        // An enamel dial set into the seal. Only its ink rotates toward the
        // viewer: it remains a physical surface, with normal depth and zoom.
        const dial = new THREE.Group();
        scene.add(dial);
        dial.visible = !!display;
        dial.scale.set(1.22, 1, 1.22);
        const dialBase = mesh(
            new THREE.CylinderGeometry(1.24, 1.3, 0.09, 96),
            black,
            dial,
        );
        dialBase.position.y = 0.29;
        const dialRim = mesh(
            new THREE.TorusGeometry(1.25, 0.035, 8, 96),
            brass,
            dial,
        );
        dialRim.rotation.x = Math.PI / 2;
        dialRim.position.y = 0.34;
        const dialCanvas = document.createElement('canvas');
        dialCanvas.width = dialCanvas.height = 1024;
        const dialInk = dialCanvas.getContext('2d');
        if (!dialInk) throw new Error('Table dial unavailable');
        const dialTexture = new THREE.CanvasTexture(dialCanvas);
        dialTexture.colorSpace = THREE.SRGBColorSpace;
        dialTexture.anisotropy = Math.min(
            4,
            renderer.capabilities.getMaxAnisotropy(),
        );
        textures.add(dialTexture);
        const dialFace = mesh(
            new THREE.CircleGeometry(1.23, 96),
            new THREE.MeshBasicMaterial({
                map: dialTexture,
                toneMapped: false,
            }),
            dial,
        );
        dialFace.rotation.x = -Math.PI / 2;
        dialFace.position.y = 0.342;
        let displayKey = '';
        function drawDial() {
            if (!display) return;
            const nextKey = JSON.stringify(display);
            if (nextKey === displayKey) return;
            displayKey = nextKey;
            const ink = dialInk!;
            ink.fillStyle = '#132526';
            ink.fillRect(0, 0, 1024, 1024);
            ink.strokeStyle = '#a99665';
            ink.lineWidth = 3;
            ink.beginPath();
            ink.arc(512, 512, 478, 0, Math.PI * 2);
            ink.stroke();
            for (let i = 0; i < 60; i++) {
                const angle = (i / 60) * Math.PI * 2;
                const inner = i % 5 === 0 ? 432 : 450;
                ink.strokeStyle = i % 5 === 0 ? '#d0b980' : '#6d7154';
                ink.lineWidth = i % 5 === 0 ? 5 : 2;
                ink.beginPath();
                ink.moveTo(
                    512 + Math.sin(angle) * inner,
                    512 + Math.cos(angle) * inner,
                );
                ink.lineTo(
                    512 + Math.sin(angle) * 465,
                    512 + Math.cos(angle) * 465,
                );
                ink.stroke();
            }
            ink.textAlign = 'center';
            ink.textBaseline = 'middle';
            ink.fillStyle = '#e1c990';
            ink.font = '600 104px Georgia, serif';
            ink.fillText(display.phase.toUpperCase(), 512, 305, 710);
            ink.fillStyle = display.urgent ? '#ffb797' : '#fff0cc';
            ink.font = /^\d+:\d{2}$/.test(display.value)
                ? 'bold 240px Georgia, serif'
                : 'bold 148px Georgia, serif';
            ink.fillText(display.value, 512, 510, 830);
            ink.strokeStyle = '#a99665';
            ink.lineWidth = 2;
            ink.beginPath();
            ink.moveTo(360, 672);
            ink.lineTo(664, 672);
            ink.stroke();
            ink.fillStyle = '#c2d0b6';
            ink.font = '600 62px Georgia, serif';
            ink.fillText(display.detail.toUpperCase(), 512, 755, 690);
            dialTexture.needsUpdate = true;
        }
        const runes: THREE.MeshStandardMaterial[] = [];
        const runeGroup = new THREE.Group();
        scene.add(runeGroup);
        function buildRunes() {
            runeGroup.clear();
            runes.splice(0).forEach((material) => {
                materials.delete(material);
                material.dispose();
            });
            for (let i = 0; i < state.runeCount; i++) {
                const angle = (i / state.runeCount) * Math.PI * 2;
                const glyph = new THREE.Group();
                glyph.position.set(
                    Math.sin(angle) * 0.96,
                    0.26,
                    Math.cos(angle) * 0.96,
                );
                glyph.rotation.y = angle;
                runeGroup.add(glyph);
                const material = standard(0x756e4f, 0.4);
                material.emissive.set(0x86e1b2);
                runes.push(material);
                for (const [x, z, rotation] of [
                    [0, 0, 0],
                    [-0.045, -0.015, 0.85],
                    [0.045, 0.015, 0.85],
                ]) {
                    const stroke = mesh(runeGeometry, material, glyph);
                    stroke.position.set(x, 0, z);
                    stroke.rotation.y = rotation;
                }
            }
        }
        const runeGeometry = new THREE.BoxGeometry(0.024, 0.014, 0.17);
        geometries.add(runeGeometry);
        buildRunes();

        const cracks = new THREE.Group();
        scene.add(cracks);
        const crackMaterial = new THREE.MeshBasicMaterial({
            color: 0x82cdaa,
            transparent: true,
            opacity: 0,
        });
        for (let i = 0; i < 7; i++) {
            const angle = (i / 7) * Math.PI * 2 + 0.14;
            const points = [0.72, 1.04, 1.21, 1.55, 1.72].map(
                (radius, j) =>
                    new THREE.Vector3(
                        Math.sin(angle + (j % 2) * 0.14) * radius,
                        0.13,
                        Math.cos(angle + (j % 2) * 0.14) * radius,
                    ),
            );
            mesh(
                new THREE.TubeGeometry(
                    new THREE.CatmullRomCurve3(points),
                    15,
                    0.014,
                    4,
                    false,
                ),
                crackMaterial,
                cracks,
            );
        }

        const tentacles = new THREE.Group();
        tentacles.position.y = 0.25;
        scene.add(tentacles);
        const skin = standard(0x436855, 0.22, 0.42);
        skin.emissive.set(0x193e2c);
        skin.emissiveIntensity = 0.4;
        const underside = standard(0x8dab78, 0.25, 0.5);
        for (let i = 0; i < 6; i++) {
            const arm = new THREE.Group();
            arm.rotation.y = (i / 6) * Math.PI * 2;
            tentacles.add(arm);
            const height = 1.7 + (i % 3) * 0.35;
            const path = new THREE.CatmullRomCurve3([
                new THREE.Vector3(0.18, 0, 0),
                new THREE.Vector3(0.48, height * 0.35, 0.09),
                new THREE.Vector3(0.95, height * 0.7, 0.18),
                new THREE.Vector3(0.82, height, 0.12),
                new THREE.Vector3(0.52, height * 0.96, 0.02),
                new THREE.Vector3(0.57, height * 0.8, 0),
            ]);
            const tube = new THREE.TubeGeometry(path, 40, 0.17, 8, false);
            const positions = tube.getAttribute('position');
            for (let segment = 0; segment <= 40; segment++) {
                const center = path.getPointAt(segment / 40);
                const taper = Math.pow(1 - segment / 40, 0.7) * 0.95 + 0.05;
                for (let side = 0; side <= 8; side++) {
                    const index = segment * 9 + side;
                    positions.setXYZ(
                        index,
                        center.x + (positions.getX(index) - center.x) * taper,
                        center.y + (positions.getY(index) - center.y) * taper,
                        center.z + (positions.getZ(index) - center.z) * taper,
                    );
                }
            }
            tube.computeVertexNormals();
            mesh(tube, skin, arm);
            for (let j = 1; j <= 6; j++) {
                const point = path.getPointAt(j / 9);
                const sucker = mesh(
                    new THREE.TorusGeometry(0.065 * (1 - j / 10), 0.018, 5, 10),
                    underside,
                    arm,
                );
                sucker.position
                    .copy(point)
                    .add(new THREE.Vector3(0, 0, 0.12 * (1 - j / 10)));
            }
        }

        const candleGroup = new THREE.Group();
        scene.add(candleGroup);
        const wax = standard(0xcebc8d);
        const flameMaterial = new THREE.MeshBasicMaterial({ color: 0xffd695 });
        const candleGeometry = new THREE.CylinderGeometry(
            0.07,
            0.095,
            0.38,
            10,
        );
        const holderGeometry = new THREE.CylinderGeometry(0.16, 0.2, 0.065, 16);
        const flameGeometry = new THREE.SphereGeometry(0.066, 8, 6);
        const flames: { mesh: THREE.Mesh; alive: boolean }[] = [];
        function buildCandles(seats: RitualSceneSeat[]) {
            candleGroup.clear();
            flames.length = 0;
            // Seats are normalized public layout coordinates, never identities or roles.
            seats.forEach((seat) => {
                const candle = new THREE.Group();
                candle.position.set(
                    (seat.x - 0.5) * 10,
                    0.15,
                    (seat.y - 0.5) * 7.2,
                );
                candleGroup.add(candle);
                mesh(holderGeometry, brass, candle);
                const body = mesh(candleGeometry, wax, candle);
                body.position.y = 0.21;
                const flame = mesh(flameGeometry, flameMaterial, candle);
                flame.position.y = 0.49;
                flame.scale.y = 2.4;
                flame.visible = seat.alive;
                flames.push({ mesh: flame, alive: seat.alive });
            });
        }
        let seatKey = JSON.stringify(initialSeats);
        buildCandles(initialSeats);

        const mistCanvas = document.createElement('canvas');
        mistCanvas.width = mistCanvas.height = 64;
        const haze = mistCanvas.getContext('2d');
        if (haze) {
            const gradient = haze.createRadialGradient(32, 32, 0, 32, 32, 32);
            gradient.addColorStop(0, '#b9d7bc75');
            gradient.addColorStop(0.35, '#93bbaa35');
            gradient.addColorStop(1, '#93bbaa00');
            haze.fillStyle = gradient;
            haze.fillRect(0, 0, 64, 64);
        }
        const mistTexture = new THREE.CanvasTexture(mistCanvas);
        textures.add(mistTexture);
        const mistMaterial = new THREE.SpriteMaterial({
            map: mistTexture,
            transparent: true,
            depthWrite: false,
            opacity: 0,
        });
        materials.add(mistMaterial);
        const wisps = Array.from({ length: 9 }, (_, i) => {
            const sprite = new THREE.Sprite(mistMaterial);
            sprite.scale.set(1.4, 0.65, 1);
            sprite.position.set(Math.sin(i * 2.4), 0.4, Math.cos(i * 2.4));
            scene.add(sprite);
            return sprite;
        });
        const dustGeometry = new THREE.BufferGeometry();
        const dustPositions = new Float32Array(36 * 3);
        for (let i = 0; i < 36; i++) {
            dustPositions[i * 3] = Math.sin(i * 7.4) * 4.7;
            dustPositions[i * 3 + 1] = 0.5 + ((i * 0.73) % 2.6);
            dustPositions[i * 3 + 2] = Math.cos(i * 3.3) * 2.6;
        }
        dustGeometry.setAttribute(
            'position',
            new THREE.BufferAttribute(dustPositions, 3),
        );
        geometries.add(dustGeometry);
        const dustMaterial = new THREE.PointsMaterial({
            color: 0xd6bf88,
            size: 0.018,
            transparent: true,
            opacity: 0.55,
            depthWrite: false,
        });
        materials.add(dustMaterial);
        const dust = new THREE.Points(dustGeometry, dustMaterial);
        scene.add(dust);

        function render(delta = 0) {
            if (disposed) return;
            drawDial();
            const sealScale = display ? 1.62 : 1;
            seal.scale.set(sealScale, 1, sealScale);
            sealRing.scale.setScalar(sealScale);
            runeGroup.scale.set(display ? 1.7 : 1, 1, display ? 1.7 : 1);
            innerSeal.visible = !display;
            portal.visible = !display;
            const azimuth = controls.getAzimuthalAngle();
            dial.rotation.y = azimuth;
            // Keep the ritual visible beyond the dial, leaving its face clear
            // even when the creature fully emerges or the camera is orbited.
            tentacles.position.set(
                display ? -Math.sin(azimuth) * 1.9 : 0,
                0.25,
                display ? -Math.cos(azimuth) * 1.9 : 0,
            );
            const blend = motion.matches ? 1 : 1 - Math.exp(-delta * 2);
            darkness += ((state.night ? 1 : 0) - darkness) * blend;
            emergence += (state.emergence - emergence) * blend;
            warm.intensity = 3.5 - darkness * 1.9;
            edge.intensity = 2.1 + darkness * 0.8;
            ambient.intensity = 2.2 - darkness * 0.65;
            ritualLight.intensity = state.calmed ? 0 : state.progress * 5;
            portalMaterial.opacity = state.calmed
                ? 0.025
                : 0.04 + state.progress * 0.26;
            crackMaterial.opacity = state.cracks * 0.6;
            tentacles.visible = emergence > 0.005;
            tentacles.scale.setScalar(
                Math.max(0.001, emergence * (display ? 0.65 : 1)),
            );
            tentacles.children.forEach((arm, i) => {
                arm.rotation.z = motion.matches
                    ? 0
                    : Math.sin(clock * 0.45 + i) * 0.045;
            });
            mistMaterial.opacity = state.mist * 0.48;
            wisps.forEach((wisp, i) => {
                const time = motion.matches ? 0 : clock * 0.15;
                wisp.position.set(
                    Math.sin(i * 2.4 + time) * 0.95 -
                        (display ? Math.sin(azimuth) * 1.9 : 0),
                    0.5 + Math.sin(i + time) * 0.2,
                    Math.cos(i * 2.4 + time) * 0.95 -
                        (display ? Math.cos(azimuth) * 1.9 : 0),
                );
            });
            runes.forEach((material, i) => {
                material.emissiveIntensity = i < state.litRunes ? 1.7 : 0.02;
                material.color.setHex(i < state.litRunes ? 0xb8dca3 : 0x756e4f);
            });
            flames.forEach(({ mesh: flame }, i) => {
                flame.scale.y =
                    2.4 +
                    (motion.matches ? 0 : Math.sin(clock * 5 + i * 2) * 0.24);
            });
            dust.rotation.y = motion.matches
                ? 0
                : Math.sin(clock * 0.035) * 0.12;
            positionSpeech();
            renderer.render(scene, camera);
            if (interactive && projectSeats) {
                const positions = seats.map((seat) => {
                    const point = new THREE.Vector3(
                        (seat.x - 0.5) * 10,
                        0.9,
                        (seat.y - 0.5) * 7.2,
                    ).project(camera);
                    return {
                        left: `${(point.x + 1) * 50}%`,
                        top: `${(1 - point.y) * 50}%`,
                    };
                });
                const nextProjectionKey = JSON.stringify(positions);
                if (nextProjectionKey !== projectionKey) {
                    projectionKey = nextProjectionKey;
                    projectSeats(positions);
                }
            }
        }
        function animate(now: number) {
            frame = 0;
            if (disposed || document.hidden || !visible) return;
            if (now - lastFrame >= 1000 / 30) {
                const delta = Math.min(0.1, (now - lastFrame) / 1000);
                lastFrame = now;
                clock += delta;
                try {
                    render(delta);
                } catch {
                    dispose();
                    failed();
                    return;
                }
            }
            if (!motion.matches) frame = requestAnimationFrame(animate);
        }
        schedule = () => {
            cancelAnimationFrame(frame);
            frame = 0;
            if (disposed || document.hidden || !visible) return;
            try {
                if (motion.matches) render(1);
                else frame = requestAnimationFrame(animate);
            } catch {
                dispose();
                failed();
            }
        };
        changeMotion = () => schedule();
        const resize = () => {
            if (disposed) return;
            const width = Math.max(1, host.clientWidth);
            const height = Math.max(1, host.clientHeight);
            const viewHeight = Math.max(7.3, (11.8 * height) / width);
            camera.left = (-viewHeight * width) / height / 2;
            camera.right = -camera.left;
            camera.top = viewHeight / 2;
            camera.bottom = -camera.top;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height, false);
            try {
                render(1);
            } catch {
                dispose();
                failed();
            }
        };
        resize();
        if (disposed) throw new Error('Table visuals unavailable');
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(host);
        if ('IntersectionObserver' in window) {
            intersectionObserver = new IntersectionObserver(([entry]) => {
                visible = entry.isIntersecting;
                schedule();
            });
            intersectionObserver.observe(host);
        }
        document.addEventListener('visibilitychange', schedule);
        motion.addEventListener('change', changeMotion);
        schedule();
        return {
            update(next, nextSeats) {
                if (disposed) return;
                const oldRuneCount = state.runeCount;
                state = next;
                seats = nextSeats;
                if (oldRuneCount !== state.runeCount) buildRunes();
                const nextKey = JSON.stringify(seats);
                if (nextKey !== seatKey) {
                    seatKey = nextKey;
                    buildCandles(seats);
                }
                if (state.progress === 0 || state.calmed) emergence = 0;
                schedule();
            },
            dispose,
            updateBubbles,
            updateDisplay(next) {
                if (disposed) return;
                display = next;
                dial.visible = !!display;
                schedule();
            },
            resetView: () => {
                controls.reset();
                schedule();
            },
        };
    } catch (error) {
        dispose();
        throw error;
    }
}
