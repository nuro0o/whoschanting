<script setup lang="ts">
import { ref, watch } from 'vue';
import type { MatchFeedbackResponse, RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    pending: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const engagement = ref<MatchFeedbackResponse['engagement'] | ''>('');
const body = ref('');
const failure = ref('');
const choices = [
    { value: 'engaged', label: 'Involved throughout' },
    { value: 'mixed', label: 'A mix of both' },
    { value: 'waiting', label: 'Mostly waiting' },
];
watch(
    () => props.state.match_id,
    () => {
        engagement.value = '';
        body.value = '';
        failure.value = '';
    },
);
async function save() {
    if (!engagement.value || props.pending || props.state.me.feedback) return;
    failure.value = '';
    if (
        !(await props.submit('feedback', {
            engagement: engagement.value,
            body: body.value.trim(),
        }))
    )
        failure.value =
            props.error || 'Your feedback was not saved. Please try again.';
}
</script>

<template>
    <section
        class="game-panel table-experience"
        aria-labelledby="match-feedback-title"
    >
        <div class="panel-title">
            <h2 id="match-feedback-title">How involved did you feel?</h2>
            <span>Optional playtest feedback</span>
        </div>
        <p v-if="state.me.feedback" class="table-notice" role="status">
            Thank you. Your feedback is saved privately for this match.
        </p>
        <form v-else class="table-form" @submit.prevent="save">
            <p class="table-note">
                Help us understand whether every role has enough to do. Your
                answer is private, can be sent once, and gives no rewards.
            </p>
            <fieldset :disabled="pending">
                <legend>Your experience this match</legend>
                <div class="table-options">
                    <label
                        v-for="choice in choices"
                        :key="choice.value"
                        class="table-option"
                        ><input
                            v-model="engagement"
                            type="radio"
                            name="match-engagement"
                            :value="choice.value"
                            required
                        />{{ choice.label }}</label
                    >
                </div>
            </fieldset>
            <label for="engagement-note"
                >What made you feel that way?
                <span class="table-note">Optional · 280 characters</span
                ><textarea
                    id="engagement-note"
                    v-model="body"
                    rows="2"
                    maxlength="280"
                    :disabled="pending"
                />
            </label>
            <div class="table-actions">
                <button class="button" :disabled="pending || !engagement">
                    {{ pending ? 'Saving…' : 'Send private feedback' }}
                </button>
            </div>
        </form>
        <p v-if="failure" class="table-error" role="alert">{{ failure }}</p>
    </section>
</template>
