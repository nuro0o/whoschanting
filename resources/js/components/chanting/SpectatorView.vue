<script setup lang="ts">
import { Eye, LockKeyhole } from '@lucide/vue';
import type { RoomState } from '@/lib/chanting';
import RoomEvents from './RoomEvents.vue';
import BanishedPredictions from './BanishedPredictions.vue';

defineProps<{
    state: RoomState;
    privateVisible: boolean;
    blocked: boolean;
    pending: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const emit = defineEmits<{ reveal: []; journal: [] }>();
</script>

<template>
    <section class="game-panel spectator-view" aria-label="Spectator view">
        <p class="eyebrow"><Eye :size="16" /> FROM THE SHORE</p>
        <h2>Your story continues.</h2>
        <p>
            {{
                state.me.elimination_reason === 'guilt'
                    ? 'You left the village in guilt.'
                    : state.me.elimination_reason === 'shot'
                      ? 'You were shot.'
                      : 'You have been banished.'
            }}
            Follow public events and make a private prediction while the village
            plays on.
        </p>
        <p class="next-match">
            Your seat is saved. When this match ends, the host can start a
            rematch in this room and you can ready up again.
        </p>
        <template v-if="!blocked">
            <h3><LockKeyhole :size="16" /> Your private prediction</h3>
            <button
                v-if="!privateVisible"
                class="button"
                @click="emit('reveal')"
            >
                Reveal predictions &amp; notes
            </button>
            <BanishedPredictions
                v-else
                :state="state"
                :pending="pending"
                :error="error"
                :submit="submit"
            />
            <button class="button" @click="emit('journal')">
                Open my private journal
            </button>
            <RoomEvents :log="state.log" />
        </template>
        <p v-else>
            Clear your curse to return to public events and private notes.
        </p>
    </section>
</template>

<style scoped>
.spectator-view > p {
    color: var(--muted);
    line-height: 1.6;
    font-size: 14px;
}
.spectator-view h2 {
    margin: 12px 0;
}
.spectator-view h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 22px;
    font-size: 16px;
}
.next-match {
    padding-left: 12px;
    border-left: 2px solid var(--sea);
}
fieldset {
    padding: 0;
    border: 0;
}
legend {
    margin-block: 12px;
    font-size: 14px;
}
.prediction-player {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    font-size: 14px;
}
input {
    accent-color: var(--sea);
    width: 18px;
    height: 18px;
}
.winner-choice {
    display: grid;
    gap: 8px;
    margin-top: 12px;
    font-size: 14px;
}
select {
    color: var(--cream);
    background: var(--panel);
    padding: 12px;
    border: 1px solid var(--line);
}
.button {
    margin: 12px 8px 0 0;
}
.room-events {
    margin-top: 24px;
    padding: 0;
    border: 0;
}
</style>
