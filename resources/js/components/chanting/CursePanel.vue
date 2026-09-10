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

const props = defineProps<{
    curse: Curse;
    pending: boolean;
    error?: string;
    timeLabel?: string;
    phaseLabel?: string;
    disconnected?: boolean;
}>();
const emit = defineEmits<{
    solve: [payload: { curse_id: string; answer: string[] }];
}>();
const answer = ref<string[]>([]);
const introduction = ref<HTMLElement | null>(null);
const challenge = computed(() => props.curse.challenge);
const complete = computed(
    () =>
        !!challenge.value &&
        answer.value.length === challenge.value.answer_length,
);
const title = computed(() =>
    props.curse.type === 'mist'
        ? 'A mist inside your mind'
        : 'An impossible inscription',
);
let previousFocus: HTMLElement | null = null;
watch(
    () => props.curse.id,
    () => {
        answer.value = [];
    },
);
function choose(id: string) {
    if (!props.pending && !complete.value) answer.value.push(id);
}
function solve() {
    if (!props.pending && !props.disconnected && complete.value)
        emit('solve', { curse_id: props.curse.id, answer: [...answer.value] });
}
function label(id: string) {
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
            living player instead. It happens once. Abstaining does not trigger
            it; there is no puzzle to break this curse.
        </p>
        <p class="curse-expiry">
            Took hold at dawn {{ curse.day }} · Fades at the next dawn.
        </p>
    </section>

    <DialogRoot v-else :open="true" :modal="true">
        <DialogPortal>
            <DialogOverlay class="curse-dialog-overlay" />
            <DialogContent
                class="chanting curse-panel curse-dialog"
                @escape-key-down.prevent
                @interact-outside.prevent
                @open-auto-focus="rememberFocus"
                @close-auto-focus="restoreFocus"
            >
                <div class="curse-dialog-context">
                    <span
                        ><LockKeyhole :size="14" aria-hidden="true" />
                        Cursed</span
                    >
                    <span v-if="phaseLabel || timeLabel">
                        {{ phaseLabel
                        }}<template v-if="phaseLabel && timeLabel"> · </template
                        >{{ timeLabel }}
                    </span>
                </div>
                <div class="curse-dialog-body">
                    <header
                        ref="introduction"
                        class="curse-heading"
                        tabindex="-1"
                    >
                        <span class="curse-sigil" aria-hidden="true">⟐</span>
                        <div>
                            <p class="eyebrow">
                                ELDRITCH CURSE · LEVEL {{ curse.level }}
                            </p>
                            <DialogTitle>{{ title }}</DialogTitle>
                        </div>
                    </header>
                    <DialogDescription class="curse-effect">
                        Solve this challenge to return to the room. The game
                        keeps moving while you solve it. This curse fades at the
                        next dawn.
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
                        <h3>{{ challenge.title }}</h3>
                        <p id="curse-instruction">
                            {{ challenge.instruction }}
                        </p>
                        <ul class="curse-clues" aria-label="Challenge clues">
                            <li
                                v-for="(clue, index) in challenge.clues"
                                :key="index"
                            >
                                {{ clue }}
                            </li>
                        </ul>
                        <div
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
                            class="curse-answer"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <span
                                >Your answer · {{ answer.length }}/{{
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
                                    {{ label(id) }}
                                </li>
                            </ol>
                            <p v-else>
                                Choose your answer in the order requested.
                            </p>
                        </div>
                        <p class="curse-hint">
                            Choices can be repeated. Undo or clear to change
                            your answer, then submit when you are ready.
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
                                :disabled="pending || !answer.length"
                                @click="answer.pop()"
                            >
                                <Undo2 :size="15" aria-hidden="true" /> Undo
                            </button>
                            <button
                                type="button"
                                class="button"
                                :disabled="pending || !answer.length"
                                @click="answer = []"
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
