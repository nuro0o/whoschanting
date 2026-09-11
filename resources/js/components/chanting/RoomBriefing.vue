<script setup lang="ts">
import { computed } from 'vue';
import { Check } from '@lucide/vue';
import type { RoomState } from '@/lib/chanting';
import { usePanelMotion } from '@/composables/usePanelMotion';
const panel = usePanelMotion();
const props = defineProps<{
    state: RoomState;
    pending: boolean;
    revealed: boolean;
    roleRead: boolean;
}>();
const step = computed(() => {
    const { phase, me } = props.state;
    if (phase === 'lobby')
        return [
            'Get everyone ready',
            'Invite your friends, then mark yourself ready.',
        ];
    if (phase === 'finished')
        return [
            props.state.winner === 'cult'
                ? 'The cult prevails.'
                : 'The town prevails.',
            props.state.win_reason ||
                'Every role is now face-up around the table.',
        ];
    if (!me.alive)
        return [
            'You are watching',
            phase === 'night'
                ? 'The living are making their moves. Wait for dawn.'
                : phase === 'voting'
                  ? 'The living are deciding who to banish. The result arrives shortly.'
                  : 'Follow the chat and public events until the next match.',
        ];
    if (me.submitted)
        return phase === 'reveal'
            ? ['You are ready', 'Waiting for everyone to read their role.']
            : phase === 'night'
              ? ['Action submitted', 'Waiting for dawn. Your choice is final.']
              : phase === 'discussion'
                ? ['Ready for voting', 'Keep talking while everyone finishes.']
                : [
                      'Vote submitted',
                      'Waiting for the result. Your choice is final.',
                  ];
    if (phase === 'reveal')
        return [
            'Read your private role',
            props.roleRead
                ? 'Keep your objective, ability and team secret. Confirm when ready.'
                : 'Reveal your card and read your objective, ability and team first.',
        ];
    if (phase === 'night')
        return [
            'Make your night move',
            props.revealed
                ? 'Choose your action below, then confirm. Your choice is final.'
                : 'Your role and night action stay hidden until you reveal them.',
        ];
    if (props.state.ritual.final_vote)
        return phase === 'discussion'
            ? [
                  'One last discussion',
                  'The ritual is full. Compare stories before the final vote.',
              ]
            : [
                  'The final vote',
                  'Banish every remaining cultist now. If any survive this vote, the cult wins.',
              ];
    if (phase === 'discussion')
        return [
            'Discuss with the village',
            'Compare stories in chat or on a call. Voting starts when everyone is ready or the timer ends.',
        ];
    return [
        'Choose, then confirm your vote',
        'Select a living player or Abstain. Selecting alone never submits.',
    ];
});
</script>
<template>
    <section
        ref="panel"
        id="your-turn"
        class="game-panel your-turn"
        aria-labelledby="turn-heading"
        tabindex="-1"
    >
        <h2 v-if="state.phase === 'lobby'" id="turn-heading" class="sr-only">
            Get everyone ready
        </h2>
        <div v-else id="current-action" class="phase-control-row" tabindex="-1">
            <div class="phase-summary">
                <div class="turn-heading">
                    <p class="eyebrow">
                        <Check v-if="state.me.submitted" :size="12" />
                        {{
                            state.phase === 'finished'
                                ? 'MATCH COMPLETE'
                                : state.phase
                        }}
                    </p>
                    <span v-if="pending" class="turn-pending" role="status"
                        >Sending…</span
                    >
                </div>
                <div class="turn-status" role="status" aria-atomic="true">
                    <h2 id="turn-heading">{{ step[0] }}</h2>
                    <p>{{ step[1] }}</p>
                    <p
                        v-if="state.phase === 'discussion' && state.me.alive"
                        class="phase-readiness"
                    >
                        {{
                            state.players.filter(
                                (player) =>
                                    player.alive && player.discussion_ready,
                            ).length
                        }}
                        of
                        {{
                            state.players.filter((player) => player.alive)
                                .length
                        }}
                        ready for voting. This choice is final.
                    </p>
                    <p
                        v-if="
                            state.phase === 'finished' &&
                            state.host_id !== state.me.id
                        "
                    >
                        Your host can start a new gathering. Your seat is saved.
                    </p>
                </div>
            </div>
            <div class="phase-buttons"><slot name="controls" /></div>
        </div>
        <slot />
    </section>
</template>

<style scoped>
.phase-control-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px 24px;
}
.phase-summary {
    flex: 1 1 240px;
    min-width: 0;
}
.turn-heading {
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 8px 14px;
    margin-bottom: 6px;
}
.turn-heading .eyebrow {
    color: #b7c6a7;
    font-size: 9px;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}
.turn-pending {
    min-width: 0;
    margin: 0;
    color: #b9c5b8;
    font-size: 10px;
}
.phase-buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    max-width: 100%;
}
.phase-buttons:empty {
    display: none;
}
@media (max-width: 900px) {
    .phase-control-row {
        gap: 14px;
    }
    .phase-buttons {
        flex: 1 1 100%;
    }
    .phase-buttons :deep(.button) {
        flex: 1;
    }
}
</style>
