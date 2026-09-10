<script setup lang="ts">
import { computed, onMounted, shallowRef, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';
import { ritualNightStage, usesNightAtmosphere } from '@/lib/ritualAtmosphere';

const props = defineProps<{
    phase: RoomState['phase'];
    ritualTokens: number;
    ritualThreshold: number;
    finalVote: boolean;
}>();

const nightImage = (stage: number) =>
    `/assets/chanting/village-night-${stage}.webp`;
const scene = computed(() => {
    const night = usesNightAtmosphere(props.phase, props.finalVote);
    const stage = ritualNightStage(props.ritualTokens, props.ritualThreshold);
    return {
        night,
        stage,
        src: night
            ? nightImage(stage)
            : '/assets/chanting/village-morning.webp',
    };
});
const visible = shallowRef<(typeof scene)['value']>();
const images = new Map<string, Promise<void>>();

function loadImage(src: string): Promise<void> {
    const existing = images.get(src);
    if (existing) return existing;

    const image = new Image();
    image.decoding = 'async';
    image.src = src;
    const ready = image.decode().catch((error: unknown) => {
        images.delete(src);
        throw error;
    });
    images.set(src, ready);
    return ready;
}

onMounted(() => {
    watch(
        scene,
        async (target, _previous, onCleanup) => {
            let current = true;
            onCleanup(() => {
                current = false;
            });

            try {
                await loadImage(target.src);
                // A newer phase/progress update, or unmount, owns the scene now.
                if (!current) return;
                visible.value = target;

                // Warm only the likely next night image, not the whole sequence.
                const nextStage = target.night
                    ? Math.min(6, target.stage + 1)
                    : target.stage;
                void loadImage(nightImage(nextStage)).catch(() => {});
            } catch {
                // Keep the last decoded scene if a new asset is unavailable.
            }
        },
        { immediate: true },
    );
});
</script>

<template>
    <div
        class="game-atmosphere"
        :class="{ 'is-night': visible?.night ?? scene.night }"
        :data-night-stage="visible?.night ? visible.stage : undefined"
        aria-hidden="true"
    >
        <TransitionGroup name="atmosphere-scene">
            <div
                v-if="visible"
                :key="visible.src"
                class="game-atmosphere__scene"
                :class="
                    visible.night
                        ? 'game-atmosphere__night'
                        : 'game-atmosphere__morning'
                "
                :style="{ '--scene-image': `url('${visible.src}')` }"
            ></div>
        </TransitionGroup>
        <div class="game-atmosphere__haze"></div>
    </div>
</template>
