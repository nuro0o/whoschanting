<script setup lang="ts">
import {
    ArrowRight,
    ChevronDown,
    Eye,
    MessageCircle,
    Moon,
    Vote,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { buildRecapStory } from '@/lib/recapStory';
import { chaosEvents, modeName } from '@/lib/gameModes';
import {
    roles,
    type MatchRecapData,
    type Player,
    type RecapNightAction,
} from '@/lib/chanting';
import RecapVillager from './RecapVillager.vue';
import { recapHighlights } from '@/lib/recapHighlights';

const props = defineProps<{
    recap: MatchRecapData;
    players: Player[];
    matchId?: string | null;
}>();
const story = computed(() => buildRecapStory(props.recap, props.players));
const stepIndex = ref(0);
const currentStep = computed(
    () => story.value[Math.min(stepIndex.value, story.value.length - 1)],
);
watch(
    () => props.matchId,
    () => {
        stepIndex.value = 0;
    },
);
const highlights = computed(() => recapHighlights(props.recap, props.players));
const names = computed(
    () => new Map(props.players.map((player) => [player.id, player.name])),
);
const rounds = computed(() =>
    [...props.recap.rounds].sort((a, b) => a.day - b.day),
);
const villagers = computed(
    () => new Map(props.players.map((player) => [player.id, player])),
);
type RecapVote = NonNullable<MatchRecapData['rounds'][number]['vote']>;
function tally(vote: RecapVote) {
    const counts = new Map<string | null, number>();
    for (const ballot of vote.ballots) {
        const target = ballot.submitted ? ballot.target_id : null;
        counts.set(target, (counts.get(target) ?? 0) + 1);
    }
    return [...counts]
        .map(([id, count]) => ({ id, count }))
        .sort((a, b) => b.count - a.count);
}
function verdict(vote: RecapVote) {
    if (vote.banished_id) return `${name(vote.banished_id)} was banished.`;
    const results = tally(vote);
    if (!results.length)
        return 'Nobody was banished. Ballot details were not recorded.';
    const leaders = results.filter(
        (result) => result.count === results[0].count,
    );
    if (leaders.length > 1)
        return `Nobody was banished. The lead was tied between ${leaders.map((result) => (result.id ? name(result.id) : 'abstention')).join(', ')} at ${leaders[0].count} ${leaders[0].count === 1 ? 'vote' : 'votes'} each.`;
    if (leaders[0].id === null)
        return 'Nobody was banished. Abstention received the most votes.';
    return 'Nobody was banished.';
}
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
    <section
        class="game-panel match-recap table-experience"
        aria-labelledby="recap-title"
    >
        <div class="panel-title">
            <h2 id="recap-title">What really happened</h2>
            <span>Every secret, revealed</span>
        </div>
        <div
            v-if="currentStep"
            class="table-step"
            aria-live="polite"
            aria-atomic="true"
        >
            <span class="table-eyebrow">{{ currentStep.label }}</span>
            <h3>{{ currentStep.title }}</h3>
            <p>{{ currentStep.body }}</p>
            <ul v-if="currentStep.details.length">
                <li v-for="(detail, index) in currentStep.details" :key="index">
                    {{ detail }}
                </li>
            </ul>
        </div>
        <nav class="table-step-nav" aria-label="Match reveal">
            <button
                class="button"
                :disabled="stepIndex === 0"
                @click="stepIndex--"
            >
                Back
            </button>
            <span
                >{{ stepIndex + 1 }} / {{ story.length }}<br />The match,
                revealed</span
            >
            <button
                class="button"
                :disabled="stepIndex >= story.length - 1"
                @click="stepIndex++"
            >
                Next
            </button>
        </nav>
        <section
            v-if="highlights.length"
            class="recap-highlights"
            aria-labelledby="recap-highlights-title"
        >
            <h3 id="recap-highlights-title">The moments that mattered</h3>
            <ol>
                <li v-for="highlight in highlights" :key="highlight.id">
                    <span>ROUND {{ highlight.day }}</span>
                    <h4>{{ highlight.title }}</h4>
                    <p>{{ highlight.detail }}</p>
                </li>
            </ol>
        </section>
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
                <strong>The cult’s mission: {{ recap.mission.name }}</strong>
                {{ recap.mission.description }}
            </p>
            <p v-if="!recap.complete" class="recap-note">
                This match began before recaps were added. Only actions recorded
                after the update are available.
            </p>
            <p v-if="!rounds.length" class="recap-note">
                No completed nights or votes were recorded for this match.
            </p>
            <p v-else class="recap-hint">
                Follow the village’s story. Open a night to uncover every secret
                action.
            </p>
        </div>
        <ol v-if="rounds.length" class="chronicle" aria-label="Match timeline">
            <li
                v-for="round in rounds"
                :key="round.day"
                class="chronicle-round"
            >
                <div class="chronicle-round-heading">
                    <span class="chronicle-marker" aria-hidden="true">{{
                        round.day
                    }}</span>
                    <h3>Round {{ round.day }}</h3>
                </div>
                <section
                    v-if="round.night"
                    class="chronicle-phase chronicle-night"
                    :aria-labelledby="`recap-night-${round.day}`"
                >
                    <header class="chronicle-phase-heading">
                        <h4 :id="`recap-night-${round.day}`">
                            <Moon :size="17" aria-hidden="true" />Night
                            {{ round.day }}
                        </h4>
                        <span class="chronicle-gain"
                            >+{{ round.night.gained }} ritual
                            {{
                                round.night.gained === 1 ? 'step' : 'steps'
                            }}</span
                        >
                    </header>
                    <div class="chronicle-ritual">
                        <span>Ritual progress</span>
                        <strong
                            >{{ round.night.tokens }} /
                            {{ recap.ritual_goal }} steps</strong
                        >
                        <progress
                            :value="round.night.tokens"
                            :max="Math.max(1, recap.ritual_goal)"
                            :aria-label="`Ritual progress after night ${round.day}`"
                        >
                            {{ round.night.tokens }} / {{ recap.ritual_goal }}
                        </progress>
                    </div>
                    <p v-if="round.night.chaos_event" class="chronicle-note">
                        <strong
                            >{{
                                chaosEvents[round.night.chaos_event].name
                            }}:</strong
                        >
                        {{ chaosEvents[round.night.chaos_event].description }}
                    </p>
                    <details
                        v-if="round.night.actions.length"
                        class="chronicle-secrets"
                    >
                        <summary>
                            <Eye :size="16" aria-hidden="true" />
                            <span
                                >Reveal {{ round.night.actions.length }} night
                                {{
                                    round.night.actions.length === 1
                                        ? 'action'
                                        : 'actions'
                                }}</span
                            >
                            <ChevronDown
                                :size="17"
                                class="chronicle-chevron"
                                aria-hidden="true"
                            />
                        </summary>
                        <ul class="chronicle-actions">
                            <li
                                v-for="action in round.night.actions"
                                :key="action.player_id"
                            >
                                <div class="chronicle-actor">
                                    <RecapVillager
                                        :player="
                                            villagers.get(action.player_id)
                                        "
                                        :name="name(action.player_id)"
                                    />
                                    <span class="chronicle-role">{{
                                        roles[action.role]?.name ?? action.role
                                    }}</span>
                                </div>
                                <p>{{ actionDescription(action) }}</p>
                                <p
                                    v-if="redirection(action)"
                                    class="chronicle-note"
                                >
                                    {{ redirection(action) }}
                                </p>
                                <p
                                    v-if="
                                        action.role === 'oracle' &&
                                        action.submitted &&
                                        action.veiled &&
                                        !action.forged
                                    "
                                    class="chronicle-note"
                                >
                                    The veil reversed this reading.
                                </p>
                                <p v-if="action.forged" class="chronicle-note">
                                    The Counterfeiter planted this reading,
                                    overriding any veil.
                                </p>
                                <p
                                    v-if="action.visits_hidden"
                                    class="chronicle-note"
                                >
                                    The Phantasm hid this player’s outgoing
                                    visits from observers.
                                </p>
                            </li>
                        </ul>
                    </details>
                    <p v-else class="chronicle-note">
                        Individual night actions were not recorded.
                    </p>
                </section>
                <section
                    v-if="round.discussion?.length"
                    class="chronicle-phase"
                    :aria-labelledby="`recap-discussion-${round.day}`"
                >
                    <header class="chronicle-phase-heading">
                        <h4 :id="`recap-discussion-${round.day}`">
                            <MessageCircle
                                :size="17"
                                aria-hidden="true"
                            />Village discussion
                        </h4>
                    </header>
                    <ul class="chronicle-discussion">
                        <li
                            v-for="(action, index) in round.discussion"
                            :key="index"
                        >
                            <strong>{{ name(action.player_id) }}</strong>
                            {{
                                action.type === 'exorcise'
                                    ? 'cleansed'
                                    : 'publicly promised to vote for'
                            }}
                            <strong>{{ name(action.target_id) }}</strong
                            >.
                        </li>
                    </ul>
                </section>
                <section
                    v-if="round.last_words?.accusations.length"
                    class="chronicle-phase"
                    :aria-labelledby="`recap-last-words-${round.day}`"
                >
                    <h4 :id="`recap-last-words-${round.day}`">Last Words</h4>
                    <ul>
                        <li
                            v-for="entry in round.last_words.accusations"
                            :key="entry.player_id"
                        >
                            {{ name(entry.player_id) }} accused
                            {{ name(entry.target_id) }}.
                        </li>
                    </ul>
                    <p v-for="id in round.last_words.accused_ids" :key="id">
                        <strong>{{ name(id) }}:</strong>
                        {{
                            round.last_words.defenses.find(
                                (entry) => entry.player_id === id,
                            )?.body ?? 'No defense was submitted.'
                        }}
                    </p>
                </section>
                <section
                    v-if="round.vote"
                    class="chronicle-phase chronicle-vote"
                    :aria-labelledby="`recap-vote-${round.day}`"
                >
                    <header class="chronicle-phase-heading">
                        <h4 :id="`recap-vote-${round.day}`">
                            <Vote :size="17" aria-hidden="true" />Day
                            {{ round.day }} vote
                        </h4>
                        <span
                            >{{ round.vote.ballots.length }} ballots
                            recorded</span
                        >
                    </header>
                    <p class="chronicle-verdict">{{ verdict(round.vote) }}</p>
                    <ul
                        v-if="round.vote.ballots.length"
                        class="chronicle-tally"
                        aria-label="Vote totals, including abstentions"
                    >
                        <li
                            v-for="result in tally(round.vote)"
                            :key="result.id ?? 'abstain'"
                            :class="{
                                'is-banished':
                                    result.id &&
                                    result.id === round.vote.banished_id,
                            }"
                        >
                            <span>{{
                                result.id ? name(result.id) : 'Abstained'
                            }}</span>
                            <strong
                                >{{ result.count
                                }}<span class="sr-only">
                                    {{
                                        result.count === 1 ? 'vote' : 'votes'
                                    }}</span
                                ></strong
                            >
                        </li>
                    </ul>
                    <ul class="chronicle-ballots" aria-label="Individual votes">
                        <li
                            v-for="ballot in round.vote.ballots"
                            :key="ballot.player_id"
                        >
                            <div class="chronicle-connection">
                                <RecapVillager
                                    :player="villagers.get(ballot.player_id)"
                                    :name="name(ballot.player_id)"
                                />
                                <span class="chronicle-arrow"
                                    ><ArrowRight
                                        :size="18"
                                        aria-hidden="true"
                                    /><span class="sr-only">{{
                                        ballot.submitted && ballot.target_id
                                            ? 'voted for'
                                            : 'counted as'
                                    }}</span></span
                                >
                                <RecapVillager
                                    v-if="ballot.submitted && ballot.target_id"
                                    :player="villagers.get(ballot.target_id)"
                                    :name="name(ballot.target_id)"
                                />
                                <span v-else class="chronicle-abstain"
                                    >Abstention</span
                                >
                            </div>
                            <p
                                v-if="!ballot.submitted"
                                class="chronicle-ballot-note"
                            >
                                Missed the deadline · counted as abstention.
                            </p>
                            <p
                                v-else-if="!ballot.target_id"
                                class="chronicle-ballot-note"
                            >
                                Chose to abstain.
                            </p>
                            <p
                                v-if="redirection(ballot)"
                                class="chronicle-note"
                            >
                                {{ redirection(ballot) }}
                            </p>
                            <p
                                v-if="ballot.oath_kept != null"
                                class="chronicle-ballot-note"
                            >
                                {{
                                    ballot.oath_kept
                                        ? 'Oath kept; protection earned for the next night.'
                                        : 'Oath broken; no protection earned.'
                                }}
                            </p>
                        </li>
                    </ul>
                </section>
                <p
                    v-if="
                        !round.night && !round.vote && !round.discussion?.length
                    "
                    class="chronicle-note"
                >
                    No action details were recorded for this round.
                </p>
            </li>
        </ol>
    </section>
</template>

<style scoped>
.recap-highlights {
    margin: 22px 0;
    padding: 20px 0;
    border-top: 1px solid #a6c2b23d;
    border-bottom: 1px solid #a6c2b23d;
}
.recap-highlights h3 {
    font:
        23px 'Fraunces',
        Georgia,
        serif;
    color: #efe7ce;
    margin-bottom: 18px;
}
.recap-highlights ol {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}
.recap-highlights li {
    border-left: 2px solid #a9c7af;
    padding-left: 14px;
}
.recap-highlights li > span {
    font-size: 12px;
    letter-spacing: 0.08em;
    color: #b3cfbc;
}
.recap-highlights h4 {
    font-size: 17px;
    color: #f0ead6;
    margin: 6px 0;
}
.recap-highlights p {
    font-size: 14px;
    line-height: 1.6;
    color: #c7d6ca;
}
@media (max-width: 600px) {
    .recap-highlights ol {
        grid-template-columns: 1fr;
    }
}

.match-recap {
    --chronicle-muted: #bbc8be;
}
.recap-intro {
    padding: 22px;
}
.recap-stats dt,
.recap-mission,
.recap-note,
.recap-hint {
    color: var(--chronicle-muted);
    font-size: 13px;
}
.recap-stats dd {
    font-size: 25px;
    font-variant-numeric: tabular-nums;
}
.chronicle {
    list-style: none;
    margin: 0;
    padding: 0 22px 26px 50px;
}
.chronicle-round {
    position: relative;
    padding: 0 0 28px 22px;
    border-left: 1px solid #bdcd9c45;
}
.chronicle-round:last-child {
    padding-bottom: 0;
}
.chronicle-round-heading {
    display: flex;
    align-items: center;
    min-height: 36px;
    margin-bottom: 16px;
}
.chronicle-round-heading h3 {
    margin: 0;
    color: var(--cream);
    font-family: 'Fraunces', serif;
    font-size: 23px;
    font-weight: 500;
}
.chronicle-marker {
    position: absolute;
    left: -18px;
    display: grid;
    width: 35px;
    height: 35px;
    place-items: center;
    border: 1px solid #bdcd9c65;
    border-radius: 50%;
    background: #1c3532;
    color: var(--green);
    font-size: 13px;
    font-weight: 700;
}
.chronicle-phase {
    padding: 17px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #102b294a;
}
.chronicle-phase + .chronicle-phase {
    margin-top: 12px;
}
.chronicle-night {
    border-left: 2px solid #b4a3c3;
    background: linear-gradient(115deg, #aba0bf0b, transparent 75%);
}
.chronicle-vote {
    border-left: 2px solid var(--green);
}
.chronicle-phase-heading,
.chronicle-phase-heading h4 {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.chronicle-phase-heading {
    justify-content: space-between;
    margin-bottom: 12px;
}
.chronicle-phase-heading h4 {
    margin: 0;
    color: var(--cream);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
}
.chronicle-phase-heading > span {
    color: var(--chronicle-muted);
    font-size: 12px;
}
.chronicle-phase-heading > .chronicle-gain {
    color: #d6c5e6;
}
.chronicle-ritual {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    color: var(--chronicle-muted);
    font-size: 12px;
}
.chronicle-ritual strong {
    color: var(--cream);
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}
.chronicle-ritual progress {
    width: 100%;
    height: 5px;
    grid-column: 1 / -1;
    overflow: hidden;
    border: 0;
    border-radius: 2px;
    background: #ffffff13;
    accent-color: #b9a4d0;
}
.chronicle-ritual progress::-webkit-progress-bar {
    background: #ffffff13;
}
.chronicle-ritual progress::-webkit-progress-value {
    background: #b9a4d0;
}
.chronicle-ritual progress::-moz-progress-bar {
    background: #b9a4d0;
}
.chronicle-secrets {
    margin-top: 12px;
}
.chronicle-secrets summary {
    display: flex;
    align-items: center;
    gap: 9px;
    min-height: 44px;
    padding: 8px;
    margin: 0 -8px;
    border-radius: 4px;
    color: var(--green);
    font-size: 13px;
    cursor: pointer;
    list-style: none;
}
.chronicle-secrets summary::-webkit-details-marker {
    display: none;
}
.chronicle-secrets summary:hover {
    background: #bdcd9c0c;
}
.chronicle-secrets summary:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 2px;
}
.chronicle-chevron {
    flex-shrink: 0;
    margin-left: auto;
}
.chronicle-secrets[open] .chronicle-chevron {
    transform: rotate(180deg);
}
.chronicle-actions,
.chronicle-ballots,
.chronicle-discussion,
.chronicle-tally {
    list-style: none;
    margin: 0;
    padding: 0;
}
.chronicle-actions > li,
.chronicle-ballots > li {
    padding: 14px 0;
    border-top: 1px solid var(--line);
}
.chronicle-actions > li:last-child,
.chronicle-ballots > li:last-child {
    padding-bottom: 0;
}
.chronicle-actor {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
}
.chronicle-role {
    color: #d6c5e6;
    font-size: 12px;
}
.chronicle-actions p,
.chronicle-discussion li,
.chronicle-ballot-note,
.chronicle-note {
    margin: 8px 0 0;
    color: var(--chronicle-muted);
    font-size: 13px;
    line-height: 1.65;
}
.chronicle-actions .chronicle-note,
.chronicle-note {
    color: #e5b19e;
}
.chronicle-verdict {
    margin: 0 0 12px;
    color: var(--cream);
    font-size: 14px;
    line-height: 1.65;
}
.chronicle-tally {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 15px;
}
.chronicle-tally li {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 9px;
    border: 1px solid var(--line);
    border-radius: 4px;
    color: var(--chronicle-muted);
    font-size: 12px;
}
.chronicle-tally strong {
    color: var(--cream);
}
.chronicle-tally .is-banished {
    border-color: #e5b19e75;
    color: #e5b19e;
}
.chronicle-connection {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 32px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
}
.chronicle-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--green);
}
.chronicle-abstain {
    color: var(--chronicle-muted);
    font-size: 13px;
}
@media (max-width: 600px) {
    .recap-intro {
        padding: 18px;
    }
    .chronicle {
        padding: 0 12px 20px 29px;
    }
    .chronicle-round {
        padding-left: 17px;
    }
    .chronicle-marker {
        width: 29px;
        height: 29px;
        left: -15px;
    }
    .chronicle-phase {
        padding: 12px;
    }
    .chronicle-phase-heading {
        align-items: flex-start;
    }
    .chronicle-connection {
        grid-template-columns: minmax(0, 1fr) 18px minmax(0, 1fr);
        gap: 6px;
    }
    .chronicle-connection :deep(.recap-villager) {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .chronicle-arrow {
        align-self: center;
    }
}
</style>
