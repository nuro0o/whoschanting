<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';

const props = defineProps<{
    state: RoomState;
    disabled: boolean;
    countdown: string;
    submit: (type: string, extra?: Record<string, unknown>) => Promise<boolean>;
}>();
const target = ref('');
const body = ref('');
const record = computed(() => props.state.table?.last_words);
const accusation = computed(() =>
    record.value?.accusations.find(
        (entry) => entry.player_id === props.state.me.id,
    ),
);
const defense = computed(() =>
    record.value?.defenses.find(
        (entry) => entry.player_id === props.state.me.id,
    ),
);
const accused = computed(() =>
    record.value?.accused_ids.includes(props.state.me.id),
);
const targets = computed(() =>
    props.state.players.filter(
        (player) => player.alive && player.id !== props.state.me.id,
    ),
);
const name = (id: string) =>
    props.state.players.find((player) => player.id === id)?.name ??
    'A villager';
watch(
    [
        () => props.state.match_id,
        () => props.state.day,
        () => props.state.me.id,
        () => props.state.phase,
    ],
    () => {
        target.value = '';
        body.value = '';
    },
);
async function accuse() {
    if (props.disabled || !target.value) return;
    await props.submit('accuse', { target: target.value });
}
async function defend() {
    if (props.disabled || !body.value.trim()) return;
    if (await props.submit('defend', { body: body.value.trim() }))
        body.value = '';
}
</script>

<template>
    <section
        v-if="
            record &&
            ['discussion', 'last_words', 'voting'].includes(state.phase)
        "
        id="last-words-panel"
        tabindex="-1"
        class="last-words"
        aria-labelledby="last-words-heading"
    >
        <p class="eyebrow">BEFORE THE VERDICT</p>
        <p
            v-if="state.phase === 'last_words'"
            class="last-words-timer"
            role="timer"
            aria-label="Time remaining for Last Words"
        >
            {{ countdown }}
        </p>
        <h3 id="last-words-heading">
            {{
                state.phase === 'discussion'
                    ? 'Who should answer to the village?'
                    : 'Last Words'
            }}
        </h3>
        <p v-if="state.phase === 'discussion'">
            You may publicly accuse one other living player. The most accused
            players, including ties, share
            {{ state.rules.seconds.last_words }} seconds to defend themselves
            before voting. No accusations means voting opens directly.
        </p>
        <p v-else-if="state.phase === 'last_words'">
            {{ record.accused_ids.map(name).join(', ') }} may each publish one
            defense. Chat pauses while the village listens. Voting opens when
            the timer ends.
        </p>
        <p v-else>
            Read the defenses before deciding. You can still vote for any other
            living player or abstain.
        </p>

        <form
            v-if="state.phase === 'discussion' && state.me.alive && !accusation"
            @submit.prevent="accuse"
        >
            <label for="accusation-target">Accuse a player (optional)</label>
            <select
                id="accusation-target"
                v-model="target"
                :disabled="disabled"
                required
            >
                <option value="" disabled>Choose a suspect</option>
                <option
                    v-for="player in targets"
                    :key="player.id"
                    :value="player.id"
                >
                    {{ player.name }}
                </option>
            </select>
            <p class="last-words-note">
                Your accusation is public and final for this round. It does not
                cast your vote or mark you ready.
            </p>
            <button class="button" :disabled="disabled || !target">
                Confirm accusation
            </button>
        </form>
        <p v-if="accusation" role="status">
            You accused {{ name(accusation.target_id) }}. Your ballot is still
            yours to choose.
        </p>
        <ul
            v-if="record.accusations.length"
            class="last-words-accusations"
            aria-label="Public accusations"
        >
            <li v-for="entry in record.accusations" :key="entry.player_id">
                <strong>{{ name(entry.player_id) }}</strong> accused
                {{ name(entry.target_id) }}.
            </li>
        </ul>

        <form
            v-if="
                state.phase === 'last_words' &&
                state.me.alive &&
                accused &&
                !defense
            "
            @submit.prevent="defend"
        >
            <label for="last-words-defense">Your final defense</label>
            <textarea
                id="last-words-defense"
                v-model="body"
                maxlength="280"
                rows="3"
                :disabled="disabled"
                required
                placeholder="Tell the village why they should trust you…"
            />
            <p class="last-words-note">
                {{ body.length }}/280 · Public and final once sent. You may also
                remain silent.
            </p>
            <button class="button primary" :disabled="disabled || !body.trim()">
                Publish Last Words
            </button>
        </form>
        <p v-if="state.phase === 'last_words' && !accused">
            Listen to the accused before casting your vote.
        </p>
        <div
            v-if="record.accused_ids.length"
            class="last-words-defenses"
            role="region"
            aria-live="polite"
            aria-label="Final defenses"
        >
            <article v-for="id in record.accused_ids" :key="id">
                <h4>{{ name(id) }}</h4>
                <p>
                    {{
                        record.defenses.find((entry) => entry.player_id === id)
                            ?.body ??
                        (state.phase === 'last_words'
                            ? 'Waiting for their Last Words…'
                            : 'No defense was submitted.')
                    }}
                </p>
            </article>
        </div>
    </section>
</template>

<style scoped>
.last-words {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #827251;
    border-radius: 4px;
    background: #192824;
    color: #eee8d9;
}
.last-words-timer {
    font-variant-numeric: tabular-nums;
    font-size: 20px;
    font-weight: 600;
}
h3 {
    margin: 6px 0 12px;
    font-size: 22px;
}
p,
li {
    line-height: 1.6;
    overflow-wrap: anywhere;
}
form {
    display: grid;
    gap: 10px;
    margin-block: 18px;
}
label {
    font-weight: 600;
}
select,
textarea {
    width: 100%;
    min-width: 0;
    padding: 12px;
    color: #eee8d9;
    background: #10201c;
    border: 1px solid #7a886e;
    border-radius: 4px;
    font: inherit;
}
textarea {
    resize: vertical;
}
.button {
    justify-self: start;
    min-height: 44px;
}
.last-words-note {
    margin: 0;
    color: #c5cbbb;
    font-size: 13px;
}
.last-words-accusations {
    padding-left: 20px;
}
.last-words-defenses {
    display: grid;
    gap: 12px;
    margin-top: 16px;
}
article {
    border-left: 3px solid #c5ab75;
    padding: 10px 14px;
    background: #ffffff06;
}
h4,
article p {
    margin: 0;
}
article p {
    margin-top: 6px;
    white-space: pre-wrap;
}
</style>
