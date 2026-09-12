<script setup lang="ts">
import { ChevronDown, Eye, Moon, Vote } from '@lucide/vue';
import { computed } from 'vue';
import { chaosEvents, modeName } from '@/lib/gameModes';
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
function redirection(action: {
    chosen_target_id?: string | null;
    target_id: string | null;
}) {
    return action.chosen_target_id &&
        action.chosen_target_id !== action.target_id
        ? `Originally chose ${name(action.chosen_target_id)}. Misdirection changed the target to ${name(action.target_id)}.`
        : '';
}
function actionDescription(action: RecapNightAction) {
    if (!action.submitted) return 'Missed the night deadline. No action taken.';
    if (action.disrupted)
        return `Night action disrupted. No ability or chant took effect.${action.used_ability ? ' The once-per-match ability was spent.' : ''}`;
    if (action.role === 'vigilante')
        return action.shot_fired
            ? `Shot ${name(action.target_id)}.${action.guilty ? ' The target was not a cultist, triggering guilt and departure if the Vigilante survived the night.' : ' The target was a cultist.'}`
            : 'Kept watch and saved any remaining shot.';
    if (action.role === 'tracker')
        return `Tracked ${name(action.target_id)}. ${action.tracked_target_id ? `Seen targeting ${name(action.tracked_target_id)}.` : 'No outgoing visit was visible.'}`;
    if (action.role === 'herbalist')
        return action.used_ability
            ? 'Protected the village from all new curses. The ability was spent.'
            : 'Kept watch and saved any remaining ability.';
    if (action.role === 'phantasm' && action.used_ability)
        return `Haunted ${name(action.target_id)} and concealed their outgoing visits instead of chanting.`;
    if (action.role === 'counterfeiter' && action.used_ability)
        return `Forged ${name(action.target_id)} as ${action.forged_alignment} to the Oracle instead of chanting.`;
    if (action.role === 'medium')
        return action.used_ability
            ? `Contacted ${name(action.target_id)}. True alignment: ${action.true_alignment}.`
            : 'Kept watch and saved any remaining ability.';
    if (action.role === 'bellkeeper')
        return action.used_ability
            ? `Rang the bell. Prevented ${action.prevented_steps ?? 0} ritual ${action.prevented_steps === 1 ? 'step' : 'steps'}. The ability was spent.`
            : 'Kept watch and saved any remaining ability.';
    if (action.role === 'dreamweaver' && action.used_ability)
        return `Sent a disruption to ${name(action.target_id)} instead of chanting. The ability was spent.`;
    if (action.role === 'oracle') {
        return `Investigated ${name(action.target_id)}. Read as ${action.apparent_alignment === 'cult' ? 'cult' : 'town'}.`;
    }
    if (action.role === 'warden') {
        if (!action.target_id) return 'Skipped protection.';
        return `Protected ${name(action.target_id)} from new curses. ${action.prevented_curse ? 'Blocked a curse attempt.' : 'No curse was attempted on that player.'}`;
    }
    if (action.role === 'lamplighter') {
        return `Watched ${name(action.target_id)}. ${action.visited ? 'At least one other player was seen targeting them.' : 'No other player was seen targeting them.'}`;
    }
    if (
        [
            'veilweaver',
            'acolyte',
            'dreamweaver',
            'phantasm',
            'counterfeiter',
        ].includes(action.role)
    ) {
        const chant = action.contributed
            ? 'Chanted. Added 1 ritual step.'
            : action.ritual_blocked
              ? 'Chanted, but the Bellkeeper prevented this ritual step.'
              : 'Chanted, but the mission condition was not met. No ritual step.';
        const veil =
            action.role === 'veilweaver'
                ? action.target_id
                    ? `Veiled ${name(action.target_id)}.`
                    : 'Placed no veil.'
                : '';
        const curse =
            action.curse_type && action.target_id
                ? `Cursed ${name(action.target_id)} with ${{ puzzle: 'Soul Bind', mist: 'Mind Mist', misdirection: 'Misdirection' }[action.curse_type]}.`
                : action.curse_blocked
                  ? `Tried to curse ${name(action.target_id)}, but protection blocked it.`
                  : '';
        return [chant, veil, curse].filter(Boolean).join(' ');
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
                Mode: {{ modeName(recap.mode_setup, recap.roster) }}.
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
                    <p v-if="round.night.chaos_event" class="recap-veil">
                        {{ chaosEvents[round.night.chaos_event].name }}:
                        {{ chaosEvents[round.night.chaos_event].description }}
                    </p>
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
                            <p v-if="redirection(action)" class="recap-veil">
                                {{ redirection(action) }}
                            </p>
                            <p
                                v-if="
                                    action.role === 'oracle' &&
                                    action.submitted &&
                                    action.veiled &&
                                    !action.forged
                                "
                                class="recap-veil"
                            >
                                <Eye :size="14" aria-hidden="true" />The veil
                                reversed this reading.
                            </p>
                            <p v-if="action.forged" class="recap-veil">
                                The Counterfeiter planted this reading,
                                overriding any veil.
                            </p>
                            <p v-if="action.visits_hidden" class="recap-veil">
                                The Phantasm hid this player’s outgoing visits
                                from observers.
                            </p>
                        </li>
                    </ul>
                </section>
                <section v-if="round.discussion?.length">
                    <h3>Discussion abilities</h3>
                    <p v-for="(action, index) in round.discussion" :key="index">
                        {{ name(action.player_id) }}
                        {{
                            action.type === 'exorcise'
                                ? 'cleansed'
                                : 'publicly promised to vote for'
                        }}
                        {{ name(action.target_id) }}.
                    </p>
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
                            ><span
                                >{{
                                    !ballot.submitted
                                        ? 'Missed the deadline · counted as abstention'
                                        : ballot.target_id
                                          ? `Voted for ${name(ballot.target_id)}`
                                          : 'Chose to abstain'
                                }}<template v-if="redirection(ballot)"
                                    >. {{ redirection(ballot) }}</template
                                ><template v-if="ballot.oath_kept != null"
                                    >.
                                    {{
                                        ballot.oath_kept
                                            ? 'Oath kept; protection earned for the next night.'
                                            : 'Oath broken; no protection earned.'
                                    }}</template
                                ></span
                            >
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
