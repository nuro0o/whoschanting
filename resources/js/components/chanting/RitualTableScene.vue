<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { RitualSceneSeat, RitualSceneState } from '@/lib/ritualSceneState';
import type { RitualTableRenderer } from '@/lib/ritualTableRenderer';

const props = defineProps<{
    enabled: boolean;
    state: RitualSceneState;
    seats: RitualSceneSeat[];
}>();
const emit = defineEmits<{ ready: [ready: boolean]; unavailable: [] }>();
const host = ref<HTMLElement>();
let renderer: RitualTableRenderer | undefined;
let mounted = false;
let generation = 0;

function stop() {
    generation++;
    renderer?.dispose();
    renderer = undefined;
    emit('ready', false);
}
async function start() {
    stop();
    if (!mounted || !props.enabled || !host.value) return;
    const current = generation;
    try {
        const { createRitualTable } = await import('@/lib/ritualTableRenderer');
        if (!mounted || !props.enabled || current !== generation || !host.value)
            return;
        renderer = createRitualTable(
            host.value,
            props.state,
            props.seats,
            () => {
                if (!mounted || current !== generation) return;
                stop();
                emit('unavailable');
            },
        );
        emit('ready', true);
    } catch {
        if (!mounted || current !== generation) return;
        stop();
        emit('unavailable');
    }
}
watch(() => props.enabled, start);
watch(
    () => [props.state, props.seats],
    () => renderer?.update(props.state, props.seats),
);
onMounted(() => {
    mounted = true;
    void start();
});
onBeforeUnmount(() => {
    mounted = false;
    stop();
});
</script>

<template>
    <div ref="host" class="ritual-table-scene" aria-hidden="true"></div>
</template>

<style scoped>
.ritual-table-scene {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: -1;
}
.ritual-table-scene :deep(canvas) {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
