<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RotateCcw, RotateCw } from '@lucide/vue';
import type { CurseChallenge } from '@/lib/chanting';
import type { CursePuzzleRenderer } from '@/lib/cursePuzzleRenderer';
import {
    ringDirection,
    ringTurn,
    rotateCurseRing,
    selectCurseObject,
    nextGuidedLantern,
} from '@/lib/cursePuzzleState';

const props = defineProps<{
    challenge: CurseChallenge;
    answer: string[];
    disabled: boolean;
}>();
const emit = defineEmits<{ change: [answer: string[]] }>();
const host = ref<HTMLElement>();
const status = ref<'loading' | 'ready' | 'fallback'>('loading');
const sceneKey = computed(() => JSON.stringify(props.challenge));
const rings = computed(() => props.challenge.scene?.rings ?? []);
const objects = computed(() => props.challenge.scene?.objects ?? []);
const isRings = computed(() => props.challenge.scene?.kind === 'rings');
const nextLantern = computed(() =>
    nextGuidedLantern(props.challenge, props.answer),
);
const feedback = ref('');
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;
const selectedCount = computed(() =>
    isRings.value
        ? rings.value.filter(
              (ring, i) =>
                  ringDirection(ring.start, ringTurn(ring, props.answer[i])) ===
                  'North',
          ).length
        : props.answer.length,
);
let renderer: CursePuzzleRenderer | undefined;
let mounted = false;
let generation = 0;
function choose(id: string | number) {
    if (props.disabled) return;
    const next =
        typeof id === 'number'
            ? rotateCurseRing(props.challenge, props.answer, id)
            : selectCurseObject(props.challenge, props.answer, id);
    clearTimeout(feedbackTimer);
    if (next !== props.answer) {
        feedback.value = '';
        emit('change', next);
    } else if (
        typeof id === 'string' &&
        nextLantern.value &&
        !props.answer.includes(id)
    ) {
        feedback.value = `Look for lantern ${nextLantern.value.label}`;
        feedbackTimer = setTimeout(() => {
            feedback.value = '';
        }, 2200);
    }
}
function stop() {
    generation++;
    renderer?.dispose();
    renderer = undefined;
}
async function start() {
    stop();
    if (!mounted || !host.value) return;
    const current = generation;
    status.value = 'loading';
    feedback.value = '';
    clearTimeout(feedbackTimer);
    try {
        const { createCursePuzzle } = await import('@/lib/cursePuzzleRenderer');
        if (!mounted || current !== generation || !host.value) return;
        renderer = createCursePuzzle(
            host.value,
            props.challenge,
            props.answer,
            props.disabled,
            choose,
            () => {
                if (!mounted || current !== generation) return;
                stop();
                status.value = 'fallback';
            },
        );
        status.value = 'ready';
    } catch {
        if (!mounted || current !== generation) return;
        stop();
        status.value = 'fallback';
    }
}
function height(id: string) {
    return objects.value.find((object) => object.option_id === id)?.height;
}
watch(sceneKey, start);
watch(
    () => [props.answer, props.disabled],
    () => {
        feedback.value = '';
        clearTimeout(feedbackTimer);
        renderer?.update(props.answer, props.disabled);
    },
    { deep: true },
);
onMounted(() => {
    mounted = true;
    void start();
});
onBeforeUnmount(() => {
    mounted = false;
    stop();
    clearTimeout(feedbackTimer);
});
</script>

<template>
    <section class="puzzle-mechanism" aria-label="Interactive curse mechanism">
        <div class="mechanism-status">
            <span>{{
                isRings
                    ? 'THE ALIGNMENT'
                    : challenge.scene?.kind === 'towers'
                      ? 'THE STONE CHOIR'
                      : 'THE LOST LIGHTS'
            }}</span>
            <span role="status" aria-live="polite"
                >{{ selectedCount }} / {{ challenge.answer_length }}
                {{ isRings ? 'aligned' : 'lit' }}</span
            >
        </div>
        <div
            class="mechanism-stage"
            :class="{ 'is-fallback': status === 'fallback' }"
        >
            <div ref="host" class="mechanism-canvas" />
            <p
                v-if="status !== 'ready'"
                class="mechanism-message"
                role="status"
            >
                {{
                    status === 'loading'
                        ? 'Raising the ritual stones… Controls below are ready.'
                        : 'The 3D view is unavailable. Solve the same mechanism using the controls below.'
                }}
            </p>
            <button
                v-if="status === 'ready' && !isRings"
                type="button"
                class="mechanism-reset"
                @click="renderer?.resetView()"
            >
                <RotateCcw :size="13" aria-hidden="true" /> Reset view
            </button>
        </div>
        <p class="mechanism-guidance">
            {{
                isRings
                    ? 'Tap a ring or use its turn button below.'
                    : challenge.scene?.kind === 'lanterns'
                      ? 'Follow the numbers, not the heights · Drag to look around'
                      : 'Tap an object to light it · Drag to look around'
            }}
        </p>
        <p
            v-if="
                challenge.scene?.kind === 'lanterns' && challenge.scene.guided
            "
            class="mechanism-next"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            {{
                feedback ||
                (nextLantern
                    ? `Next lantern: ${nextLantern.label}`
                    : 'All lanterns lit. Your seal is ready.')
            }}
        </p>
        <div
            v-if="isRings"
            class="mechanism-rings"
            role="group"
            aria-label="Ring controls; north is the target"
        >
            <button
                v-for="(ring, index) in rings"
                :key="index"
                type="button"
                :disabled="disabled"
                :aria-label="`${ring.label}, facing ${ringDirection(ring.start, ringTurn(ring, answer[index]))}. Rotate clockwise a quarter turn`"
                @click="choose(index)"
            >
                <span class="ring-number">{{ index + 1 }}</span>
                <span class="ring-name"
                    >{{ ring.label
                    }}<small>{{
                        ringDirection(ring.start, ringTurn(ring, answer[index]))
                    }}</small></span
                >
                <RotateCw :size="16" aria-hidden="true" />
            </button>
        </div>
        <div
            v-else
            class="mechanism-choices"
            role="group"
            aria-label="Puzzle controls; select in order"
        >
            <button
                v-for="option in challenge.options"
                :key="option.id"
                type="button"
                :disabled="
                    disabled ||
                    answer.includes(option.id) ||
                    answer.length >= challenge.answer_length
                "
                :class="{ 'is-selected': answer.includes(option.id) }"
                :aria-pressed="answer.includes(option.id)"
                @click="choose(option.id)"
            >
                <span>{{ option.label }}</span>
                <small v-if="challenge.scene?.kind === 'towers'"
                    >Height {{ height(option.id) }}</small
                >
                <small v-if="answer.includes(option.id)"
                    >Lit {{ answer.indexOf(option.id) + 1 }}</small
                >
            </button>
        </div>
    </section>
</template>

<style scoped>
.puzzle-mechanism {
    margin-block: 12px;
    border: 1px solid #65536e;
    background: #132127;
}
.mechanism-status {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 1px solid #504357;
    color: #d9c8e0;
    font-size: 10px;
    letter-spacing: 0.12em;
}
.mechanism-next {
    margin: 0;
    padding: 0 14px 12px;
    color: #a8e1cd;
    font-size: 13px;
    font-weight: 600;
}
.mechanism-status span:last-child {
    color: #9fdac9;
    letter-spacing: 0.04em;
}
.mechanism-stage {
    position: relative;
    height: clamp(235px, 45vw, 350px);
}
.mechanism-stage.is-fallback {
    height: 96px;
}
.mechanism-canvas {
    position: absolute;
    inset: 0;
}
.mechanism-canvas :deep(canvas) {
    display: block;
    width: 100%;
    height: 100%;
    touch-action: none;
    cursor: grab;
}
.mechanism-canvas :deep(canvas:active) {
    cursor: grabbing;
}
.mechanism-message {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    margin: 0;
    padding: 24px;
    text-align: center;
    color: #d3c5d9;
    font-size: 13px;
    pointer-events: none;
}
.mechanism-reset {
    position: absolute;
    right: 10px;
    bottom: 10px;
    display: inline-flex;
    gap: 5px;
    align-items: center;
    padding: 7px 10px;
    border: 1px solid #6b6276;
    background: #142128e8;
    color: #e8dfce;
    font-size: 11px;
}
.mechanism-guidance {
    margin: 0;
    padding: 12px 14px;
    border-top: 1px solid #504357;
    color: #d3cbd6;
    font-size: 12px;
    line-height: 1.6;
}
.mechanism-rings,
.mechanism-choices {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    padding: 0 12px 12px;
}
.mechanism-rings button,
.mechanism-choices button {
    display: flex;
    gap: 9px;
    align-items: center;
    min-height: 48px;
    padding: 8px 10px;
    border: 1px solid #766480;
    background: #302b39;
    color: #f0e5d0;
    text-align: left;
    cursor: pointer;
}
.ring-number {
    display: grid;
    place-items: center;
    width: 23px;
    height: 23px;
    flex: 0 0 auto;
    border: 1px solid #aa8e60;
    color: #e5c17e;
    font-size: 12px;
}
.ring-name {
    flex: 1;
    font-size: 12px;
}
.ring-name small,
.mechanism-choices small {
    display: block;
    color: #c8b6d0;
    font-size: 11px;
}
.mechanism-choices button {
    flex-wrap: wrap;
    justify-content: space-between;
    font-size: 13px;
}
.mechanism-choices button.is-selected {
    border-color: #69b19d;
    background: #25463e;
    color: #d8f3de;
    opacity: 1;
}
.mechanism-choices button.is-selected small {
    color: #b7e6d9;
}
button:hover:not(:disabled) {
    background: #443a4f;
    border-color: #c3a4d2;
}
button:focus-visible {
    outline: 2px solid #e6c787;
    outline-offset: 3px;
}
button:disabled {
    cursor: default;
    opacity: 0.55;
}
@media (max-width: 380px) {
    .mechanism-rings {
        grid-template-columns: 1fr;
    }
    .mechanism-status {
        letter-spacing: 0.05em;
    }
}
</style>
