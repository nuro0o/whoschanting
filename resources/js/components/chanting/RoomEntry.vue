<script setup lang="ts">
import { ref } from 'vue';
import { ArrowRight, KeyRound, LoaderCircle, Plus } from '@lucide/vue';
import { roomRequest } from '@/lib/chanting';

const props = defineProps<{ initialCode?: string }>();
const mode = ref(props.initialCode ? 'join' : 'create');
const name = ref('');
const code = ref(props.initialCode ?? '');
const pending = ref(false);
const error = ref('');
async function enter() {
    pending.value = true;
    error.value = '';
    try {
        const data = await roomRequest<{ code: string }>(
            mode.value === 'create' ? '/rooms' : '/rooms/join',
            {
                name: name.value.trim(),
                ...(mode.value === 'join'
                    ? { code: code.value.trim().toUpperCase() }
                    : {}),
            },
        );
        window.location.assign(`/rooms/${encodeURIComponent(data.code)}`);
    } catch (cause) {
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Something went wrong. Try again.';
        pending.value = false;
    }
}
</script>

<template>
    <section class="entry-panel" aria-label="Enter the village">
        <div
            v-if="!initialCode"
            class="entry-tabs"
            role="group"
            aria-label="Room action"
        >
            <button
                type="button"
                :class="{ selected: mode === 'create' }"
                :aria-pressed="mode === 'create'"
                @click="
                    mode = 'create';
                    error = '';
                "
            >
                <Plus :size="16" /> Create a room
            </button>
            <button
                type="button"
                :class="{ selected: mode === 'join' }"
                :aria-pressed="mode === 'join'"
                @click="
                    mode = 'join';
                    error = '';
                "
            >
                <KeyRound :size="16" /> Join friends
            </button>
        </div>
        <form class="entry-form" @submit.prevent="enter">
            <label for="player-name">What should the village call you?</label>
            <input
                id="player-name"
                v-model="name"
                required
                maxlength="24"
                autocomplete="nickname"
                placeholder="Your suspiciously innocent name"
                :disabled="pending"
            />
            <template v-if="mode === 'join'">
                <label for="room-code">Secret room code</label>
                <input
                    id="room-code"
                    v-model="code"
                    required
                    maxlength="8"
                    autocapitalize="characters"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="e.g. MIST42"
                    class="code-input"
                    :disabled="pending || !!initialCode"
                />
            </template>
            <p v-if="error" class="form-error" role="alert">{{ error }}</p>
            <button
                class="button primary entry-submit"
                :disabled="
                    pending || !name.trim() || (mode === 'join' && !code.trim())
                "
            >
                <LoaderCircle v-if="pending" :size="18" class="spin" /><template
                    v-else
                    >{{
                        mode === 'create'
                            ? 'Gather your suspects'
                            : 'Enter the village'
                    }}<ArrowRight :size="18"
                /></template>
            </button>
            <p class="entry-note">
                No accounts. Just friends. And a little betrayal.
            </p>
        </form>
    </section>
</template>
