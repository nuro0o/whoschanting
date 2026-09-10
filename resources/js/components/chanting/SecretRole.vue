<script setup lang="ts">
import { Eye, EyeOff, LockKeyhole } from '@lucide/vue';
import { roles, type RoomState } from '@/lib/chanting';
defineProps<{ state: RoomState }>();
const revealed = defineModel<boolean>({ default: false });
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
            <button class="button" @click="revealed = true">
                <Eye :size="15" /> Reveal my role
            </button>
        </div>
        <div
            v-else
            class="role-private"
            :class="{ cult: state.me.alignment === 'cult' }"
        >
            <p class="eyebrow">
                <LockKeyhole :size="12" /> ONLY YOU CAN SEE THIS
            </p>
            <h2>{{ roles[state.me.role ?? '']?.name ?? state.me.role }}</h2>
            <p class="role-subtitle">
                {{ roles[state.me.role ?? '']?.subtitle }}
            </p>
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
            <div v-if="state.me.results.length" class="private-separator">
                <strong>Your investigations</strong>
                <ul class="investigation-list">
                    <li
                        v-for="(result, index) in state.me.results"
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
            <button class="hide-role" @click="revealed = false">
                <EyeOff :size="12" style="display: inline; margin-right: 5px" />
                Hide my secrets
            </button>
        </div>
    </section>
</template>
