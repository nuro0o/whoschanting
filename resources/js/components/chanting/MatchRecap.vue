<script setup lang="ts">
import { ChevronDown, Eye, Moon, Vote } from '@lucide/vue';
import { computed } from 'vue';
import {
    roles,
    type MatchRecapData,
    type Player,
    type RecapNightAction,
} from '@/lib/chanting';

const props = defineProps<{ recap: MatchRecapData; players: Player[] }>();
const names = computed(
    () => new Map(props.players.map((player) => [player.id, player.name])),
);
const rounds = computed(() =>
    [...props.recap.rounds].sort((a, b) => a.day - b.day),
);
function name(id: string | null) {
    return id ? (names.value.get(id) ?? 'A former villager') : 'Nobody';
}
function actionDescription(action: RecapNightAction) {
    if (!action.submitted) return 'Missed the night deadline. No action taken.';
    if (action.role === 'oracle') {
        return `Investigated ${name(action.target_id)}. Read as ${action.apparent_alignment === 'cult' ? 'cult' : 'town'}.`;
    }
    if (action.role === 'veilweaver' || action.role === 'acolyte') {
        const chant = action.contributed
            ? 'Chanted. Added 1 ritual step.'
            : 'Chanted, but the mission condition was not met. No ritual step.';
        return action.role === 'veilweaver'
            ? `${chant} ${action.target_id ? `Veiled ${name(action.target_id)}.` : 'Placed no veil.'}`
            : chant;
    }
    return 'Kept watch.';
}
</script>

<template>
    <section class="game-panel match-recap" aria-labelledby="recap-title">
        <div class="panel-title">
            <h2 id="recap-title">What really happened</h2>
            <span>Every secret, revealed</span>
        </div>
        <div class="recap-intro">
            <dl class="recap-stats">
                <div>
                    <dt>Players</dt>
                    <dd>{{ recap.player_count }}</dd>
                </div>
                <div>
                    <dt>Nights</dt>
                    <dd>{{ recap.nights }}</dd>
                </div>
                <div>
                    <dt>Ritual goal</dt>
                    <dd>{{ recap.ritual_goal }} steps</dd>
                </div>
            </dl>
            <p class="recap-mission">
                <strong>The cult’s mission: {{ recap.mission.name }}</strong
                >{{ recap.mission.description }}
            </p>
            <p v-if="!recap.complete" class="recap-note">
                This match began before recaps were added. Only actions recorded
                after the update are available.
            </p>
            <p v-if="!rounds.length" class="recap-note">
                No completed nights or votes were recorded for this match.
            </p>
            <p v-else class="recap-hint">
                Open a round to compare the stories with the secrets.
            </p>
        </div>
        <details v-for="round in rounds" :key="round.day" class="recap-round">
            <summary>
                <span class="recap-round-name">Round {{ round.day }}</span>
                <span class="recap-round-result">
                    <template v-if="round.night"
                        >+{{ round.night.gained }} ritual
                        {{
                            round.night.gained === 1 ? 'step' : 'steps'
                        }}</template
                    >
                    <template v-if="round.vote"
                        ><span v-if="round.night"> · </span
                        >{{
                            round.vote.banished_id
                                ? `${name(round.vote.banished_id)} banished`
                                : 'Nobody banished'
                        }}</template
                    >
                </span>
                <ChevronDown
                    :size="16"
                    class="recap-chevron"
                    aria-hidden="true"
                />
            </summary>
            <div class="recap-round-body">
                <section
                    v-if="round.night"
                    :aria-labelledby="`recap-night-${round.day}`"
                >
                    <h3 :id="`recap-night-${round.day}`">
                        <Moon :size="15" aria-hidden="true" />Night
                        {{ round.day
                        }}<span
                            >{{ round.night.tokens }} /
                            {{ recap.ritual_goal }} steps</span
                        >
                    </h3>
                    <ul class="recap-actions">
                        <li
                            v-for="action in round.night.actions"
                            :key="action.player_id"
                        >
                            <div class="recap-actor">
                                <strong>{{ name(action.player_id) }}</strong
                                ><span>{{
                                    roles[action.role]?.name ?? action.role
                                }}</span>
                            </div>
                            <p>{{ actionDescription(action) }}</p>
                            <p
                                v-if="
                                    action.role === 'oracle' &&
                                    action.submitted &&
                                    action.veiled
                                "
                                class="recap-veil"
                            >
                                <Eye :size="14" aria-hidden="true" />The veil
                                reversed this reading.
                            </p>
                        </li>
                    </ul>
                </section>
                <section
                    v-if="round.vote"
                    :aria-labelledby="`recap-vote-${round.day}`"
                >
                    <h3 :id="`recap-vote-${round.day}`">
                        <Vote :size="15" aria-hidden="true" />Day
                        {{ round.day }} vote
                    </h3>
                    <ul class="recap-ballots">
                        <li
                            v-for="ballot in round.vote.ballots"
                            :key="ballot.player_id"
                        >
                            <strong>{{ name(ballot.player_id) }}</strong
                            ><span>{{
                                !ballot.submitted
                                    ? 'Missed the deadline · counted as abstention'
                                    : ballot.target_id
                                      ? `Voted for ${name(ballot.target_id)}`
                                      : 'Chose to abstain'
                            }}</span>
                        </li>
                    </ul>
                    <p class="recap-verdict">
                        {{
                            round.vote.banished_id
                                ? `${name(round.vote.banished_id)} was banished.`
                                : 'Nobody was banished.'
                        }}
                    </p>
                </section>
            </div>
        </details>
    </section>
</template>
