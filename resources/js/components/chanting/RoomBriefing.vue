<script setup lang="ts">
import { computed } from 'vue';
import {
    ArrowDown,
    Check,
    Eye,
    EyeOff,
    LockKeyhole,
    ScrollText,
} from '@lucide/vue';
import { roles, type RoomState } from '@/lib/chanting';

const props = defineProps<{ state: RoomState; pending: boolean }>();
const revealed = defineModel<boolean>({ default: false });
const active = computed(
    () => !['lobby', 'finished'].includes(props.state.phase),
);
const latestEvents = computed(() => props.state.log.slice(-2).reverse());
const nextStep = computed(() => {
    const { phase, me } = props.state;
    if (!me.alive)
        return [
            'You are banished',
            'Follow the public events. Your seat is saved for the next match.',
        ];
    if (props.pending)
        return [
            'Sending your choice…',
            'Wait for confirmation before making another move.',
        ];
    if (me.submitted) {
        if (phase === 'reveal')
            return [
                'You are ready',
                'Waiting for everyone to read their role.',
            ];
        if (phase === 'night')
            return [
                'Your action is sealed',
                'Nothing more to do. Your next update arrives at dawn.',
            ];
        if (phase === 'discussion')
            return [
                'Ready for voting',
                'Voting starts when everyone is ready or time runs out.',
            ];
        return ['Your vote is sealed', 'Wait here for the village’s decision.'];
    }
    if (phase === 'reveal')
        return [
            'Read your role, then get ready',
            'Reveal your private card and confirm “I know who I am” below.',
        ];
    if (me.curse?.type === 'mist')
        return [
            'Clear the mist',
            'Complete the focus challenge below to see clearly again.',
        ];
    if (me.curse?.type === 'puzzle')
        return [
            'A curse needs your attention',
            'Solve the challenge below to unlock targets. You can still act without a target when allowed.',
        ];
    if (
        me.curse?.type === 'misdirection' &&
        ['night', 'voting'].includes(phase)
    )
        return [
            'Check your curse before choosing',
            'Misdirection can change your target. Read the warning by your action.',
        ];
    if (phase === 'night')
        return [
            'Make your night move',
            revealed.value
                ? 'Choose your action below and confirm it before dawn.'
                : 'Reveal your role to see your private night action.',
        ];
    if (phase === 'discussion')
        return [
            'Talk it through',
            'Compare stories, then mark yourself ready for voting below.',
        ];
    return [
        'Cast your vote',
        'Choose a living player or abstain, then confirm below.',
    ];
});
</script>

<template>
    <section class="room-briefing" aria-label="Your village briefing">
        <div v-if="active" class="briefing-personal">
            <div class="briefing-role">
                <span class="briefing-icon" aria-hidden="true"
                    ><LockKeyhole :size="20"
                /></span>
                <div class="briefing-role-copy">
                    <p class="eyebrow">YOUR PRIVATE ROLE</p>
                    <strong>{{
                        revealed
                            ? (roles[state.me.role ?? '']?.name ?? 'Your role')
                            : 'Your role is hidden'
                    }}</strong>
                    <a
                        v-if="revealed"
                        href="#private-role"
                        class="briefing-link"
                        >Role & win objective <ArrowDown :size="12"
                    /></a>
                    <span v-else>Role, team & private notes</span>
                </div>
                <button
                    id="role-visibility-toggle"
                    class="button briefing-reveal"
                    :class="{ primary: !revealed }"
                    :aria-expanded="revealed"
                    aria-controls="private-role"
                    @click="revealed = !revealed"
                >
                    <EyeOff v-if="revealed" :size="16" /><Eye
                        v-else
                        :size="16"
                    />
                    {{ revealed ? 'Hide secrets' : 'Reveal my role' }}
                </button>
            </div>
            <div class="briefing-next" role="status" aria-atomic="true">
                <p class="eyebrow">
                    <Check v-if="state.me.submitted" :size="13" />{{
                        state.me.submitted ? 'ALL SET' : 'WHAT TO DO NOW'
                    }}
                </p>
                <strong>{{ nextStep[0] }}</strong>
                <p>{{ nextStep[1] }}</p>
                <a
                    v-if="state.me.alive && !state.me.submitted"
                    href="#current-action"
                    class="briefing-link"
                    >Go to your action <ArrowDown :size="12"
                /></a>
                <a
                    v-if="revealed && state.me.results.length"
                    href="#role-investigations"
                    class="briefing-link"
                    >Read your private investigations <ArrowDown :size="12"
                /></a>
            </div>
        </div>
        <div class="briefing-events">
            <div class="briefing-events-heading">
                <h2>
                    <ScrollText :size="16" aria-hidden="true" /> What just
                    happened
                </h2>
                <span>Public · newest first</span>
            </div>
            <ol
                v-if="latestEvents.length"
                class="latest-events"
                aria-label="Latest public events"
                aria-live="polite"
                aria-relevant="additions text"
            >
                <li
                    v-for="(entry, index) in latestEvents"
                    :key="`${state.log.length - index}:${entry}`"
                >
                    {{ entry }}
                </li>
            </ol>
            <p v-else class="briefing-empty">
                The village waits for its story to begin.
            </p>
            <details v-if="state.log.length" class="event-history">
                <summary>
                    Full public history
                    <span>{{ state.log.length }} events</span>
                </summary>
                <ol
                    class="events-list"
                    aria-label="Full public history, newest first"
                    tabindex="0"
                >
                    <li
                        v-for="(entry, index) in [...state.log].reverse()"
                        :key="index"
                    >
                        {{ entry }}
                    </li>
                </ol>
            </details>
        </div>
    </section>
</template>
