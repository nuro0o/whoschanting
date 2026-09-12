<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    pending: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const target = ref('');
const confirm = ref<'transfer_host' | 'remove_player' | null>(null);
const failure = ref('');
const others = computed(() =>
    props.state.players.filter((player) => player.id !== props.state.me.id),
);
const selected = computed(() =>
    others.value.find((player) => player.id === target.value),
);
watch(
    () =>
        `${props.state.host_id}:${props.state.phase}:${others.value.map((player) => player.id).join(',')}`,
    () => {
        confirm.value = null;
        if (!selected.value) target.value = '';
        failure.value = '';
    },
);
watch(target, () => {
    confirm.value = null;
    failure.value = '';
});
async function apply() {
    if (!confirm.value || !selected.value || props.pending) return;
    failure.value = '';
    if (await props.submit(confirm.value, { target: target.value })) {
        confirm.value = null;
        target.value = '';
    } else
        failure.value =
            props.error ||
            'The host action did not complete. Please try again.';
}
</script>

<template>
    <details
        v-if="state.host_id === state.me.id && others.length"
        class="table-experience table-host-controls"
    >
        <summary>Host controls</summary>
        <p class="table-note">
            Hand the table to another player{{
                state.phase === 'lobby'
                    ? ', or remove a seat before the match starts'
                    : ''
            }}.
        </p>
        <div class="table-form">
            <label for="host-player"
                >Choose a player<select
                    id="host-player"
                    v-model="target"
                    :disabled="pending || !!confirm"
                >
                    <option disabled value="">Select a player</option>
                    <option
                        v-for="player in others"
                        :key="player.id"
                        :value="player.id"
                    >
                        {{ player.name }}
                    </option>
                </select></label
            >
            <div v-if="!confirm" class="table-actions">
                <button
                    class="button"
                    :disabled="pending || !selected"
                    @click="confirm = 'transfer_host'"
                >
                    Transfer host</button
                ><button
                    v-if="state.phase === 'lobby'"
                    class="button"
                    :disabled="pending || !selected"
                    @click="confirm = 'remove_player'"
                >
                    Remove seat
                </button>
            </div>
        </div>
        <div v-if="confirm && selected" class="table-confirm">
            <p>
                <template v-if="confirm === 'transfer_host'"
                    ><strong>Make {{ selected.name }} the host?</strong
                    ><br />They will control the table. You will remain as a
                    player and lose host controls.</template
                ><template v-else
                    ><strong>Remove {{ selected.name }} from this lobby?</strong
                    ><br />Everyone else will need to ready up again. This
                    removes their seat; they can still rejoin.</template
                >
            </p>
            <div class="table-actions">
                <button
                    class="button primary"
                    :disabled="pending"
                    @click="apply"
                >
                    {{
                        pending
                            ? 'Please wait…'
                            : confirm === 'transfer_host'
                              ? 'Confirm transfer'
                              : 'Confirm removal'
                    }}</button
                ><button
                    class="button"
                    :disabled="pending"
                    @click="confirm = null"
                >
                    Cancel
                </button>
            </div>
        </div>
        <p v-if="failure" class="table-error" role="alert">{{ failure }}</p>
    </details>
</template>

<style scoped>
.table-host-controls {
    border-top: 1px solid var(--line);
    margin-top: 16px;
    padding-top: 10px;
}
</style>
