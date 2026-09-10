<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Player, RoomState } from '@/lib/chanting';
import CharacterPortrait from './CharacterPortrait.vue';

// Only public seats and this player's submission enter the scene. No roles,
// targets, investigations, missions, or other players' private actions.
const props = defineProps<{
    players: Pick<
        Player,
        'id' | 'name' | 'character' | 'alive' | 'ready' | 'discussion_ready'
    >[];
    phase: RoomState['phase'];
    phaseId: number;
    meId: string;
    submitted: boolean;
}>();
const canvas = ref<HTMLElement>();
const available = ref(false);
const failed = ref(false);
let disposed = false;
let game: import('phaser').Game | undefined;
let scene: { draw: (animate: boolean) => void } | undefined;
let motion: MediaQueryList | undefined;
const reduced = ref(false);
const seatStyle = (index: number) => {
    const angle =
        (index / Math.max(1, props.players.length)) * Math.PI * 2 - Math.PI / 2;
    return {
        left: `${50 + Math.cos(angle) * 40}%`,
        top: `${50 + Math.sin(angle) * 38}%`,
    };
};
const caption = computed(
    () =>
        ({
            lobby: 'A place for every alibi.',
            reveal: 'Your secret is in your role card below.',
            night: 'Cards down. Secrets kept.',
            discussion: 'Stories on the table.',
            voting: 'One choice. Sealed until the count.',
            finished: 'The cards are on the table.',
        })[props.phase],
);
function motionChanged() {
    reduced.value = motion?.matches ?? false;
    scene?.draw(false);
}
watch(
    () =>
        [
            props.phaseId,
            props.submitted,
            props.players
                .map(
                    (p) =>
                        `${p.id}:${p.alive}:${p.ready}:${p.discussion_ready}`,
                )
                .join('|'),
        ].join('/'),
    () => scene?.draw(!reduced.value),
);
onMounted(async () => {
    motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    motionChanged();
    motion.addEventListener('change', motionChanged);
    try {
        const Phaser = await import('phaser');
        if (disposed || !canvas.value) return;
        class TableScene extends Phaser.Scene {
            previousPhase = -1;
            previousSubmitted = false;
            create() {
                scene = { draw: (animate: boolean) => this.draw(animate) };
                available.value = true;
                this.draw(!reduced.value);
            }
            draw(animate: boolean) {
                this.tweens.killAll();
                this.children.removeAll(true);
                const changed = this.previousPhase !== props.phaseId;
                const sealed =
                    !changed && props.submitted && !this.previousSubmitted;
                this.previousPhase = props.phaseId;
                this.previousSubmitted = props.submitted;
                const ink = this.add.graphics();
                ink.fillStyle(0x080f11, 0.3);
                ink.fillEllipse(450, 272, 670, 302);
                ink.fillStyle(props.phase === 'night' ? 0x152c2c : 0x254039);
                ink.fillEllipse(450, 250, 660, 290);
                ink.lineStyle(2, 0x76805c, 0.7);
                ink.strokeEllipse(450, 250, 660, 290);
                ink.lineStyle(1, 0x76805c, 0.3);
                ink.strokeEllipse(450, 250, 630, 266);
                ink.lineStyle(1, 0xbdcd9c, 0.22);
                ink.strokeCircle(450, 250, 49);
                ink.strokeCircle(450, 250, 38);
                this.add
                    .text(450, 250, props.phase === 'night' ? '☾' : '✧', {
                        fontFamily: 'Georgia',
                        fontSize: '43px',
                        color: '#bdcd9c',
                    })
                    .setOrigin(0.5)
                    .setAlpha(0.7);
                props.players.forEach((player, index) => {
                    const angle =
                        (index / Math.max(1, props.players.length)) *
                            Math.PI *
                            2 -
                        Math.PI / 2;
                    const x = 450 + Math.cos(angle) * 242;
                    const y = 250 + Math.sin(angle) * 91;
                    const card = this.add.container(x, y);
                    const back = this.add.graphics();
                    back.fillStyle(0x080f11, 0.4);
                    back.fillRoundedRect(-23, -30, 49, 66, 4);
                    back.fillStyle(0xe4dec5);
                    back.fillRoundedRect(-24, -34, 48, 64, 3);
                    back.fillStyle(0x233c37);
                    back.fillRoundedRect(-20, -30, 40, 56, 2);
                    back.lineStyle(1, 0xbdcd9c, 0.65);
                    back.strokeRoundedRect(-16, -26, 32, 48, 1);
                    const glyph = this.add
                        .text(0, -1, '✧', {
                            fontFamily: 'Georgia',
                            fontSize: '24px',
                            color: '#bdcd9c',
                        })
                        .setOrigin(0.5);
                    card.add([back, glyph]);
                    card.setAngle(Math.sin(angle) * 7);
                    card.setAlpha(player.alive ? 1 : 0.3);
                    if (
                        player.id === props.meId &&
                        props.submitted &&
                        ['night', 'voting'].includes(props.phase)
                    ) {
                        const wax = this.add.circle(0, 14, 9, 0xb77861);
                        card.add(wax);
                        if (sealed && animate)
                            this.tweens.add({
                                targets: card,
                                x: 450,
                                y: 300,
                                angle: 0,
                                duration: 420,
                                ease: 'Cubic.Out',
                            });
                        else card.setPosition(450, 300).setAngle(0);
                    } else if (changed && animate) {
                        card.setPosition(450, 250).setScale(0.65).setAlpha(0);
                        this.tweens.add({
                            targets: card,
                            x,
                            y,
                            scale: 1,
                            alpha: player.alive ? 1 : 0.3,
                            duration: 650,
                            delay: index * 65,
                            ease: 'Cubic.Out',
                        });
                    }
                });
            }
        }
        game = new Phaser.Game({
            type: Phaser.CANVAS,
            parent: canvas.value,
            width: 900,
            height: 500,
            transparent: true,
            scene: TableScene,
            audio: { noAudio: true },
            banner: false,
            scale: {
                mode: Phaser.Scale.FIT,
                autoCenter: Phaser.Scale.CENTER_BOTH,
            },
            fps: { target: 30, forceSetTimeOut: true },
        });
    } catch {
        failed.value = true;
    }
});
onBeforeUnmount(() => {
    disposed = true;
    motion?.removeEventListener('change', motionChanged);
    scene = undefined;
    game?.destroy(true);
});
</script>
<template>
    <section class="table-panel" aria-label="The village card table">
        <div class="table-topline">
            <span class="eyebrow">THE VILLAGE TABLE</span
            ><span>Faces are cosmetic. Secrets stay secret.</span>
        </div>
        <div class="card-table" :class="{ 'is-night': phase === 'night' }">
            <div v-if="!available" class="table-fallback" aria-hidden="true">
                <span>✧</span>
            </div>
            <div ref="canvas" class="table-canvas" aria-hidden="true"></div>
            <div
                v-for="(player, index) in players"
                :key="player.id"
                class="table-seat"
                :class="{
                    'is-me': player.id === meId,
                    'is-banished': !player.alive,
                }"
                :style="seatStyle(index)"
                aria-hidden="true"
            >
                <CharacterPortrait :character="player.character" decorative />
                <span class="seat-name"
                    >{{ player.name
                    }}{{ player.id === meId ? ' · You' : '' }}</span
                >
                <span
                    v-if="
                        phase === 'discussion' &&
                        player.alive &&
                        player.discussion_ready
                    "
                    class="seat-ready"
                    >Ready for voting</span
                >
                <span
                    v-else-if="phase === 'lobby' && player.ready"
                    class="seat-ready"
                    >Ready</span
                >
                <span v-else-if="!player.alive" class="seat-banished"
                    >Banished</span
                >
            </div>
        </div>
        <p class="table-caption">
            {{ caption
            }}<span v-if="failed">
                The animated table is unavailable; all controls remain
                below.</span
            >
        </p>
    </section>
</template>
