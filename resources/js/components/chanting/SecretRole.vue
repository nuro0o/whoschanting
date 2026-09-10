<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Eye, EyeOff, LockKeyhole } from '@lucide/vue';
import { roles, type RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    active: boolean;
    newResults: number;
}>();
const emit = defineEmits<{ hide: []; 'results-read': [] }>();
const revealed = defineModel<boolean>({ default: false });
const resultsOpen = ref(false);
const resultsElement = ref<HTMLElement>();
const resultsVisible = ref(false);
const revealButton = ref<HTMLButtonElement>();
let resultsObserver: IntersectionObserver | undefined;
watch(resultsElement, (element) => {
    resultsObserver?.disconnect();
    resultsVisible.value = false;
    if (!element) return;
    resultsObserver = new IntersectionObserver(
        ([entry]) => {
            resultsVisible.value = entry.isIntersecting;
        },
        { threshold: 0.25 },
    );
    resultsObserver.observe(element);
});
watch(
    [resultsVisible, () => props.active, () => props.state.me.results.length],
    () => {
        acknowledgeResults();
    },
    { flush: 'post' },
);
function acknowledgeResults() {
    if (
        resultsVisible.value &&
        props.active &&
        revealed.value &&
        document.visibilityState === 'visible'
    )
        emit('results-read');
}
const justRevealed = ref(false);
watch(revealed, (visible) => {
    justRevealed.value = visible;
    if (!visible) resultsOpen.value = false;
});
function revealRole() {
    justRevealed.value = true;
    revealed.value = true;
}
async function hideRole() {
    revealed.value = false;
    emit('hide');
    await nextTick();
    revealButton.value?.focus({ preventScroll: true });
}
async function showResults() {
    resultsOpen.value = true;
    await nextTick();
    resultsElement.value?.focus();
}
defineExpose({ showResults });
onMounted(() =>
    document.addEventListener('visibilitychange', acknowledgeResults),
);
onBeforeUnmount(() => {
    resultsObserver?.disconnect();
    document.removeEventListener('visibilitychange', acknowledgeResults);
});
</script>
<template>
    <section class="game-panel role-panel" aria-label="Your private role">
        <div v-if="!revealed" class="role-cover">
            <span class="role-sigil" aria-hidden="true">◈</span>
            <h2>Your private role</h2>
            <p>
                Your role is for your eyes only. Make sure no curious neighbors
                are looking.
            </p>
            <button ref="revealButton" class="button" @click="revealRole">
                <Eye :size="15" /> Reveal my role
            </button>
        </div>
        <div
            v-else
            class="role-private"
            :class="{
                cult: state.me.alignment === 'cult',
                'role-revealing': justRevealed,
            }"
            @animationend="justRevealed = false"
        >
            <div class="role-private-heading">
                <p class="eyebrow"><LockKeyhole :size="12" /> YOUR EYES ONLY</p>
                <button class="hide-role" @click="hideRole">
                    <EyeOff :size="15" />Hide secrets
                </button>
            </div>
            <h2>{{ roles[state.me.role ?? '']?.name ?? state.me.role }}</h2>
            <p class="role-subtitle">
                {{ roles[state.me.role ?? '']?.subtitle }}
            </p>
            <div class="role-objective">
                <p class="eyebrow">YOUR OBJECTIVE</p>
                <strong>{{
                    state.me.alignment === 'cult'
                        ? 'Your team: Cult'
                        : 'Your team: Town'
                }}</strong>
                <p>
                    {{
                        state.me.alignment === 'cult'
                            ? 'Complete the ritual, eliminate the town, or reach a final pair of one cultist and one town player.'
                            : 'Find and banish every cultist before the ritual is complete.'
                    }}
                </p>
            </div>
            <button
                v-if="state.me.results.length"
                class="button investigation-toggle"
                :aria-expanded="resultsOpen"
                aria-controls="role-investigations"
                @click="resultsOpen = !resultsOpen"
            >
                {{
                    resultsOpen ? 'Hide investigations' : 'Read investigations'
                }}
                <span v-if="newResults" class="room-badge"
                    >{{ newResults }} new result{{
                        newResults === 1 ? '' : 's'
                    }}</span
                >
            </button>
            <div
                v-if="resultsOpen && state.me.results.length"
                id="role-investigations"
                ref="resultsElement"
                class="private-separator role-results"
                tabindex="-1"
            >
                <strong>Your investigations · private</strong>
                <ul class="investigation-list">
                    <li
                        v-for="(result, index) in [
                            ...state.me.results,
                        ].reverse()"
                        :key="index"
                    >
                        Night {{ result.day }} · {{ result.target }} appeared
                        <strong>{{ result.alignment }}</strong
                        >.
                    </li>
                </ul>
                <p>
                    Readings can be veiled. Your own role and mission always
                    tell the truth.
                </p>
            </div>
            <div class="role-details">
                <p class="eyebrow">TONIGHT’S ABILITY</p>
                <p>{{ roles[state.me.role ?? '']?.description }}</p>
                <div v-if="state.me.mission" class="private-separator">
                    <p class="eyebrow">YOUR MISSION</p>
                    <strong>{{ state.me.mission.name }}</strong>
                    <p>{{ state.me.mission.description }}</p>
                </div>
                <div v-if="state.me.allies.length" class="private-separator">
                    <p class="eyebrow">
                        {{
                            state.me.allies.length === 1
                                ? 'YOUR TEAMMATE'
                                : 'YOUR TEAMMATES'
                        }}
                    </p>
                    <p v-for="ally in state.me.allies" :key="ally.id">
                        <strong>{{ ally.name }}</strong> ·
                        {{ roles[ally.role]?.name ?? ally.role }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
