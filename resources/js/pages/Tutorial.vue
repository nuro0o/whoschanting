<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Eye,
    Lamp as Lantern,
    Moon,
    RotateCcw,
    Waves,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import {
    createTutorialState,
    resolveTutorialVote,
    tutorialPlayers,
    tutorialReducer,
    tutorialSeals,
    type TutorialAction,
    type TutorialTarget,
    type TutorialVote,
} from '@/lib/tutorial';
import '../../css/chanting.css';
import '../../css/tutorial.css';

const state = ref(createTutorialState());
const heading = ref<HTMLElement | null>(null);
const steps = ['welcome', 'night', 'evidence', 'curse', 'vote', 'recap'];
const labels = [
    'Your role',
    'Investigate',
    'Read the clue',
    'Break a curse',
    'Cast a vote',
    'The reveal',
];
const stepIndex = computed(() => steps.indexOf(state.value.step));
const seal = computed(() => tutorialSeals[state.value.sealIndex]);
const outcome = computed(() =>
    state.value.voteTarget ? resolveTutorialVote(state.value.voteTarget) : null,
);
const selectable = computed(
    () => state.value.step === 'night' || state.value.step === 'vote',
);
const selected = computed(() =>
    state.value.step === 'night'
        ? state.value.investigationTarget
        : state.value.voteTarget,
);
const names = (id: TutorialVote) =>
    id === 'abstain'
        ? 'Abstain'
        : tutorialPlayers.find((player) => player.id === id)?.name;
const roles = {
    you: 'Oracle · Town',
    mara: 'Warden · Town',
    nell: 'Townsperson · Town',
    ivo: 'Veilweaver · Cult',
    bram: 'Acolyte · Cult',
};

function dispatch(action: TutorialAction) {
    state.value = tutorialReducer(state.value, action);
}
function selectPlayer(id: TutorialTarget) {
    dispatch(
        state.value.step === 'night'
            ? { type: 'select-investigation', target: id }
            : { type: 'select-vote', target: id },
    );
}
watch(
    () => state.value.step,
    async () => {
        await nextTick();
        heading.value?.focus();
    },
);
</script>

<template>
    <Head title="Learn to play · A night in the village" />
    <div class="chanting tutorial-page">
        <header class="tutorial-header">
            <a href="/" class="wordmark" aria-label="Who's Chanting? home"
                ><span class="brand-eye"><Eye :size="23" /></span>who’s
                chanting<span class="brand-question">?</span></a
            >
            <a class="tutorial-exit" href="/"
                >Exit practice <ArrowRight :size="16"
            /></a>
        </header>

        <main class="tutorial-shell">
            <div class="tutorial-masthead">
                <p class="eyebrow">
                    <Waves :size="16" /> THE VILLAGE FIELD GUIDE
                </p>
                <span
                    >Solo practice <span aria-hidden="true">/</span> No
                    timer</span
                >
            </div>
            <nav class="tutorial-progress" aria-label="Tutorial progress">
                <ol>
                    <li
                        v-for="(label, index) in labels"
                        :key="label"
                        :class="{
                            current: index === stepIndex,
                            complete: index < stepIndex,
                        }"
                        :aria-current="index === stepIndex ? 'step' : undefined"
                    >
                        <span class="tutorial-step-number"
                            ><Check
                                v-if="index < stepIndex"
                                :size="13"
                                aria-hidden="true"
                            /><template v-else>{{ index + 1 }}</template></span
                        ><span class="tutorial-step-label">{{ label }}</span>
                    </li>
                </ol>
            </nav>

            <div class="tutorial-layout">
                <section
                    class="tutorial-lesson"
                    aria-labelledby="tutorial-heading"
                >
                    <p class="eyebrow tutorial-chapter">
                        FIELD NOTE 0{{ stepIndex + 1 }}
                        <span aria-hidden="true">/</span>
                        {{ labels[stepIndex] }}
                    </p>
                    <h1 id="tutorial-heading" ref="heading" tabindex="-1">
                        <template v-if="state.step === 'welcome'"
                            >A quiet village.<br /><em
                                >A useful little lie.</em
                            ></template
                        >
                        <template v-else-if="state.step === 'night'"
                            >Night falls.<br /><em
                                >Look a little closer.</em
                            ></template
                        >
                        <template v-else-if="state.step === 'evidence'"
                            >A reading.<br /><em>Not a verdict.</em></template
                        >
                        <template v-else-if="state.step === 'curse'"
                            >A fog in your mind.<br /><em
                                >Find the light.</em
                            ></template
                        >
                        <template v-else-if="state.step === 'vote'"
                            >Morning brings<br /><em
                                >conflicting stories.</em
                            ></template
                        >
                        <template v-else
                            >Now you know<br /><em
                                >who was chanting.</em
                            ></template
                        >
                    </h1>

                    <template v-if="state.step === 'welcome'">
                        <p class="tutorial-lede">
                            Learn one round with four scripted villagers. A few
                            minutes, a secret role, and one very suspicious
                            clue.
                        </p>
                        <div class="tutorial-role-note">
                            <Eye :size="24" />
                            <div>
                                <p class="eyebrow">YOUR PRIVATE ROLE · TOWN</p>
                                <h2>The Oracle</h2>
                                <p>
                                    Once per match, investigate a player at
                                    night to learn their apparent alignment.
                                    Choose your night carefully: that reading
                                    can be altered.
                                </p>
                            </div>
                        </div>
                        <p>
                            Your role card is reliable. Your goal is to help the
                            Town banish <strong>every cultist</strong> before
                            the summoning.
                        </p>
                        <button
                            class="button primary tutorial-next"
                            @click="dispatch({ type: 'begin' })"
                        >
                            Begin the night <ArrowRight :size="17" />
                        </button>
                        <p class="tutorial-small">
                            Guided choices. No account or other players needed.
                            Refreshing starts the exercise again.
                        </p>
                    </template>

                    <template v-else-if="state.step === 'night'">
                        <p class="tutorial-lede">
                            The Oracle gets one investigation for the whole
                            match. Tonight, you will use it on Mara.
                        </p>
                        <ol class="tutorial-instructions">
                            <li>Select Mara at the table.</li>
                            <li>
                                Confirm your investigation to spend the ability.
                            </li>
                        </ol>
                        <p class="tutorial-callout">
                            <Moon :size="20" /><span
                                >Selecting a portrait only chooses your target.
                                The confirmation button submits the
                                action.</span
                            >
                        </p>
                        <p class="tutorial-small">
                            This target is guided. In a real match, choose
                            another living player or save your ability for a
                            later night.
                        </p>
                    </template>

                    <template v-else-if="state.step === 'evidence'">
                        <p class="tutorial-lede">
                            Your private reading says Mara appeared Cult. Does
                            this prove Mara is a cultist?
                        </p>
                        <div class="tutorial-answer-list">
                            <button
                                class="tutorial-answer"
                                :disabled="state.evidenceUnderstood"
                                @click="
                                    dispatch({
                                        type: 'answer-evidence',
                                        certain: true,
                                    })
                                "
                            >
                                Yes. The reading proves it.
                            </button>
                            <button
                                class="tutorial-answer"
                                :class="{
                                    'is-correct': state.evidenceUnderstood,
                                }"
                                :disabled="state.evidenceUnderstood"
                                @click="
                                    dispatch({
                                        type: 'answer-evidence',
                                        certain: false,
                                    })
                                "
                            >
                                No. The reading could have been altered.<Check
                                    v-if="state.evidenceUnderstood"
                                    :size="19"
                                />
                            </button>
                        </div>
                        <p class="tutorial-small">
                            Keep the wording precise: “appeared Cult,” not “is
                            Cult.” Compare the clue with claims and other
                            evidence.
                        </p>
                        <button
                            v-if="state.evidenceUnderstood"
                            class="button primary tutorial-next"
                            @click="dispatch({ type: 'continue-evidence' })"
                        >
                            Continue to dawn <ArrowRight :size="17" />
                        </button>
                    </template>

                    <template v-else-if="state.step === 'curse'">
                        <p class="tutorial-lede">
                            Someone sent you Mind Mist. Clear three small seals
                            to regain your voice.
                        </p>
                        <p>
                            Choose the numbered lanterns from
                            <strong>smallest to largest</strong>. Ignore the
                            words. A wrong choice resets only this seal.
                        </p>
                        <div
                            class="tutorial-puzzle"
                            :class="{ solved: state.curseSolved }"
                        >
                            <div class="tutorial-puzzle-heading">
                                <span
                                    ><Lantern :size="18" />
                                    {{
                                        state.curseSolved
                                            ? 'Mind clear'
                                            : `Seal ${state.sealIndex + 1} of 3`
                                    }}</span
                                ><span>{{
                                    state.curseSolved
                                        ? '3 / 3 cleared'
                                        : `${state.sealIndex} / 3 cleared`
                                }}</span>
                            </div>
                            <div
                                v-if="!state.curseSolved"
                                class="tutorial-lanterns"
                                :key="state.sealIndex"
                                role="group"
                                :aria-label="`Seal ${state.sealIndex + 1} lanterns`"
                            >
                                <button
                                    v-for="token in seal.tokens"
                                    :key="token"
                                    :disabled="state.lanterns.includes(token)"
                                    :class="{
                                        lit: state.lanterns.includes(token),
                                    }"
                                    :aria-label="`${token}${state.lanterns.includes(token) ? ', lit' : ''}`"
                                    @click="
                                        dispatch({
                                            type: 'choose-lantern',
                                            token,
                                        })
                                    "
                                >
                                    <Lantern
                                        :size="27"
                                        aria-hidden="true"
                                    /><span>{{ token }}</span>
                                </button>
                            </div>
                            <p
                                v-if="!state.curseSolved"
                                class="tutorial-lantern-order"
                            >
                                Your order:
                                {{
                                    state.lanterns.length
                                        ? state.lanterns.join(' → ')
                                        : 'No lanterns lit yet'
                                }}
                            </p>
                            <p v-else class="tutorial-cleared">
                                <Check :size="25" /> The mist lifts. You can
                                speak and vote again.
                            </p>
                        </div>
                        <p class="tutorial-small">
                            This is a shortened practice puzzle. In live play, a
                            curse blocks chat and actions until solved or
                            expired, while the phase clock keeps running. Here,
                            take your time.
                        </p>
                        <button
                            v-if="state.curseSolved"
                            class="button primary tutorial-next"
                            @click="dispatch({ type: 'continue-curse' })"
                        >
                            Join the discussion <ArrowRight :size="17" />
                        </button>
                    </template>

                    <template v-else-if="state.step === 'vote'">
                        <p class="tutorial-lede">
                            Listen to the claims, then choose a player to banish
                            or abstain. There is no certain answer in these
                            words alone.
                        </p>
                        <div
                            class="tutorial-discussion"
                            aria-label="Scripted discussion"
                        >
                            <p>
                                <strong>Mara <span>claims</span></strong
                                >“I’m Town. A veil could explain that reading.
                                Don’t let one clue decide this.”
                            </p>
                            <p>
                                <strong>Ivo <span>claims</span></strong
                                >“The Oracle saw Cult. That’s enough for me.
                                Vote Mara.”
                            </p>
                            <p>
                                <strong>Nell <span>claims</span></strong
                                >“What else do we know? I’m suspicious of anyone
                                insisting this is certain.”
                            </p>
                            <p>
                                <strong>Bram <span>claims</span></strong
                                >“I’m following the suspicion on Mara.”
                            </p>
                        </div>
                        <p class="tutorial-small">
                            A unique leading ballot banishes its target.
                            Abstention also competes for the lead. A tie, or an
                            abstention win, banishes nobody.
                        </p>
                    </template>

                    <template v-else-if="outcome">
                        <p
                            class="tutorial-lede"
                            v-if="outcome.banished === 'ivo'"
                        >
                            Ivo is banished, 3 votes to 2. You removed a
                            cultist, but Bram remains. The match would continue.
                        </p>
                        <p
                            class="tutorial-lede"
                            v-else-if="outcome.banished === 'mara'"
                        >
                            Mara is banished, 3 votes to 2. She was Town. Both
                            cultists remain, and the match would continue.
                        </p>
                        <p class="tutorial-lede" v-else>
                            The leading vote is tied, 2 to 2. Nobody is
                            banished. Both cultists remain, and the match would
                            continue.
                        </p>
                        <p class="tutorial-reveal-label">
                            END-OF-EXERCISE REVEAL · NOT A MATCH VICTORY
                        </p>
                        <div class="tutorial-recap-story">
                            <h2>What happened in the dark</h2>
                            <p>
                                <strong>Ivo, the Veilweaver,</strong> veiled and
                                cursed Mara. That veil reversed your reading:
                                she appeared Cult even though she was Town.
                            </p>
                            <p>
                                <strong>Bram, the Acolyte,</strong> sent Mind
                                Mist to you. Mara, the Warden, protected Nell,
                                so neither curse was stopped. She cleared her
                                own curse before joining the discussion.
                            </p>
                            <p>
                                Both cultists chanted. Their Concord mission
                                advanced the ritual by
                                <strong>2 steps out of 6</strong>.
                            </p>
                        </div>
                        <details class="tutorial-ballots">
                            <summary>See all five ballots</summary>
                            <ul>
                                <li
                                    v-for="ballot in outcome.ballots"
                                    :key="ballot.voter"
                                >
                                    <span>{{ ballot.voter }}</span
                                    ><ArrowRight :size="14" /><strong>{{
                                        names(ballot.target)
                                    }}</strong>
                                </li>
                            </ul>
                        </details>
                        <p class="tutorial-small">
                            We reveal everyone here to explain the exercise. In
                            a real match, roles stay secret until victory. When
                            the ritual fills, Town gets one final discussion and
                            vote to remove every remaining cultist.
                        </p>
                        <div class="tutorial-finish-actions">
                            <a href="/" class="button primary"
                                >Play with friends <ArrowRight :size="17" /></a
                            ><button
                                class="button"
                                @click="dispatch({ type: 'restart' })"
                            >
                                Practice again
                            </button>
                        </div>
                        <p class="tutorial-small">
                            Practice complete. No XP awarded. Remember: question
                            readings, clear curses, and confirm your choices.
                        </p>
                    </template>

                    <p
                        class="tutorial-feedback"
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        {{ state.feedback }}
                    </p>
                </section>

                <aside
                    class="tutorial-table"
                    aria-label="Practice village and private notes"
                >
                    <div class="tutorial-table-heading">
                        <p class="eyebrow">
                            {{
                                state.step === 'recap'
                                    ? 'THE FACES BEHIND THE CLAIMS'
                                    : 'YOUR PRACTICE TABLE'
                            }}
                        </p>
                        <span>5 seats</span>
                    </div>
                    <p class="tutorial-table-note">
                        {{
                            state.step === 'recap'
                                ? 'Roles revealed for this exercise.'
                                : 'Four scripted villagers. Appearances tell you nothing about allegiance.'
                        }}
                    </p>
                    <div class="tutorial-seats">
                        <template
                            v-for="player in tutorialPlayers"
                            :key="player.id"
                        >
                            <button
                                v-if="selectable && player.id !== 'you'"
                                class="tutorial-seat"
                                :class="{ selected: selected === player.id }"
                                :aria-pressed="selected === player.id"
                                :aria-label="`Select ${player.name}`"
                                @click="
                                    selectPlayer(player.id as TutorialTarget)
                                "
                            >
                                <CharacterPortrait
                                    :character="player.character"
                                    decorative
                                /><strong>{{ player.name }}</strong
                                ><small>{{
                                    selected === player.id
                                        ? 'Selected'
                                        : state.step === 'night' &&
                                            player.id === 'mara'
                                          ? 'Guided target'
                                          : 'Select'
                                }}</small>
                            </button>
                            <div
                                v-else
                                class="tutorial-seat"
                                :class="{ 'your-seat': player.id === 'you' }"
                            >
                                <CharacterPortrait
                                    :character="player.character"
                                    decorative
                                /><strong>{{ player.name }}</strong
                                ><small>{{
                                    state.step === 'recap'
                                        ? roles[player.id]
                                        : player.id === 'you'
                                          ? 'Oracle · Town'
                                          : 'Role hidden'
                                }}</small>
                            </div>
                        </template>
                    </div>
                    <div
                        v-if="state.step === 'night'"
                        class="tutorial-table-action"
                    >
                        <button
                            class="button primary"
                            :disabled="!state.investigationTarget"
                            @click="dispatch({ type: 'confirm-investigation' })"
                        >
                            Confirm investigation <ArrowRight :size="16" />
                        </button>
                        <p>Uses your one investigation for this match.</p>
                    </div>
                    <div
                        v-if="state.step === 'vote'"
                        class="tutorial-table-action"
                    >
                        <button
                            class="button tutorial-abstain"
                            :aria-pressed="state.voteTarget === 'abstain'"
                            :class="{
                                selected: state.voteTarget === 'abstain',
                            }"
                            @click="
                                dispatch({
                                    type: 'select-vote',
                                    target: 'abstain',
                                })
                            "
                        >
                            {{
                                state.voteTarget === 'abstain'
                                    ? 'Abstention selected'
                                    : 'Choose to abstain'
                            }}</button
                        ><button
                            class="button primary"
                            :disabled="!state.voteTarget"
                            @click="dispatch({ type: 'confirm-vote' })"
                        >
                            Confirm
                            {{
                                state.voteTarget === 'abstain'
                                    ? 'abstention'
                                    : 'vote'
                            }}
                            <ArrowRight :size="16" />
                        </button>
                        <p>You can change your selection before confirming.</p>
                    </div>

                    <div
                        class="tutorial-evidence"
                        :class="{ 'has-reading': state.investigated }"
                    >
                        <div class="tutorial-evidence-top">
                            <Eye :size="19" />
                            <p class="eyebrow">PRIVATE ORACLE NOTE</p>
                            <span>01</span>
                        </div>
                        <template v-if="state.investigated"
                            ><p class="tutorial-reading">
                                Mara <em>appeared Cult.</em>
                            </p>
                            <p>Night 1 · Investigation used</p>
                            <div
                                v-if="state.step === 'recap'"
                                class="tutorial-evidence-reveal"
                            >
                                <strong>True alignment: Town</strong
                                ><span>Ivo’s veil reversed this reading.</span>
                            </div>
                            <p v-else class="tutorial-evidence-caution">
                                An apparent alignment. Keep an open mind.
                            </p></template
                        >
                        <template v-else
                            ><p class="tutorial-reading">
                                Every clue<br /><em>needs context.</em>
                            </p>
                            <p>
                                Your reading will appear here after you
                                investigate.
                            </p></template
                        >
                    </div>
                    <div
                        v-if="state.step === 'vote' || state.step === 'recap'"
                        class="tutorial-ritual"
                    >
                        <span>Ritual at dawn</span>
                        <div aria-hidden="true">
                            <i
                                v-for="n in 6"
                                :key="n"
                                :class="{ filled: n <= 2 }"
                            ></i>
                        </div>
                        <strong>2 / 6</strong>
                    </div>
                    <p
                        v-if="state.step === 'welcome'"
                        class="tutorial-margin-note"
                    >
                        Trust your friends.<br /><em>Mostly.</em>
                    </p>
                </aside>
            </div>

            <footer class="tutorial-footer">
                <span>One guided round. No live match is created.</span
                ><button @click="dispatch({ type: 'restart' })">
                    <RotateCcw :size="14" /> Restart practice
                </button>
            </footer>
        </main>
    </div>
</template>
