<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    pending: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const picks = ref<string[]>([]);
const winner = ref<'town' | 'cult'>('town');
const confirming = ref(false);
const failure = ref('');
const living = computed(() =>
    props.state.players.filter((player) => player.alive),
);
const results = computed(
    () =>
        props.state.table?.predictions ?? props.state.recap?.predictions ?? [],
);
const canPredict = computed(
    () =>
        !props.state.me.alive &&
        !['lobby', 'finished'].includes(props.state.phase),
);
function names(ids: string[]) {
    return (
        ids
            .map(
                (id) =>
                    props.state.players.find((player) => player.id === id)
                        ?.name ?? 'A former villager',
            )
            .join(', ') || 'No remaining cultists'
    );
}
watch(
    () => props.state.match_id,
    () => {
        picks.value = [];
        winner.value = 'town';
        confirming.value = false;
        failure.value = '';
    },
);
watch(
    () => living.value.map((player) => player.id).join(','),
    () => {
        picks.value = picks.value.filter((id) =>
            living.value.some((player) => player.id === id),
        );
        confirming.value = false;
    },
);
async function seal() {
    if (!canPredict.value || props.pending || props.state.me.prediction) return;
    failure.value = '';
    if (
        await props.submit('prediction', {
            cultist_ids: picks.value,
            winner: winner.value,
        })
    )
        confirming.value = false;
    else
        failure.value =
            props.error || 'Your prediction was not saved. Please try again.';
}
</script>

<template>
    <section
        v-if="canPredict || (state.phase === 'finished' && results.length)"
        class="game-panel table-experience"
        aria-labelledby="prediction-title"
    >
        <div class="panel-title">
            <h2 id="prediction-title">From beyond the village</h2>
            <span>{{
                state.phase === 'finished'
                    ? 'Predictions revealed'
                    : 'Your private prediction'
            }}</span>
        </div>
        <template v-if="canPredict">
            <p class="table-note">
                Keep following the stories. Predict the cultists still alive and
                the eventual winner. Your choice stays private until the match
                ends and gives you no extra information or rewards.
            </p>
            <p v-if="state.me.alignment === 'cult'" class="table-notice">
                You already know your team. Your prediction will be labelled
                “informed” in the reveal.
            </p>
            <div v-if="state.me.prediction" class="table-notice" role="status">
                <strong>Sealed on day {{ state.me.prediction.day }}</strong>
                <p>
                    Your suspects: {{ names(state.me.prediction.cultist_ids) }}.
                </p>
                <p>
                    Winner:
                    {{
                        state.me.prediction.winner === 'town' ? 'Town' : 'Cult'
                    }}. You can keep watching; this prediction is final.
                </p>
            </div>
            <form v-else class="table-form" @submit.prevent="confirming = true">
                <fieldset :disabled="pending || confirming">
                    <legend>
                        Who among the living is a cultist? Select any number,
                        including none.
                    </legend>
                    <div class="table-options">
                        <label
                            v-for="player in living"
                            :key="player.id"
                            class="table-option"
                            ><input
                                v-model="picks"
                                type="checkbox"
                                :value="player.id"
                            />{{ player.name }}</label
                        >
                    </div>
                </fieldset>
                <fieldset :disabled="pending || confirming">
                    <legend>Who will win?</legend>
                    <div class="table-options">
                        <label class="table-option"
                            ><input
                                v-model="winner"
                                type="radio"
                                name="predicted-winner"
                                value="town"
                            />Town</label
                        ><label class="table-option"
                            ><input
                                v-model="winner"
                                type="radio"
                                name="predicted-winner"
                                value="cult"
                            />Cult</label
                        >
                    </div>
                </fieldset>
                <button v-if="!confirming" class="button" :disabled="pending">
                    Review my prediction
                </button>
                <div v-else class="table-confirm">
                    <p>
                        <strong>Seal this prediction?</strong><br />Cultists:
                        {{ names(picks) }}.<br />Winner:
                        {{ winner === 'town' ? 'Town' : 'Cult' }}.<br />You have
                        one prediction this match. It cannot be changed.
                    </p>
                    <div class="table-actions">
                        <button
                            type="button"
                            class="button primary"
                            :disabled="pending"
                            @click="seal"
                        >
                            {{
                                pending ? 'Sealing…' : 'Seal my prediction'
                            }}</button
                        ><button
                            type="button"
                            class="button"
                            :disabled="pending"
                            @click="confirming = false"
                        >
                            Keep thinking
                        </button>
                    </div>
                </div>
            </form>
        </template>
        <template v-else>
            <p class="table-note">
                Suspects are scored against the cultists alive when each
                prediction was sealed. Informed predictions came from players
                who already knew their cult team.
            </p>
            <ul class="table-record">
                <li v-for="result in results" :key="result.player_id">
                    <div class="table-entry-heading">
                        <strong>{{ names([result.player_id]) }}</strong
                        ><span
                            >Day {{ result.day
                            }}<template v-if="result.informed">
                                · Informed prediction</template
                            ></span
                        >
                    </div>
                    <p class="table-note">
                        Picked: {{ names(result.cultist_ids) }}.
                    </p>
                    <p>
                        {{
                            result.exact
                                ? 'Every remaining cultist, exactly.'
                                : `${result.correct_picks} of ${result.total_cultists} cultists identified · ${result.cultist_ids.length - result.correct_picks} incorrect picks.`
                        }}
                        {{
                            result.winner_correct
                                ? 'Winner predicted correctly'
                                : 'Winner prediction missed'
                        }}
                        ({{ result.winner === 'town' ? 'Town' : 'Cult' }}).
                    </p>
                </li>
            </ul>
        </template>
        <p v-if="failure" class="table-error" role="alert">{{ failure }}</p>
    </section>
</template>
