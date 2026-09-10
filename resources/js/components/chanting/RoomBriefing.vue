<script setup lang="ts">
import { computed } from 'vue';
import { Check, Clock3 } from '@lucide/vue';
import type { RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    pending: boolean;
    timeLabel: string;
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
            'Match complete',
            'Review the revealed roles and start another game.',
        ];
    if (!me.alive)
        return [
            'You are watching',
            'Follow the chat and public events until the next match.',
        ];
    if (props.pending)
        return ['Sending your choice…', 'Wait for confirmation.'];
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
            'Open My role, read your objective, then confirm you are ready.',
        ];
    if (phase === 'night')
        return [
            'Make your night move',
            'Reveal your secrets to choose and confirm your private action.',
        ];
    if (phase === 'discussion')
        return [
            'Discuss with the village',
            'Compare stories in Chat, then mark yourself ready for voting.',
        ];
    return [
        'Choose, then confirm your vote',
        'Select a living player or Abstain. Selecting alone never submits.',
    ];
});
</script>
<template>
    <section
        id="your-turn"
        class="game-panel your-turn"
        aria-labelledby="turn-heading"
        tabindex="-1"
    >
        <div class="turn-heading">
            <p class="eyebrow">
                <Check v-if="state.me.submitted" :size="14" /> YOUR TURN
            </p>
            <span v-if="timeLabel" class="turn-time"
                ><Clock3 :size="14" /> {{ timeLabel }} left</span
            >
        </div>
        <div class="turn-status" role="status" aria-atomic="true">
            <h2 id="turn-heading">{{ step[0] }}</h2>
            <p>{{ step[1] }}</p>
        </div>
        <slot />
    </section>
</template>
