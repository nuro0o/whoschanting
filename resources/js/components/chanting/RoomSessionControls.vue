<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';

const props = defineProps<{
    state: RoomState;
    now: number;
    submit: (type: 'leave' | 'end_room' | 'here') => Promise<boolean>;
}>();
const host = computed(() => props.state.host_id === props.state.me.id);
const hostName = computed(
    () => props.state.players.find((p) => p.id === props.state.host_id)?.name,
);
const playing = computed(
    () => !['lobby', 'finished'].includes(props.state.phase),
);
const needsCheck = computed(
    () =>
        playing.value &&
        props.state.me.alive &&
        !!(props.state.me.afk || props.state.me.afk_prompt_deadline),
);
const seconds = computed(() =>
    Math.max(
        0,
        Math.ceil(
            (Date.parse(props.state.me.afk_prompt_deadline ?? '') - props.now) /
                1000,
        ),
    ),
);
const dialog = ref<HTMLDialogElement>();
const mode = ref<'leave' | 'end_room' | 'afk' | null>(null);
const busy = ref(false);
const failure = ref('');

watch(
    needsCheck,
    (needed) => {
        if (needed && !mode.value) mode.value = 'afk';
        else if (!needed && mode.value === 'afk') mode.value = null;
    },
    { immediate: true },
);
watch(host, (isHost) => {
    if (!isHost && mode.value === 'end_room') mode.value = null;
});
watch(
    mode,
    async (value) => {
        failure.value = '';
        await nextTick();
        if (value && !dialog.value?.open) dialog.value?.showModal();
        else if (!value) dialog.value?.close();
    },
    { immediate: true },
);

async function confirm(type: 'leave' | 'end_room' | 'here') {
    if (busy.value) return;
    busy.value = true;
    try {
        if (await props.submit(type)) mode.value = null;
        else failure.value = 'That did not complete. Please try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="room-session-controls">
        <span class="room-chip"
            >Host: {{ hostName }}{{ host ? ' (you)' : '' }}</span
        >
        <button v-if="needsCheck" class="button" @click="mode = 'afk'">
            I'm here
        </button>
        <button class="button" @click="mode = 'leave'">Leave room</button>
        <button v-if="host" class="button" @click="mode = 'end_room'">
            End room
        </button>
        <dialog
            ref="dialog"
            class="session-dialog"
            aria-labelledby="session-dialog-title"
            @cancel="mode = null"
            @close="mode = null"
        >
            <template v-if="mode === 'afk'">
                <h2 id="session-dialog-title">Are you still here?</h2>
                <p v-if="state.me.afk">
                    You're marked AFK. Your seat is saved, but the village won't
                    wait for your choices.
                </p>
                <p v-else>
                    You missed a few chances to participate. Confirm within
                    {{ seconds }} seconds to keep your turns active.
                </p>
                <p>
                    Voting, submitting a night choice, or joining the discussion
                    also counts as participation.
                </p>
                <div class="table-actions">
                    <button
                        class="button primary"
                        :disabled="busy"
                        @click="confirm('here')"
                    >
                        I'm here
                    </button>
                    <button
                        class="button"
                        :disabled="busy"
                        @click="mode = 'leave'"
                    >
                        Leave room
                    </button>
                </div>
            </template>
            <template v-else-if="mode === 'leave'">
                <h2 id="session-dialog-title">Leave room?</h2>
                <p v-if="playing">
                    Your character and role stay until the match ends. Return to
                    room {{ state.code }} to resume. If you miss choices, you'll
                    be marked AFK and the village will continue without waiting
                    for you.
                </p>
                <p v-else>
                    Your seat will be removed.
                    {{
                        host
                            ? 'Another player will become host, or the room will close if it is empty.'
                            : 'You can join another gathering.'
                    }}
                </p>
                <div class="table-actions">
                    <button
                        class="button primary"
                        :disabled="busy"
                        @click="confirm('leave')"
                    >
                        Leave room
                    </button>
                    <button
                        class="button"
                        :disabled="busy"
                        @click="mode = null"
                    >
                        Stay
                    </button>
                </div>
            </template>
            <template v-else-if="mode === 'end_room'">
                <h2 id="session-dialog-title">End room for everyone?</h2>
                <p>
                    Everyone will leave this room.
                    {{
                        playing
                            ? 'The current match will end without a winner or match rewards.'
                            : 'Players will need to create or join another room.'
                    }}
                </p>
                <div class="table-actions">
                    <button
                        class="button primary"
                        :disabled="busy"
                        @click="confirm('end_room')"
                    >
                        End room for everyone
                    </button>
                    <button
                        class="button"
                        :disabled="busy"
                        @click="mode = null"
                    >
                        Cancel
                    </button>
                </div>
            </template>
            <p v-if="failure" class="table-error" role="alert">{{ failure }}</p>
        </dialog>
    </div>
</template>

<style scoped>
.room-session-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    width: 100%;
}
.room-session-controls .room-chip {
    width: 100%;
    letter-spacing: 0.04em;
    overflow-wrap: anywhere;
}
.room-session-controls > .button {
    min-height: 44px;
    padding: 7px 12px;
    font-size: 12px;
}
.session-dialog {
    margin: auto;
    width: min(480px, calc(100vw - 32px));
    max-height: calc(100dvh - 32px);
    overflow: auto;
    padding: 28px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #141c20;
    color: #ede8dc;
    box-shadow: 0 24px 80px #0008;
}
.session-dialog::backdrop {
    background: #050a10bb;
}
.session-dialog h2 {
    margin-bottom: 16px;
    font-size: 24px;
}
.session-dialog p {
    margin-bottom: 20px;
    line-height: 1.6;
}
@media (max-width: 600px) {
    .room-session-controls {
        justify-content: flex-end;
    }
}
</style>
