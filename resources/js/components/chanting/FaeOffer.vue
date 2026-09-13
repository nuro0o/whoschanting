<script setup lang="ts">
import { computed, useId, watch } from 'vue';
import type { RoomState } from '@/lib/chanting';
import {
    bargains,
    faeRecipients,
    bargainPromise,
    offerableBargains,
    type BargainKind,
} from '@/lib/faeCourt';
const props = defineProps<{ state: RoomState; disabled: boolean }>();
const target = defineModel<string | null>('target', { required: true });
const kind = defineModel<BargainKind>('kind', { required: true });
const promise = defineModel<string | null>('promise', { required: true });
const gift = defineModel<string | null>('gift', { required: true });
const renewal = defineModel<string | null>('renewal', { default: null });
const collector = computed(() => props.state.me.role === 'fae_collector');
const failed = computed(
    () =>
        props.state.fae?.bargains.filter((b) =>
            props.state.fae?.renewable_ids?.includes(b.id),
        ) ?? [],
);
const original = computed(() =>
    failed.value.find((b) => b.id === renewal.value),
);
watch(renewal, () => {
    target.value = null;
});
const id = useId();
const recipients = computed(() => faeRecipients(props.state, renewal.value));
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
        <legend>
            {{
                collector ? 'Renew a failed bargain' : 'Offer a secret bargain'
            }}
        </legend>
        <template v-if="collector">
            <p>
                Once per match, renew a failed bargain with a different player.
                Its original gift and promise stay the same. Your renewal is
                spent when confirmed, even if disrupted or the offer becomes
                void.
            </p>
            <p v-if="state.fae?.collector_used">
                Your renewal is spent. You can keep watch and help the Court
                through discussion and voting.
            </p>
            <template v-else>
                <label :for="`${id}-renewal`">Failed bargain</label>
                <select :id="`${id}-renewal`" v-model="renewal">
                    <option :value="null">
                        Keep watch - save your renewal
                    </option>
                    <option v-for="b in failed" :key="b.id" :value="b.id">
                        Day {{ b.day }} / {{ bargains[b.kind].name }} /
                        {{
                            state.players.find((p) => p.id === b.recipient_id)
                                ?.name
                        }}
                        / {{ b.status }}
                    </option>
                </select>
                <p v-if="!failed.length">
                    No eligible failed bargains yet. Wait for a declined,
                    expired or broken offer from an earlier night.
                </p>
                <p v-if="original">
                    <strong>Original gift:</strong>
                    {{ bargains[original.kind].gift
                    }}<span v-if="original.gift_target">
                        Ballot to reveal:
                        {{
                            state.players.find(
                                (p) => p.id === original?.gift_target,
                            )?.name
                        }}.</span
                    >
                </p>
                <p v-if="original">
                    <strong>Original promise:</strong>
                    {{
                        bargainPromise(
                            original.kind,
                            state.players.find(
                                (p) => p.id === original?.promise_target,
                            )?.name ?? 'the chosen player',
                        )
                    }}
                </p>
            </template>
        </template>
        <p>
            Your identity stays hidden. The recipient answers in the private
            window after night actions resolve. Disruption prevents delivery;
            visits can still be tracked.
        </p>
        <label :for="`${id}-recipient`">Recipient</label>
        <select
            :id="`${id}-recipient`"
            v-model="target"
            :disabled="collector && (!original || state.fae?.collector_used)"
        >
            <option :value="null">Keep watch — no offer tonight</option>
            <option
                v-for="player in recipients"
                :key="player.id"
                :value="player.id"
            >
                {{ player.name }}
            </option>
        </select>
        <template v-if="target && !collector">
            <label :for="`${id}-kind`">Bargain</label>
            <select :id="`${id}-kind`" v-model="kind">
                <option
                    v-for="key in offerableBargains"
                    :key="key"
                    :value="key"
                >
                    {{ bargains[key].name }}
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
                    {{ kind === 'lantern' ? 'accuse' : 'vote for' }}?</label
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
