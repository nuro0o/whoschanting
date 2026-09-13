<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import RitualTableScene from './RitualTableScene.vue';
import {
    banishmentDetail,
    cosmeticPlaybackMilliseconds,
} from '@/lib/ritualCosmeticTiming';
import {
    ritualSceneState,
    type RitualCosmeticEvent,
} from '@/lib/ritualSceneState';

const props = defineProps<{
    table?: string;
    banishment?: string;
    celebration?: string;
}>();
const unavailable = ref(false);
const active = ref<RitualCosmeticEvent | null>(null);
const state = ritualSceneState({
    phase: 'lobby',
    tokens: 0,
    threshold: 6,
    winner: null,
});
const seats = [
    { id: 'preview', x: 0.5, y: 0.18, alive: true },
    { x: 0.8, y: 0.76, alive: true },
    { x: 0.2, y: 0.76, alive: true },
];
let timer: ReturnType<typeof setTimeout> | undefined;
let serial = 0;
const tableName = computed(
    () =>
        ({
            founders_oak: 'Founder’s oak',
            moonlit: 'Moonlit table',
            harvest: 'Harvest table',
        })[props.table ?? ''] ?? 'Classic village table',
);
const caption = computed(() =>
    active.value?.kind === 'banishment'
        ? (banishmentDetail(active.value.effect)?.name ?? 'Classic banishment')
        : active.value
          ? 'Victory preview'
          : tableName.value,
);
const banishment = computed(() => banishmentDetail(props.banishment ?? ''));
function play(kind: 'banishment' | 'celebration') {
    clearTimeout(timer);
    active.value = {
        id: `preview-${++serial}`,
        kind,
        player_id: 'preview',
        effect: props[kind] ?? 'classic',
        created_at: new Date().toISOString(),
    };
    timer = setTimeout(() => {
        active.value = null;
    }, cosmeticPlaybackMilliseconds(active.value));
}
watch(
    () => [props.table, props.banishment, props.celebration],
    () => {
        clearTimeout(timer);
        active.value = null;
    },
);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <figure class="cosmetic-preview" :data-table="table ?? 'classic'">
        <div
            class="cosmetic-preview-scene"
            role="img"
            :aria-label="`${tableName}. ${caption}.`"
        >
            <RitualTableScene
                :state="state"
                :seats="seats"
                :table="table"
                :effect="active"
                @unavailable="unavailable = true"
            />
            <div v-if="unavailable" class="cosmetic-preview-fallback">
                <span class="preview-table-emblem" aria-hidden="true">{{
                    table === 'moonlit' ? '☾' : table === 'harvest' ? '❧' : '♛'
                }}</span>
                <strong>{{ tableName }}</strong>
                <span>3D preview is unavailable on this device.</span>
            </div>
        </div>
        <figcaption>
            <strong role="status">{{ caption }}</strong>
            <p v-if="banishment" class="banishment-description">
                <span
                    v-if="active?.kind !== 'banishment'"
                    class="banishment-name"
                    >{{ banishment.name }}. </span
                >{{ banishment.description }}
            </p>
            <ol
                v-if="banishment"
                class="banishment-phases"
                aria-label="Banishment sequence"
            >
                <li v-for="phase in banishment.phases" :key="phase">
                    {{ phase }}
                </li>
            </ol>
            <div class="cosmetic-preview-controls">
                <button
                    v-if="props.banishment"
                    type="button"
                    :disabled="unavailable"
                    @click="play('banishment')"
                >
                    Preview banishment
                </button>
                <button
                    v-if="celebration"
                    type="button"
                    :disabled="unavailable"
                    @click="play('celebration')"
                >
                    Preview victory
                </button>
            </div>
            <small
                >Motion follows your device preference. All effects are
                cosmetic.</small
            >
        </figcaption>
    </figure>
</template>

<style scoped>
.cosmetic-preview {
    margin: 0;
    border: 1px solid #b4a47166;
    background: #182c2c;
    color: #eee3c8;
}
.cosmetic-preview-scene {
    position: relative;
    isolation: isolate;
    min-height: 250px;
    height: clamp(250px, 34vw, 360px);
    overflow: hidden;
}
.cosmetic-preview-scene :deep(.ritual-table-scene) {
    z-index: 0;
}
figcaption {
    border-top: 1px solid #b4a47144;
    padding: 14px 18px;
    display: grid;
    gap: 10px;
}
figcaption strong {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 17px;
}
figcaption small {
    color: #c2c9bb;
}
.banishment-description {
    margin: 0;
    font-size: 13px;
    line-height: 1.65;
    color: #d4d8c9;
    max-width: 62ch;
}
.banishment-name {
    color: #f0dfb2;
    font-weight: 600;
}
.banishment-phases {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 15px;
    margin: 0;
    padding: 0;
    list-style: none;
    counter-reset: phase;
    color: #d9c598;
    font-size: 10px;
    line-height: 1.6;
    letter-spacing: 0.035em;
}
.banishment-phases li {
    counter-increment: phase;
}
.banishment-phases li::before {
    content: '0' counter(phase) ' ';
    opacity: 0.65;
    margin-right: 3px;
}
.cosmetic-preview-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
button {
    color: #f1e8cc;
    border: 1px solid #b4a47188;
    background: #31483d;
    padding: 8px 12px;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
}
button:hover {
    background: #42583e;
}
button:focus-visible {
    outline: 2px solid #f0d08b;
    outline-offset: 3px;
}
button:disabled {
    opacity: 0.5;
    cursor: default;
}
.cosmetic-preview-fallback {
    min-height: 250px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #e3d6b5;
}
.cosmetic-preview-fallback span:last-child {
    font-size: 13px;
}
.preview-table-emblem {
    font-size: 70px;
}
[data-table='moonlit'] {
    border-color: #a3aec45c;
    background: radial-gradient(ellipse at 50% 30%, #54558245, #141c2b 75%);
}
[data-table='moonlit'] figcaption {
    border-color: #a3aec444;
}
[data-table='moonlit'] button {
    background: #323b58;
    color: #e3e2f5;
    border-color: #a4acc888;
}
[data-table='moonlit'] button:hover {
    background: #454c71;
}
[data-table='moonlit'] button:focus-visible {
    outline-color: #d3cafa;
}
[data-table='moonlit'] .cosmetic-preview-fallback {
    color: #d6d7ed;
    background: radial-gradient(ellipse, #353e60, transparent 70%);
}
[data-table='founders_oak'] {
    border-color: #b0935655;
    background: radial-gradient(ellipse at 50% 30%, #47634a45, #18241f 75%);
}
[data-table='founders_oak'] figcaption {
    border-color: #b0935644;
}
[data-table='founders_oak'] button {
    background: #344a37;
    border-color: #b69b5e88;
}
[data-table='founders_oak'] button:hover {
    background: #496044;
}
[data-table='founders_oak'] .cosmetic-preview-fallback {
    color: #e0ce9f;
    background: radial-gradient(ellipse, #414c32, transparent 70%);
}
[data-table='harvest'] {
    border-color: #ae815955;
    background: radial-gradient(ellipse at 50% 30%, #66442d55, #24211d 75%);
}
[data-table='harvest'] figcaption {
    border-color: #ae815944;
}
[data-table='harvest'] button {
    background: #4c352a;
    border-color: #b4895d88;
}
[data-table='harvest'] button:hover {
    background: #624432;
}
</style>
