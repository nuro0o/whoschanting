import * as THREE from 'three';
import type { RitualCosmeticEvent, RitualSceneSeat } from './ritualSceneState';
import { createPaidBanishments } from './ritualBanishments.ts';
import { createPaidCelebrations } from './ritualCelebrations.ts';
import {
    banishmentDetail,
    celebrationDetail,
    cosmeticDuration,
} from './ritualCosmeticTiming.ts';

/** Fixed geometry pool, shared by the store preview and live table. */
export function createTableCosmetics(scene: THREE.Scene) {
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    const effect = new THREE.Group();
    effect.name = 'Table cosmetic event';
    scene.add(effect);
    const legacy = new THREE.Group();
    legacy.name = 'Classic banishment and celebrations';
    effect.add(legacy);
    const paid = createPaidBanishments(effect);
    const celebrations = createPaidCelebrations(effect);
    const glow = new THREE.MeshStandardMaterial({
        color: 0xf5ce7a,
        emissive: 0xc68b37,
        emissiveIntensity: 0.6,
        transparent: true,
    });
    const shadow = new THREE.MeshStandardMaterial({
        color: 0x425c58,
        transparent: true,
    });
    [glow, shadow].forEach((material) => materials.add(material));
    function geometry<T extends THREE.BufferGeometry>(value: T): T {
        geometries.add(value);
        return value;
    }
    const ring = geometry(new THREE.TorusGeometry(0.35, 0.045, 6, 32));
    const jewel = geometry(new THREE.OctahedronGeometry(0.12));
    const moon = geometry(
        new THREE.TorusGeometry(0.42, 0.075, 8, 40, Math.PI * 1.45),
    );
    const lantern = geometry(new THREE.CylinderGeometry(0.1, 0.14, 0.22, 6));
    const crown = geometry(
        new THREE.CylinderGeometry(0.2, 0.17, 0.2, 10, 1, true),
    );
    const crownVertices = crown.getAttribute('position');
    for (let i = 0; i < crownVertices.count; i++) {
        if (crownVertices.getY(i) > 0)
            crownVertices.setY(i, i % 2 === 0 ? 0.23 : 0.07);
    }
    crown.computeVertexNormals();
    const body = geometry(new THREE.ConeGeometry(0.26, 0.7, 12));
    const head = geometry(new THREE.SphereGeometry(0.14, 12, 8));
    function mesh(
        shape: THREE.BufferGeometry,
        material: THREE.Material,
        parent: THREE.Object3D,
    ) {
        const object = new THREE.Mesh(shape, material);
        parent.add(object);
        return object;
    }
    const halo = mesh(ring, glow, legacy);
    halo.rotation.x = -Math.PI / 2;
    const risingMoon = mesh(moon, glow, legacy);
    const pawn = new THREE.Group();
    legacy.add(pawn);
    mesh(body, shadow, pawn).position.y = 0.35;
    mesh(head, shadow, pawn).position.y = 0.88;
    const particles = Array.from({ length: 28 }, () =>
        mesh(jewel, glow, legacy),
    );
    let active: RitualCosmeticEvent | null = null;
    let elapsed = 0;
    const origin = new THREE.Vector3();
    effect.visible = false;

    return {
        table(id: string) {
            return id === 'moonlit'
                ? 0x7e8dad
                : id === 'harvest'
                  ? 0xe6ad6a
                  : id === 'founders_oak'
                    ? 0xddbf84
                    : 0xc3b39a;
        },
        play(event: RitualCosmeticEvent | null, seats: RitualSceneSeat[]) {
            if (event?.id === active?.id) return;
            active = event;
            elapsed = 0;
            effect.visible = !!event;
            paid.reset();
            celebrations.reset();
            legacy.visible =
                !!event &&
                !(
                    (event.kind === 'banishment' &&
                        banishmentDetail(event.effect)) ||
                    (event.kind === 'celebration' &&
                        celebrationDetail(event.effect))
                );
            const seat = seats.find((seat) => seat.id === event?.player_id);
            origin.set(
                seat ? (seat.x - 0.5) * 10 : 0,
                0.25,
                seat ? (seat.y - 0.5) * 7.2 : 0,
            );
            const lunar =
                event?.effect === 'lunar_rift' || event?.effect === 'moonrise';
            const harvest =
                event?.effect === 'ember_spiral' ||
                event?.effect === 'lantern_festival';
            glow.color.setHex(lunar ? 0xc5c5ff : harvest ? 0xffb75e : 0xf5ce7a);
            glow.emissive.setHex(
                lunar ? 0x8071db : harvest ? 0xc25a23 : 0xc68b37,
            );
            particles.forEach((particle) => {
                particle.geometry =
                    event?.effect === 'lantern_festival'
                        ? lantern
                        : event?.effect === 'crownfall'
                          ? crown
                          : jewel;
            });
        },
        update(delta: number, reduced: boolean) {
            const event = active;
            if (!event) return;
            elapsed += reduced ? 0 : delta;
            const progress = reduced
                ? 0.45
                : Math.min(1, elapsed / cosmeticDuration(event));
            effect.visible = reduced || progress < 1;
            if (!legacy.visible) {
                if (event.kind === 'banishment')
                    paid.update(event.effect, elapsed, origin, reduced);
                else celebrations.update(event.effect, elapsed, reduced);
                return;
            }
            const banish = event.kind === 'banishment';
            const fade = reduced
                ? 1
                : Math.min(1, progress * 8, (1 - progress) * 6);
            glow.opacity = fade;
            shadow.opacity = fade;
            pawn.visible = banish;
            pawn.position.copy(origin).multiplyScalar(1 - progress);
            pawn.position.y =
                0.3 + Math.sin(progress * Math.PI) * 1.4 - progress * 0.4;
            pawn.scale.setScalar(Math.max(0.03, 1 - progress));
            pawn.rotation.y = progress * Math.PI * 3;
            halo.position.copy(
                banish ? pawn.position : new THREE.Vector3(0, 0.35, 0),
            );
            halo.scale.setScalar(
                banish
                    ? 1.2 + Math.sin(progress * Math.PI) * 2
                    : 3 + progress * 4,
            );
            risingMoon.visible = event.effect === 'moonrise';
            risingMoon.position.set(0, 0.6 + progress * 3.5, 0);
            risingMoon.scale.setScalar(2);
            particles.forEach((particle, i) => {
                const angle = i * 2.4 + progress * Math.PI * (banish ? 5 : 0.8);
                if (banish) {
                    const radius =
                        (0.3 + (i % 5) * 0.12) * Math.sin(progress * Math.PI);
                    particle.position.set(
                        pawn.position.x + Math.cos(angle) * radius,
                        pawn.position.y + (i % 7) * 0.12,
                        pawn.position.z + Math.sin(angle) * radius,
                    );
                    if (event.effect === 'lunar_rift')
                        particle.position.x =
                            pawn.position.x + Math.cos(angle) * radius * 0.2;
                    if (event.effect === 'ember_spiral')
                        particle.position.y += progress * (i % 4);
                } else {
                    const radius = 0.5 + (i % 7) * 0.57;
                    particle.position.set(
                        Math.cos(angle) * radius,
                        event.effect === 'crownfall'
                            ? 3.8 - progress * 3.3 + (i % 3) * 0.2
                            : 0.5 + progress * (1.8 + (i % 4)),
                        Math.sin(angle) * radius * 0.7,
                    );
                }
                particle.rotation.set(
                    event.effect === 'lantern_festival' ? 0 : progress * 4 + i,
                    angle,
                    event.effect === 'lantern_festival' ? 0 : i,
                );
                particle.scale.setScalar(
                    event.effect === 'crownfall' ? 0.8 : 0.5 + (i % 3) * 0.2,
                );
            });
        },
        dispose() {
            paid.dispose();
            celebrations.dispose();
            scene.remove(effect);
            geometries.forEach((value) => value.dispose());
            materials.forEach((value) => value.dispose());
        },
    };
}
