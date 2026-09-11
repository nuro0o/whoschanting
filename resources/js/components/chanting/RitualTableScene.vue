<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type {
    RitualSceneSeat,
    RitualSceneState,
    RitualTableDisplay,
} from '@/lib/ritualSceneState';
import type { RitualTableRenderer } from '@/lib/ritualTableRenderer';
import type { TableChatBubble } from '@/lib/tableChat';

const props = defineProps<{
    state: RitualSceneState;
    seats: RitualSceneSeat[];
    interactive?: boolean;
    display?: RitualTableDisplay;
    bubbles?: TableChatBubble[];
}>();
const emit = defineEmits<{
    ready: [ready: boolean];
    unavailable: [];
    positions: [positions: { left: string; top: string }[]];
}>();
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
    if (!mounted || !host.value) return;
    const current = generation;
    try {
        const { createRitualTable } = await import('@/lib/ritualTableRenderer');
        if (!mounted || current !== generation || !host.value) return;
        renderer = createRitualTable(
            host.value,
            props.state,
            props.seats,
            () => {
                if (!mounted || current !== generation) return;
                stop();
                emit('unavailable');
            },
            props.interactive,
            (positions) => emit('positions', positions),
            props.display,
        );
        renderer.updateBubbles(props.bubbles ?? []);
        emit('ready', true);
    } catch {
        if (!mounted || current !== generation) return;
        stop();
        emit('unavailable');
    }
}
watch(() => props.interactive, start);
defineExpose({ resetView: () => renderer?.resetView() });
watch(
    () => props.bubbles,
    () => renderer?.updateBubbles(props.bubbles ?? []),
);
watch(
    () => props.display,
    () => renderer?.updateDisplay(props.display),
);
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
    <div
        ref="host"
        class="ritual-table-scene"
        :class="{ 'is-interactive': interactive }"
        :aria-hidden="interactive ? undefined : true"
    ></div>
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
.ritual-table-scene.is-interactive {
    pointer-events: auto;
}
.is-interactive :deep(canvas) {
    cursor: grab;
    touch-action: none;
}
.is-interactive :deep(canvas:active) {
    cursor: grabbing;
}
.is-interactive :deep(canvas:focus-visible) {
    outline: 2px solid #dec784;
    outline-offset: -3px;
}
</style>
