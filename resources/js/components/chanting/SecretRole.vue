<script setup lang="ts">
import { ref, watch } from 'vue';
import { Eye, EyeOff, LockKeyhole } from '@lucide/vue';
import { roles, type RoomState } from '@/lib/chanting';
defineProps<{ state: RoomState }>();
const emit = defineEmits<{ hide: [] }>();
const revealed = defineModel<boolean>({ default: false });
const justRevealed = ref(false);
watch(revealed, (visible) => {
    justRevealed.value = visible;
});
function revealRole() {
    justRevealed.value = true;
    revealed.value = true;
}
function hideRole() {
    revealed.value = false;
    emit('hide');
}
</script>
<template>
    <section class="game-panel role-panel" aria-label="Your private role">
        <div v-if="!revealed" class="role-cover">
            <span class="role-sigil" aria-hidden="true">◈</span>
            <h2>A secret to keep.</h2>
            <p>
                Your role is for your eyes only. Make sure no curious neighbors
                are looking.
            </p>
            <button class="button" @click="revealRole">
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
            <div
                v-if="state.me.results.length"
                id="role-investigations"
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
            <details class="role-details" :open="state.phase === 'reveal'">
                <summary>Your ability & private notes</summary>
                <p>{{ roles[state.me.role ?? '']?.description }}</p>
                <div v-if="state.me.mission" class="private-separator">
                    <p class="eyebrow">YOUR SHARED MISSION</p>
                    <strong>{{ state.me.mission.name }}</strong>
                    <p>{{ state.me.mission.description }}</p>
                </div>
                <div v-if="state.me.allies.length" class="private-separator">
                    <p class="eyebrow">
                        {{
                            state.me.allies.length === 1
                                ? 'YOUR FELLOW CULTIST'
                                : 'YOUR FELLOW CULTISTS'
                        }}
                    </p>
                    <p v-for="ally in state.me.allies" :key="ally.id">
                        <strong>{{ ally.name }}</strong> ·
                        {{ roles[ally.role]?.name ?? ally.role }}
                    </p>
                </div>
            </details>
        </div>
    </section>
</template>
