<script setup lang="ts">
import { computed } from 'vue';
import type { RoomState } from '@/lib/chanting';
import { bargains, bargainPromise, bargainGift } from '@/lib/faeCourt';
const props = defineProps<{
    state: RoomState;
    revealed: boolean;
    disabled: boolean;
}>();
const emit = defineEmits<{
    respond: [fields: { bargain_id: string; accept: boolean }];
    reveal: [];
}>();
const offer = computed(() =>
    props.state.fae?.bargains.find(
        (b) =>
            b.day === props.state.day &&
            b.recipient_id === props.state.me.id &&
            b.status === 'offered',
    ),
);
const answered = computed(() =>
    props.state.fae?.bargains.find(
        (b) =>
            b.day === props.state.day &&
            b.recipient_id === props.state.me.id &&
            ['accepted', 'declined'].includes(b.status),
    ),
);
function name(id: string | null | undefined) {
    return (
        props.state.players.find((p) => p.id === id)?.name ??
        'the chosen player'
    );
}
</script>

<template>
    <section
        v-if="state.fae"
        class="game-panel fae-court-panel"
        aria-label="The Fae Court"
    >
        <div class="fae-heading">
            <h3>The Fae Court</h3>
            <strong>{{ state.fae.seals }} / {{ state.fae.goal }} seals</strong>
        </div>
        <p v-if="revealed && state.me.fae_protected" role="status">
            Your accepted bargain protects you from all new curses tonight.
        </p>
        <details class="fae-rules">
            <summary>How the Court shares victory</summary>
            <p class="small-help">
                The Court needs {{ state.fae.goal }} different fulfilled
                partners across at least {{ state.fae.minimum_rounds }} rounds,
                including someone on the winning side. Its victory is decided at
                match end, even if the Fae is banished.
            </p>
        </details>
        <template v-if="state.phase === 'bargains'">
            <p>
                The private bargain window stays open for everyone until the
                timer ends. Unanswered offers expire.
            </p>
            <button
                v-if="!revealed && state.me.alive"
                class="button"
                @click="emit('reveal')"
            >
                Check my private bargains
            </button>
            <div
                v-else-if="offer && state.me.alive"
                class="fae-private"
                aria-live="polite"
            >
                <p class="eyebrow">A PRIVATE OFFER FROM THE COURT</p>
                <h4>{{ bargains[offer.kind].name }}</h4>
                <p>
                    <strong>You receive:</strong>
                    {{ bargainGift(offer, name(offer.gift_target)) }}
                </p>
                <p>
                    <strong>You promise:</strong>
                    {{ bargainPromise(offer.kind, name(offer.promise_target)) }}
                </p>
                <p>
                    You can break your promise and keep the gift. Fulfilling it
                    earns the Court a seal. Your role and faction stay the same.
                </p>
                <div class="fae-buttons">
                    <button
                        class="button primary"
                        :disabled="disabled"
                        @click="
                            emit('respond', {
                                bargain_id: offer.id,
                                accept: true,
                            })
                        "
                    >
                        Accept the bargain
                    </button>
                    <button
                        class="button"
                        :disabled="disabled"
                        @click="
                            emit('respond', {
                                bargain_id: offer.id,
                                accept: false,
                            })
                        "
                    >
                        Decline
                    </button>
                </div>
            </div>
            <p v-else-if="revealed && answered" role="status">
                {{
                    answered.status === 'accepted'
                        ? answered.kind === 'voice' && answered.gift_target
                            ? `Bargain accepted. The ballot report about ${name(answered.gift_target)} will appear in your Journal after today’s voting closes.`
                            : 'Bargain accepted. Your gift is yours to keep.'
                        : 'Bargain declined. You received no gift and made no promise.'
                }}
                <template v-if="answered.status === 'accepted'"
                    >Your promise:
                    {{
                        bargainPromise(
                            answered.kind,
                            name(answered.promise_target),
                        )
                    }}</template
                >
            </p>
            <p v-else-if="revealed">
                No unanswered offer for you tonight. Discussion begins when the
                timer ends.
            </p>
        </template>
        <details
            v-if="revealed && state.fae.bargains.length"
            class="fae-private"
        >
            <summary>Your private bargain record</summary>
            <article
                v-for="bargain in [...state.fae.bargains].reverse()"
                :key="bargain.id"
            >
                <strong
                    >Day {{ bargain.day }} · {{ bargains[bargain.kind].name }} ·
                    {{ bargain.status }}</strong
                >
                <p>
                    Partner: {{ name(bargain.recipient_id) }}.
                    {{
                        bargainPromise(
                            bargain.kind,
                            name(bargain.promise_target),
                        )
                    }}
                </p>
                <p v-if="bargain.kind === 'voice' && bargain.gift_target">
                    Ballot to reveal: {{ name(bargain.gift_target) }}. Accepted
                    reports arrive privately in the recipient’s Journal after
                    that day’s voting closes.
                </p>
            </article>
        </details>
    </section>
</template>

<style scoped>
.fae-court-panel.game-panel {
    border: 1px solid #a5b38988;
    margin-block: 16px;
    padding: 18px;
}
.fae-rules {
    margin-top: 10px;
    font-size: 13px;
    line-height: 1.65;
}
.fae-rules summary {
    cursor: pointer;
    min-height: 32px;
}
.fae-heading,
.fae-buttons {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    flex-wrap: wrap;
    align-items: center;
}
.fae-heading h3 {
    font-size: 22px;
}
.fae-private {
    border-top: 1px solid #a5b38966;
    padding-top: 16px;
    margin-top: 16px;
}
.fae-private h4 {
    font-size: 21px;
}
.fae-private p,
.fae-court-panel > p {
    margin-block: 12px;
    line-height: 1.7;
}
.fae-private article {
    padding-block: 12px;
}
.fae-private summary {
    cursor: pointer;
    min-height: 32px;
}
</style>
