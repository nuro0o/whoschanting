<script setup lang="ts">
import { ArrowLeft, ArrowRight, Bell, Eye, RotateCcw } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import {
    createRolePractice,
    practiceScenarios,
    resolvePracticeBell,
    resolvePracticeForgery,
    resolvePracticeTracking,
    rolePracticeReducer,
    trackingPlayers,
    type Alignment,
    type PracticeRole,
    type RolePracticeAction,
    type TrackingTarget,
} from '@/lib/rolePractice';

const props = defineProps<{ role: PracticeRole }>();
defineEmits<{ back: [] }>();
const state = ref(createRolePractice(props.role));
const heading = ref<HTMLElement | null>(null);
const scenario = computed(() =>
    practiceScenarios.find((item) => item.id === props.role)!,
);
const revealed = computed(() => state.value.step !== 'choose');
const forgery = computed(() =>
    resolvePracticeForgery(state.value.choice as Alignment),
);
const tracking = computed(() =>
    resolvePracticeTracking(state.value.choice as TrackingTarget),
);
const trackedName = computed(
    () =>
        trackingPlayers.find((player) => player.id === state.value.choice)
            ?.name,
);
const bell = computed(() =>
    resolvePracticeBell(state.value.choice === 'ring', state.value.earned),
);
function dispatch(action: RolePracticeAction) {
    state.value = rolePracticeReducer(state.value, action);
}
async function focusHeading() {
    await nextTick();
    heading.value?.focus();
}
watch(
    () => props.role,
    (role) => {
        state.value = createRolePractice(role);
    },
);
watch(() => state.value.step, focusHeading);
onMounted(focusHeading);
</script>

<template>
    <div class="role-practice">
        <button class="button" @click="$emit('back')">
            <ArrowLeft :size="16" /> All scenarios
        </button>
        <div class="tutorial-layout">
            <section
                class="tutorial-lesson"
                aria-labelledby="role-practice-heading"
            >
                <p class="eyebrow tutorial-chapter">
                    {{ scenario.faction }} · {{ scenario.name }} <span>/</span>
                    {{ revealed ? 'The reveal' : 'Your decision' }}
                </p>
                <h1 id="role-practice-heading" ref="heading" tabindex="-1">
                    {{ scenario.title }}
                </h1>

                <template v-if="role === 'counterfeiter'">
                    <p class="tutorial-lede">
                        Mara is Town. Tonight, a veil will make her appear Cult,
                        and the Oracle will investigate her. Your forgery has
                        the final word.
                    </p>
                    <p>
                        Spend your once-per-match ability to choose how Mara
                        appears: Town or Cult. You forgo chanting this night.
                        The forgery overrides a veil; her true role stays the
                        same.
                    </p>
                    <div
                        v-if="!revealed"
                        class="tutorial-answer-list practice-choices"
                        role="group"
                        aria-label="Choose Mara's apparent alignment"
                    >
                        <button
                            v-for="alignment in ['town', 'cult']"
                            :key="alignment"
                            class="tutorial-answer"
                            :aria-pressed="state.choice === alignment"
                            @click="
                                dispatch({ type: 'choose', choice: alignment })
                            "
                        >
                            Make Mara appear
                            {{ alignment === 'town' ? 'Town' : 'Cult' }}
                        </button>
                    </div>
                    <template v-else>
                        <div class="tutorial-role-note">
                            <Eye :size="24" />
                            <div>
                                <h2>
                                    {{
                                        forgery.apparent === 'town'
                                            ? 'The veil is overridden.'
                                            : 'A false reading survives.'
                                    }}
                                </h2>
                                <p>
                                    {{
                                        forgery.apparent === 'town'
                                            ? 'Your Town forgery overrides the Cult appearance from the veil. This reading happens to match Mara’s real allegiance.'
                                            : 'Your Cult forgery makes a Town player appear Cult. It sets the result directly, even though the veil would also have made her appear Cult.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p>
                            The Oracle receives an apparent alignment, without a
                            warning that you forged it. A Medium’s
                            true-alignment reading cannot be altered this way.
                        </p>
                    </template>
                    <p class="tutorial-small practice-limits">
                        This exercise reveals the setup so you can learn the
                        interaction. In a live match you do not know whom the
                        Oracle will investigate. The forgery lasts only tonight
                        and is spent even if nobody investigates Mara or your
                        action is disrupted.
                    </p>
                </template>

                <template v-else-if="role === 'tracker'">
                    <p class="tutorial-lede">
                        Follow another living player. At dawn, you learn their
                        visible outgoing target, if any.
                    </p>
                    <p v-if="!revealed">
                        Choose a villager at the practice table, then confirm.
                        Each has a different scripted night. Replay to follow
                        the others.
                    </p>
                    <template v-else>
                        <p class="tutorial-callout">
                            <Eye :size="20" /><span>{{
                                tracking.visible
                                    ? `${trackedName} was seen targeting ${tracking.visible}.`
                                    : `No outgoing visit was visible for ${trackedName}.`
                            }}</span>
                        </p>
                        <h2 class="practice-question">
                            What does this establish?
                        </h2>
                        <div class="tutorial-answer-list">
                            <button
                                class="tutorial-answer"
                                :disabled="state.step === 'complete'"
                                @click="
                                    dispatch({
                                        type: 'interpret',
                                        certain: true,
                                    })
                                "
                            >
                                {{
                                    tracking.visible
                                        ? 'Their action succeeded on that player.'
                                        : 'They definitely stayed home.'
                                }}
                            </button>
                            <button
                                class="tutorial-answer"
                                :class="{
                                    'is-correct': state.step === 'complete',
                                }"
                                :disabled="state.step === 'complete'"
                                @click="
                                    dispatch({
                                        type: 'interpret',
                                        certain: false,
                                    })
                                "
                            >
                                {{
                                    tracking.visible
                                        ? 'They attempted to target that player; success and allegiance are unknown.'
                                        : 'No visit was visible; an attempt may have been concealed.'
                                }}
                            </button>
                        </div>
                        <div
                            v-if="state.step === 'complete'"
                            class="tutorial-role-note"
                        >
                            <Eye :size="24" />
                            <div>
                                <h2>Behind the footsteps</h2>
                                <p v-if="tracking.disrupted">
                                    Mara submitted a visit to Nell, but a
                                    disruption stopped her action. Her attempted
                                    visit was still visible to you.
                                </p>
                                <p v-else-if="tracking.concealed">
                                    Ivo attempted to visit Bram. A Phantasm
                                    concealed Ivo’s outgoing visits, so your
                                    result showed none.
                                </p>
                                <p v-else>
                                    Nell submitted no targeted action. Your
                                    result looks the same as a concealed visit.
                                    This clue alone cannot distinguish the two.
                                </p>
                            </div>
                        </div>
                    </template>
                    <p class="tutorial-small practice-limits">
                        Tracking is available every night. It reveals no role,
                        ability or allegiance. Phantasm concealment and the
                        Eclipse hide outgoing visits. If you are disrupted, your
                        own tracking produces no result.
                    </p>
                </template>

                <template v-else>
                    <p class="tutorial-lede">
                        The ritual stands at 5 of 6. You still have your one
                        bell. Do you ring it tonight?
                    </p>
                    <p>
                        Your bell prevents at most one step earned tonight. It
                        never removes existing progress. Keeping watch saves the
                        charge.
                    </p>
                    <fieldset v-if="!revealed" class="practice-condition">
                        <legend>
                            Practice setup: steps the cult will earn
                        </legend>
                        <div class="practice-condition-options">
                            <button
                                v-for="earned in [0, 1, 2] as const"
                                :key="earned"
                                class="button"
                                :aria-pressed="state.earned === earned"
                                @click="
                                    dispatch({ type: 'set-earned', earned })
                                "
                            >
                                {{ earned }}
                                {{ earned === 1 ? 'step' : 'steps' }}
                            </button>
                        </div>
                    </fieldset>
                    <div
                        v-if="!revealed"
                        class="tutorial-answer-list"
                        role="group"
                        aria-label="Choose whether to ring the bell"
                    >
                        <button
                            class="tutorial-answer"
                            :aria-pressed="state.choice === 'ring'"
                            @click="
                                dispatch({ type: 'choose', choice: 'ring' })
                            "
                        >
                            <span
                                >Ring the bell
                                <small>Spend your only charge</small></span
                            ><Bell :size="19" /></button
                        ><button
                            class="tutorial-answer"
                            :aria-pressed="state.choice === 'save'"
                            @click="
                                dispatch({ type: 'choose', choice: 'save' })
                            "
                        >
                            Keep watch and save it
                        </button>
                    </div>
                    <template v-else
                        ><div class="tutorial-role-note">
                            <Bell :size="24" />
                            <div>
                                <h2>
                                    {{
                                        bell.finalVote
                                            ? 'The ritual is full. One last vote.'
                                            : 'The ritual stays below its threshold.'
                                    }}
                                </h2>
                                <p>
                                    {{
                                        bell.prevented
                                            ? 'Your bell prevented one earned step.'
                                            : bell.abilitySpent
                                              ? 'No step was earned to prevent. Your bell is still spent.'
                                              : 'You saved your bell. No steps were prevented.'
                                    }}
                                    {{
                                        state.earned === 2 && bell.prevented
                                            ? 'The second earned step still reached the threshold.'
                                            : ''
                                    }}
                                </p>
                            </div>
                        </div>
                        <p>
                            {{
                                bell.finalVote
                                    ? 'One final discussion and vote remain. The Town must banish every remaining cultist; if any survive that vote, the cult wins. No winner is decided by filling the ritual at dawn.'
                                    : 'Remaining below the threshold buys time; it does not guarantee a Town victory.'
                            }}
                        </p></template
                    >
                    <p class="tutorial-small practice-limits">
                        The earned steps are shown only for this exercise. In a
                        live match, you must judge the threat from the mission
                        and discussion. A disrupted bell prevents nothing but
                        still spends the charge.
                    </p>
                </template>

                <button
                    v-if="!revealed"
                    class="button primary tutorial-next"
                    :disabled="!state.choice"
                    @click="dispatch({ type: 'confirm' })"
                >
                    {{
                        role === 'counterfeiter'
                            ? 'Forge instead of chanting'
                            : role === 'tracker'
                              ? 'Confirm tracking'
                              : state.choice === 'ring'
                                ? 'Ring the bell'
                                : state.choice === 'save'
                                  ? 'Keep watch'
                                  : 'Confirm your choice'
                    }}
                    <ArrowRight :size="17" />
                </button>
                <p class="tutorial-feedback" role="status" aria-live="polite">
                    {{ state.feedback }}
                </p>
            </section>

            <aside class="tutorial-table" aria-label="Practice evidence">
                <div class="tutorial-table-heading">
                    <p class="eyebrow">
                        {{ revealed ? 'AT DAWN' : 'TONIGHT’S SETUP' }}
                    </p>
                    <span>Scripted practice</span>
                </div>
                <div
                    v-if="role === 'tracker'"
                    class="tutorial-seats practice-seats"
                >
                    <button
                        v-for="player in trackingPlayers"
                        :key="player.id"
                        class="tutorial-seat"
                        :class="{ selected: state.choice === player.id }"
                        :disabled="revealed"
                        :aria-pressed="state.choice === player.id"
                        @click="dispatch({ type: 'choose', choice: player.id })"
                    >
                        <CharacterPortrait
                            :character="player.character"
                            decorative
                        /><strong>{{ player.name }}</strong
                        ><small>{{
                            state.choice === player.id ? 'Selected' : 'Follow'
                        }}</small>
                    </button>
                </div>
                <div
                    class="tutorial-evidence"
                    :class="{ 'has-reading': revealed }"
                >
                    <div class="tutorial-evidence-top">
                        <Eye :size="19" />
                        <p class="eyebrow">
                            {{
                                role === 'counterfeiter'
                                    ? 'THE ORACLE’S NOTE'
                                    : role === 'tracker'
                                      ? 'YOUR PRIVATE NOTE'
                                      : 'THE RITUAL'
                            }}
                        </p>
                        <span>01</span>
                    </div>
                    <template v-if="role === 'counterfeiter'"
                        ><p class="tutorial-reading">
                            Mara
                            <em>{{
                                revealed
                                    ? `appeared ${forgery.apparent === 'town' ? 'Town' : 'Cult'}.`
                                    : 'awaits a reading.'
                            }}</em>
                        </p>
                        <p>
                            {{
                                revealed
                                    ? 'Your forgery is spent. You did not chant.'
                                    : 'True alignment: Town. A veil is active tonight.'
                            }}
                        </p></template
                    >
                    <template v-else-if="role === 'tracker'"
                        ><p class="tutorial-reading">
                            {{ revealed ? trackedName : 'Every footprint' }}
                            <em>{{
                                revealed
                                    ? tracking.visible
                                        ? `targeted ${tracking.visible}.`
                                        : 'left no visible visit.'
                                    : 'needs context.'
                            }}</em>
                        </p>
                        <p>
                            {{
                                revealed
                                    ? tracking.visible
                                        ? 'A visible attempt, never proof of allegiance.'
                                        : 'No visible visit, never proof of inactivity.'
                                    : 'Choose a player and confirm to receive a result.'
                            }}
                        </p></template
                    >
                    <template v-else
                        ><p class="tutorial-reading">
                            {{ revealed ? bell.tokens : 5 }} / 6
                            <em>ritual steps</em>
                        </p>
                        <p>
                            {{ state.earned }}
                            {{
                                state.earned === 1
                                    ? 'step earned'
                                    : 'steps earned'
                            }}
                            tonight
                            {{
                                revealed
                                    ? `· ${bell.prevented} prevented`
                                    : '(practice setup)'
                            }}
                        </p>
                        <p class="tutorial-evidence-caution">
                            {{
                                revealed && bell.abilitySpent
                                    ? 'Bell charge: spent'
                                    : 'Bell charge: available'
                            }}
                        </p></template
                    >
                </div>
                <p class="tutorial-small practice-limits">
                    No timer. No other players needed. Your choices stay in this
                    practice and reset when you leave.
                </p>
            </aside>
        </div>
        <footer class="tutorial-footer">
            <span>Replay to explore a different outcome.</span
            ><button @click="dispatch({ type: 'restart' })">
                <RotateCcw :size="14" /> Restart scenario
            </button>
        </footer>
    </div>
</template>

<style scoped>
.practice-choices,
.practice-limits {
    margin-top: 22px;
}
.practice-seats {
    margin-top: 22px;
}
.practice-question {
    font-size: 24px;
    margin: 24px 0 16px;
}
.tutorial-answer[aria-pressed='true'],
.practice-condition .button[aria-pressed='true'] {
    border-color: var(--green);
    background: #2a4037;
    color: var(--cream);
}
.tutorial-answer small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
}
.practice-condition {
    border: 0;
    padding: 0;
    margin: 24px 0;
    min-width: 0;
}
.practice-condition legend {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 12px;
}
.practice-condition-options {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.practice-condition-options .button {
    min-height: 44px;
}
button:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 4px;
}
</style>
