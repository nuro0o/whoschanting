<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { LoaderCircle, LockKeyhole, Undo2 } from '@lucide/vue';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import type { Curse } from '@/lib/chanting';
import { ringDirection, ringTurn } from '@/lib/cursePuzzleState';
import CursePuzzleScene from './CursePuzzleScene.vue';
import RitualWarning from './RitualWarning.vue';

const props = defineProps<{
    curse: Curse;
    pending: boolean;
    error?: string;
    timeLabel?: string;
    phaseLabel?: string;
    disconnected?: boolean;
    finalVote?: boolean;
}>();
const emit = defineEmits<{
    solve: [payload: { curse_id: string; answer: string[] }];
}>();
const answer = ref<string[]>([]);
const history = ref<string[][]>([]);
const optionalOpen = ref(false);
const introduction = ref<HTMLElement | null>(null);
const challenge = computed(() => props.curse.challenge);
const isRings = computed(() => challenge.value?.scene?.kind === 'rings');
const blocking = computed(() => props.curse.type !== 'misdirection');
const open = computed(() => blocking.value || optionalOpen.value);
const complete = computed(
    () =>
        !!challenge.value &&
        answer.value.length === challenge.value.answer_length,
);
const title = computed(() =>
    challenge.value?.scene
        ? challenge.value.title
        : props.curse.type === 'mist'
          ? 'A mist inside your mind'
          : props.curse.type === 'misdirection'
            ? 'Steady the hand that strays'
            : 'A ritual out of alignment',
);
let previousFocus: HTMLElement | null = null;
watch(
    () => `${props.curse.id}:${JSON.stringify(props.curse.challenge)}`,
    () => {
        answer.value = [];
        history.value = [];
        optionalOpen.value = false;
    },
);
function choose(id: string) {
    if (!props.pending && !complete.value) changeAnswer([...answer.value, id]);
}
function changeAnswer(next: string[]) {
    if (props.pending) return;
    history.value.push([...answer.value]);
    answer.value = next;
}
function undo() {
    if (!props.pending && history.value.length)
        answer.value = history.value.pop()!;
}
function clear() {
    if (props.pending) return;
    answer.value = [];
    history.value = [];
}
function setOpen(value: boolean) {
    if (!blocking.value) optionalOpen.value = value;
}
function preventBlockingDismiss(event: Event) {
    if (blocking.value) event.preventDefault();
}
function solve() {
    if (!props.pending && !props.disconnected && complete.value)
        emit('solve', { curse_id: props.curse.id, answer: [...answer.value] });
}
function label(id: string, index: number) {
    const ring = challenge.value?.scene?.rings?.[index];
    if (ring)
        return `${ring.label}: ${ringDirection(ring.start, ringTurn(ring, id))}`;
    return (
        challenge.value?.options.find((option) => option.id === id)?.label ?? id
    );
}
function rememberFocus(event: Event) {
    previousFocus =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    event.preventDefault();
    introduction.value?.focus({ preventScroll: true });
}
function restoreFocus(event: Event) {
    if (previousFocus?.isConnected) {
        event.preventDefault();
        previousFocus.focus({ preventScroll: true });
    }
}
</script>

<template>
    <section
        v-if="curse.type === 'misdirection'"
        class="game-panel curse-panel"
        aria-labelledby="curse-title"
    >
        <header class="curse-heading">
            <span class="curse-sigil" aria-hidden="true">⟐</span>
            <div>
                <p class="eyebrow">ELDRITCH CURSE · LEVEL {{ curse.level }}</p>
                <h2 id="curse-title">The hand that strays</h2>
            </div>
        </header>
        <p class="curse-effect">
            Your next chosen night or vote target will turn toward another legal
            player instead. The Medium can only be redirected to another
            banished player. It happens once. Abstaining does not trigger it;
            <template v-if="challenge"
                >align the rings first to break this curse.</template
            >
            <template v-else
                >this older curse has no puzzle to break it.</template
            >
        </p>
        <button
            v-if="challenge"
            type="button"
            class="button curse-submit"
            @click="optionalOpen = true"
        >
            Untangle curse
        </button>
        <p class="curse-expiry">
            Took hold at dawn {{ curse.day }} · Fades at the next dawn.
        </p>
    </section>

    <DialogRoot :open="open" :modal="true" @update:open="setOpen">
        <DialogPortal>
            <DialogOverlay class="curse-dialog-overlay" />
            <DialogContent
                class="chanting curse-panel curse-dialog"
                :class="{ 'has-mechanism': challenge?.scene }"
                @escape-key-down="preventBlockingDismiss"
                @interact-outside="preventBlockingDismiss"
                @open-auto-focus="rememberFocus"
                @close-auto-focus="restoreFocus"
            >
                <div class="curse-dialog-context">
                    <span
                        ><LockKeyhole :size="14" aria-hidden="true" />
                        {{ blocking ? 'Cursed' : 'Untangle curse' }}</span
                    >
                    <span v-if="phaseLabel || timeLabel">
                        {{ phaseLabel
                        }}<template v-if="phaseLabel && timeLabel"> · </template
                        >{{ timeLabel }}
                    </span>
                </div>
                <div class="curse-dialog-body">
                    <button
                        v-if="!blocking"
                        type="button"
                        class="curse-back"
                        @click="optionalOpen = false"
                    >
                        ← Back to village
                    </button>
                    <RitualWarning v-if="finalVote" />
                    <header
                        ref="introduction"
                        class="curse-heading"
                        tabindex="-1"
                    >
                        <span class="curse-sigil" aria-hidden="true">⟐</span>
                        <div>
                            <p v-if="!challenge?.scene" class="eyebrow">
                                ELDRITCH CURSE · LEVEL {{ curse.level }}
                            </p>
                            <DialogTitle>{{ title }}</DialogTitle>
                        </div>
                    </header>
                    <DialogDescription
                        v-if="challenge?.scene"
                        class="curse-effect scene-instruction"
                    >
                        {{ challenge.instruction }}
                    </DialogDescription>
                    <DialogDescription v-else class="curse-effect">
                        <template v-if="blocking"
                            >Solve this challenge to return to the
                            room.</template
                        >
                        <template v-else
                            >Align the rings before choosing your next target to
                            prevent it being redirected. You can return to the
                            village and finish later.</template
                        >
                        The game keeps moving while you solve it. This curse
                        fades at the next dawn.
                    </DialogDescription>
                    <p
                        v-if="disconnected"
                        class="curse-connection"
                        role="status"
                    >
                        Reconnecting to the village. You can work on your
                        answer; submit it once the connection returns. The phase
                        timer may be out of date.
                    </p>
                    <form
                        v-if="challenge"
                        class="curse-challenge"
                        @submit.prevent="solve"
                    >
                        <h3 v-if="!challenge.scene">{{ challenge.title }}</h3>
                        <p v-if="!challenge.scene" id="curse-instruction">
                            {{ challenge.instruction }}
                        </p>
                        <ul
                            v-if="!challenge.scene && challenge.clues.length"
                            class="curse-clues"
                            aria-label="Challenge clues"
                        >
                            <li
                                v-for="(clue, index) in challenge.clues"
                                :key="index"
                            >
                                {{ clue }}
                            </li>
                        </ul>
                        <CursePuzzleScene
                            v-if="challenge.scene"
                            :challenge="challenge"
                            :answer="answer"
                            :disabled="pending"
                            @change="changeAnswer"
                        />
                        <div
                            v-else
                            class="curse-options"
                            role="group"
                            aria-label="Answer choices"
                            aria-describedby="curse-instruction"
                        >
                            <button
                                v-for="option in challenge.options"
                                :key="option.id"
                                type="button"
                                :disabled="pending || complete"
                                @click="choose(option.id)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                        <div
                            v-if="!isRings"
                            class="curse-answer"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <span
                                >{{
                                    isRings ? 'Ring positions' : 'Your answer'
                                }}
                                · {{ answer.length }}/{{
                                    challenge.answer_length
                                }}</span
                            >
                            <progress
                                :value="answer.length"
                                :max="challenge.answer_length"
                                aria-label="Answer steps selected"
                            />
                            <ol
                                v-if="answer.length"
                                aria-label="Selected answer in order"
                            >
                                <li v-for="(id, index) in answer" :key="index">
                                    {{ label(id, index) }}
                                </li>
                            </ol>
                            <p v-else>
                                {{
                                    isRings
                                        ? 'Turn a ring to begin aligning the mechanism.'
                                        : 'Choose your answer in the order requested.'
                                }}
                            </p>
                        </div>
                        <p v-if="!challenge.scene" class="curse-hint">
                            <template v-if="!challenge.scene"
                                >Choices can be repeated.
                            </template>
                            {{
                                isRings
                                    ? 'Keep turning until every notch faces north.'
                                    : 'Select the objects in the order requested.'
                            }}
                            Undo or clear to change your answer, then submit
                            when you are ready.
                        </p>
                        <p
                            v-if="error"
                            id="curse-error"
                            class="form-error"
                            role="alert"
                        >
                            {{ error }} You can edit your answer and try again.
                        </p>
                        <div class="curse-controls">
                            <button
                                type="button"
                                class="button"
                                :disabled="pending || !history.length"
                                @click="undo"
                            >
                                <Undo2 :size="15" aria-hidden="true" /> Undo
                            </button>
                            <button
                                type="button"
                                class="button"
                                :disabled="pending || !answer.length"
                                @click="clear"
                            >
                                Clear
                            </button>
                            <button
                                type="submit"
                                class="button curse-submit"
                                :disabled="pending || disconnected || !complete"
                                :aria-describedby="
                                    error ? 'curse-error' : undefined
                                "
                            >
                                <LoaderCircle
                                    v-if="pending"
                                    :size="15"
                                    class="spin"
                                    aria-hidden="true"
                                />
                                {{
                                    pending
                                        ? 'Checking…'
                                        : curse.type === 'mist'
                                          ? 'Clear my head'
                                          : 'Break the curse'
                                }}
                            </button>
                        </div>
                        <details v-if="challenge.scene" class="curse-help">
                            <summary>
                                How to play · Level {{ curse.level }}
                            </summary>
                            <p>
                                {{
                                    blocking
                                        ? 'Solve this mechanism to return to the room. The game keeps moving.'
                                        : 'Solve the rings before choosing your next target to prevent redirection. Return to the village any time; your progress stays here.'
                                }}
                            </p>
                            <ul
                                v-if="challenge.clues.length"
                                class="curse-clues"
                            >
                                <li
                                    v-for="(clue, index) in challenge.clues"
                                    :key="index"
                                >
                                    {{ clue }}
                                </li>
                            </ul>
                            <p>
                                Undo reverses your last move. Clear resets the
                                mechanism. Submit when you are ready.
                            </p>
                        </details>
                    </form>
                    <p v-else class="curse-connection" role="status">
                        Waiting for the inscription. The room will restore it
                        when the connection returns, or release you at the next
                        dawn.
                    </p>
                    <p class="curse-expiry">
                        Took hold at dawn {{ curse.day }} · Fades at the next
                        dawn.
                    </p>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.curse-dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: #071014dc;
    backdrop-filter: blur(7px);
}
.chanting.curse-dialog {
    position: fixed;
    inset: 50% auto auto 50%;
    z-index: 101;
    width: min(640px, calc(100vw - 32px));
    min-height: 0;
    max-height: calc(100dvh - 32px);
    transform: translate(-50%, -50%);
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0;
    border: 1px solid #80658f;
    border-radius: 5px;
    box-shadow: 0 24px 100px #0009;
    scrollbar-color: #80658f #18282b;
}
.curse-dialog-context {
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 6px 16px;
    padding: 11px 24px;
    border-bottom: 1px solid #665371;
    background: #211d2a;
    color: var(--curse);
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}
.curse-dialog-context > span:first-child {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-weight: 600;
}
.curse-dialog-body {
    padding: 24px;
}
.has-mechanism .curse-dialog-body {
    padding: 18px 20px;
}
.has-mechanism .curse-heading {
    gap: 10px;
}
.has-mechanism .curse-heading h2 {
    font-size: 24px;
    line-height: 1.15;
}
.has-mechanism .curse-sigil {
    font-size: 28px;
}
.has-mechanism .scene-instruction {
    margin-top: 10px;
    margin-bottom: 0;
    line-height: 1.5;
}
.has-mechanism .curse-challenge {
    margin-top: 0;
}
.curse-help {
    margin-top: 16px;
    border-top: 1px solid #504357;
    padding-top: 12px;
    color: #c8b6d0;
    font-size: 12px;
}
.curse-help summary {
    cursor: pointer;
    padding-block: 4px;
}
.curse-help p {
    margin-block: 8px;
}
.curse-help summary:focus-visible {
    outline: 2px solid #e6c787;
    outline-offset: 3px;
}
.curse-back {
    margin-bottom: 18px;
    padding: 8px 0;
    color: var(--cream);
    font-size: 13px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.curse-back:focus-visible {
    outline: 2px solid #e6c787;
    outline-offset: 4px;
}
.curse-dialog .curse-challenge > p,
.curse-dialog .curse-clues,
.curse-dialog .curse-effect {
    font-size: 14px;
}
.curse-dialog .curse-connection {
    margin-top: 16px;
    padding: 12px;
    border-left: 2px solid var(--coral);
    background: #2d2527;
    color: var(--cream);
    font-size: 13px;
}
.curse-dialog .curse-answer progress {
    display: block;
    width: 100%;
    height: 4px;
    margin-top: 8px;
    border: 0;
    border-radius: 0;
    background: #3d3444;
    accent-color: var(--curse);
}
.curse-dialog progress::-webkit-progress-bar {
    background: #3d3444;
}
.curse-dialog progress::-webkit-progress-value {
    background: var(--curse);
}
.curse-dialog progress::-moz-progress-bar {
    background: var(--curse);
}
.curse-dialog .curse-controls {
    margin-top: 16px;
}
.curse-dialog .curse-hint {
    margin-top: 0;
    font-size: 12px;
}
@media (max-width: 480px) {
    .chanting.curse-dialog {
        width: calc(100vw - 20px);
        max-height: calc(100dvh - 20px);
    }
    .curse-dialog-body {
        padding: 20px 16px;
    }
    .curse-dialog-context {
        padding-inline: 16px;
    }
    .curse-dialog .curse-clues {
        padding-right: 10px;
    }
    .curse-dialog .curse-controls .curse-submit {
        flex: 1 0 100%;
        justify-content: center;
    }
}
</style>
