<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { BookOpen, Eye, EyeOff, LockKeyhole } from '@lucide/vue';
import { roles, type RoomState } from '@/lib/chanting';
import { hasLimitedAbility } from '@/lib/roleActions';
import { usePanelMotion } from '@/composables/usePanelMotion';
const panel = usePanelMotion();
defineProps<{
    state: RoomState;
    newResults: number;
}>();
const emit = defineEmits<{ hide: []; journal: [] }>();
const revealed = defineModel<boolean>({ default: false });
const revealButton = ref<HTMLButtonElement>();
const justRevealed = ref(false);
watch(revealed, (visible) => {
    justRevealed.value = visible;
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
</script>
<template>
    <section
        ref="panel"
        class="game-panel role-panel"
        aria-label="Your private role"
    >
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
                            ? 'Fill the ritual and have at least one cultist survive the final vote, eliminate the town, or reach a final pair of one cultist and one town player.'
                            : 'Find and banish every cultist. If the ritual fills, you still have one final discussion and vote to stop the summoning.'
                    }}
                </p>
            </div>
            <button
                class="button investigation-toggle"
                @click="emit('journal')"
            >
                <BookOpen :size="16" /> Open table journal
                <span v-if="newResults" class="room-badge"
                    >{{ newResults }} new</span
                >
            </button>
            <div class="role-details">
                <p class="eyebrow">YOUR ABILITY</p>
                <p>{{ roles[state.me.role ?? '']?.description }}</p>
                <p
                    v-if="hasLimitedAbility(state.me.role)"
                    class="private-separator"
                >
                    <strong>{{
                        state.me.ability_used
                            ? 'Once-per-match ability spent.'
                            : 'Once-per-match ability available.'
                    }}</strong>
                    {{
                        state.me.role === 'exorcist'
                            ? 'Use it during discussion in Play.'
                            : 'Choose whether to use it in your night action in Play.'
                    }}
                </p>
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
