<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';

const props = defineProps<{
    state: RoomState;
    pending: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const pin = ref('');
const notice = ref('');
const failure = ref('');
const host = computed(() => props.state.host_id === props.state.me.id);
watch(
    [
        () => props.state.host_id,
        () => props.state.phase,
        () => props.state.code,
    ],
    () => {
        pin.value = '';
        notice.value = '';
        failure.value = '';
    },
);
async function save(remove = false) {
    if (props.pending || (!remove && !/^[0-9]{4,8}$/.test(pin.value))) return;
    notice.value = '';
    failure.value = '';
    if (await props.submit('set_pin', { pin: remove ? null : pin.value })) {
        pin.value = '';
        notice.value = remove
            ? 'PIN removed. New players can join with the room code.'
            : 'PIN saved. Share it privately with your friends.';
    } else
        failure.value =
            props.error || 'The PIN could not be updated. Try again.';
}
</script>

<template>
    <section
        v-if="state.phase === 'lobby'"
        class="lobby-pin"
        aria-label="Lobby access"
    >
        <strong>{{
            state.pin_required ? 'PIN required to join' : 'No lobby PIN'
        }}</strong>
        <p>
            {{
                state.pin_required
                    ? 'Share the PIN separately from your invite link. Players already seated can reconnect.'
                    : 'Anyone with the room code can join. The host can add an optional PIN.'
            }}
        </p>
        <details v-if="host">
            <summary>
                {{
                    state.pin_required ? 'Manage lobby PIN' : 'Add a lobby PIN'
                }}
            </summary>
            <form @submit.prevent="save()">
                <label for="host-lobby-pin">{{
                    state.pin_required ? 'New PIN' : 'Choose a PIN'
                }}</label>
                <input
                    id="host-lobby-pin"
                    v-model="pin"
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]{4,8}"
                    minlength="4"
                    maxlength="8"
                    autocomplete="new-password"
                    required
                    aria-describedby="host-pin-help"
                    :disabled="pending"
                />
                <p id="host-pin-help">
                    Use 4–8 digits. Changes apply to new players joining this
                    lobby.
                </p>
                <div class="pin-buttons">
                    <button
                        class="button"
                        :disabled="pending || !/^[0-9]{4,8}$/.test(pin)"
                    >
                        Save PIN
                    </button>
                    <button
                        v-if="state.pin_required"
                        type="button"
                        class="button"
                        :disabled="pending"
                        @click="save(true)"
                    >
                        Remove PIN
                    </button>
                </div>
            </form>
        </details>
        <p v-if="notice" role="status">{{ notice }}</p>
        <p v-if="failure" class="form-error" role="alert">{{ failure }}</p>
    </section>
</template>

<style scoped>
.lobby-pin {
    margin-block: 18px;
    padding: 16px;
    border: 1px solid var(--line);
    border-radius: 4px;
}
p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
    margin: 8px 0;
}
summary {
    padding-block: 8px;
    cursor: pointer;
}
form {
    display: grid;
    gap: 10px;
    margin-top: 10px;
}
input {
    width: 100%;
    min-height: 44px;
    padding: 10px;
    color: var(--cream);
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 4px;
    font: inherit;
}
.pin-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.button {
    min-height: 44px;
}
</style>
