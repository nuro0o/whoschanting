<script setup lang="ts">
import { computed, useId, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';
import { bargains, bargainPromise, type BargainKind } from '@/lib/faeCourt';
const props = defineProps<{ state: RoomState; disabled: boolean }>();
const target = defineModel<string | null>('target', { required: true });
const kind = defineModel<BargainKind>('kind', { required: true });
const promise = defineModel<string | null>('promise', { required: true });
const gift = defineModel<string | null>('gift', { required: true });
const id = useId();
const recipients = computed(() =>
    props.state.players.filter(
        (player) =>
            player.alive &&
            player.id !== props.state.me.id &&
            !props.state.fae?.bargains.some(
                (bargain) =>
                    bargain.recipient_id === player.id &&
                    bargain.status === 'fulfilled',
            ),
    ),
);
const promises = computed(() =>
    props.state.players.filter(
        (player) => player.alive && player.id !== target.value,
    ),
);
watch([kind, target], () => {
    if (
        kind.value === 'voice' ||
        promise.value === target.value ||
        !target.value
    )
        promise.value = null;
    if (kind.value !== 'voice' || gift.value === target.value || !target.value)
        gift.value = null;
});
</script>

<template>
    <fieldset class="fae-offer" :disabled="disabled">
        <legend>Offer a secret bargain</legend>
        <p>
            Your identity stays hidden. The recipient answers in the private
            window after night actions resolve. Disruption prevents delivery;
            visits can still be tracked.
        </p>
        <label :for="`${id}-recipient`">Recipient</label>
        <select :id="`${id}-recipient`" v-model="target">
            <option :value="null">Keep watch — no offer tonight</option>
            <option
                v-for="player in recipients"
                :key="player.id"
                :value="player.id"
            >
                {{ player.name }}
            </option>
        </select>
        <template v-if="target">
            <label :for="`${id}-kind`">Bargain</label>
            <select :id="`${id}-kind`" v-model="kind">
                <option
                    v-for="(bargain, key) in bargains"
                    :key="key"
                    :value="key"
                >
                    {{ bargain.name }}
                </option>
            </select>
            <p><strong>Your gift:</strong> {{ bargains[kind].gift }}</p>
            <template v-if="kind === 'voice'">
                <label :for="`${id}-gift`">Whose ballot will they learn?</label>
                <select :id="`${id}-gift`" v-model="gift">
                    <option :value="null" disabled>Choose a player</option>
                    <option
                        v-for="player in promises"
                        :key="player.id"
                        :value="player.id"
                    >
                        {{ player.name }}
                    </option>
                </select>
            </template>
            <template v-if="kind !== 'voice'">
                <label :for="`${id}-promise`"
                    >Who must they
                    {{ kind === 'thorn' ? 'vote for' : 'accuse' }}?</label
                >
                <select :id="`${id}-promise`" v-model="promise">
                    <option :value="null" disabled>Choose a player</option>
                    <option
                        v-for="player in promises"
                        :key="player.id"
                        :value="player.id"
                    >
                        {{ player.name }}
                    </option>
                </select>
            </template>
            <p>
                <strong>Their promise:</strong>
                {{
                    bargainPromise(
                        kind,
                        state.players.find((p) => p.id === promise)?.name ??
                            'the chosen player',
                    )
                }}
            </p>
            <p class="small-help">
                One fulfilled bargain per partner earns a seal. Refusals, missed
                responses and broken promises earn nothing. You can try an
                unsuccessful partner again on a later night.
            </p>
        </template>
    </fieldset>
</template>

<style scoped>
.fae-offer {
    display: grid;
    gap: 12px;
    border: 1px solid #a9b68966;
    padding: 18px;
    margin-block: 16px;
}
.fae-offer legend {
    padding-inline: 8px;
    font-weight: 600;
}
.fae-offer label {
    font-size: 13px;
    font-weight: 600;
}
.fae-offer select {
    width: 100%;
    min-height: 44px;
    background: var(--paper, #f4eee1);
    color: #263a31;
    border: 1px solid #647861;
    padding: 10px;
    border-radius: 6px;
}
.fae-offer p {
    line-height: 1.65;
}
</style>
