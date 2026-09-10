<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { LoaderCircle, Undo2 } from '@lucide/vue';
import type { Curse } from '@/lib/chanting';

const props = defineProps<{ curse: Curse; pending: boolean; error?: string }>();
const emit = defineEmits<{
    solve: [payload: { curse_id: string; answer: string[] }];
}>();
const answer = ref<string[]>([]);
const challenge = computed(() => props.curse.challenge);
const complete = computed(
    () =>
        !!challenge.value &&
        answer.value.length === challenge.value.answer_length,
);
const title = computed(() =>
    props.curse.type === 'misdirection'
        ? 'The hand that strays'
        : props.curse.type === 'mist'
          ? 'A mist inside your mind'
          : 'An impossible inscription',
);
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
    if (!props.pending && complete.value)
        emit('solve', { curse_id: props.curse.id, answer: [...answer.value] });
}
function label(id: string) {
    return (
        challenge.value?.options.find((option) => option.id === id)?.label ?? id
    );
}
</script>

<template>
    <section class="game-panel curse-panel" aria-labelledby="curse-title">
        <header class="curse-heading">
            <span class="curse-sigil" aria-hidden="true">⟐</span>
            <div>
                <p class="eyebrow">ELDRITCH CURSE · LEVEL {{ curse.level }}</p>
                <h2 id="curse-title">{{ title }}</h2>
            </div>
        </header>
        <p v-if="curse.type === 'misdirection'" class="curse-effect">
            Your next chosen night or vote target will turn toward another legal
            living player instead. It happens once. Abstaining does not trigger
            it; there is no puzzle to break this curse.
        </p>
        <p v-else-if="curse.type === 'mist'" class="curse-effect">
            The village blurs and chat turns to gibberish. Follow the focus
            sequence below to clear your head and restore the words.
        </p>
        <p v-else class="curse-effect">
            Break this inscription before targeting a player at night or in a
            vote. You can still abstain or take an untargeted action.
        </p>
        <form v-if="challenge" class="curse-challenge" @submit.prevent="solve">
            <h3>{{ challenge.title }}</h3>
            <p id="curse-instruction">{{ challenge.instruction }}</p>
            <ul class="curse-clues" aria-label="Challenge clues">
                <li v-for="(clue, index) in challenge.clues" :key="index">
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
                <ol v-if="answer.length" aria-label="Selected answer in order">
                    <li v-for="(id, index) in answer" :key="index">
                        {{ label(id) }}
                    </li>
                </ol>
                <p v-else>Choose the symbols above in order.</p>
            </div>
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
                    :disabled="pending || !complete"
                    :aria-describedby="error ? 'curse-error' : undefined"
                >
                    <LoaderCircle
                        v-if="pending"
                        :size="15"
                        class="spin"
                        aria-hidden="true"
                    />
                    {{
                        pending
                            ? 'Please wait…'
                            : curse.type === 'mist'
                              ? 'Clear my head'
                              : 'Break the curse'
                    }}
                </button>
            </div>
            <p v-if="error" id="curse-error" class="form-error" role="alert">
                {{ error }}
            </p>
            <p class="curse-hint">
                Choices can be repeated. Undo or clear to change your answer.
            </p>
        </form>
        <p
            v-else-if="curse.type !== 'misdirection'"
            class="curse-effect"
            role="status"
        >
            The inscription is unavailable. Reconnecting to the village will
            restore it.
        </p>
        <p class="curse-expiry">
            Took hold at dawn {{ curse.day }} · Fades at the next dawn.
        </p>
    </section>
</template>
