<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Moon, Sun, Vote, Sparkles } from '@lucide/vue';
import type { RoomState } from '@/lib/chanting';

const props = defineProps<{
    phase: RoomState['phase'];
    phaseId: number;
    day: number;
    finalVote: boolean;
}>();
const visible = ref(false);
let timeout: ReturnType<typeof setTimeout> | undefined;
const announcement = computed(() => {
    switch (props.phase) {
        case 'night':
            return {
                title: 'Night falls',
                detail: 'The village keeps its secrets.',
                icon: Moon,
            };
        case 'discussion':
            return {
                title: props.finalVote ? 'One last discussion' : 'Dawn breaks',
                detail: 'Bring your stories to the table.',
                icon: Sun,
            };
        case 'voting':
            return {
                title: props.finalVote
                    ? 'The final vote begins'
                    : 'Voting begins',
                detail: 'Choose carefully. Every voice matters.',
                icon: Vote,
            };
        case 'finished':
            return {
                title: 'The truth comes ashore',
                detail: 'Every secret is now revealed.',
                icon: Sparkles,
            };
        case 'reveal':
            return {
                title: 'Everyone has a secret',
                detail: 'Your private role is waiting.',
                icon: Sparkles,
            };
        default:
            return null;
    }
});
// Watching without immediate avoids playing a transition on refresh or join.
watch(
    () => props.phaseId,
    () => {
        clearTimeout(timeout);
        visible.value = !!announcement.value;
        timeout = setTimeout(() => {
            visible.value = false;
        }, 2800);
    },
);
onBeforeUnmount(() => clearTimeout(timeout));
</script>

<template>
    <div
        class="phase-announcement-region"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <Transition name="phase-arrival">
            <div
                v-if="visible && announcement"
                :key="phaseId"
                class="phase-announcement"
                :data-phase="phase"
            >
                <component
                    :is="announcement.icon"
                    :size="25"
                    aria-hidden="true"
                />
                <div>
                    <span
                        >{{ phase === 'night' ? 'Night' : 'Day' }}
                        {{ day }}</span
                    >
                    <strong>{{ announcement.title }}</strong>
                    <p>{{ announcement.detail }}</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.phase-announcement-region {
    position: fixed;
    z-index: 65;
    inset: 24px 16px auto;
    display: flex;
    justify-content: center;
    pointer-events: none;
}
.phase-announcement {
    display: flex;
    align-items: center;
    gap: 16px;
    max-width: 430px;
    padding: 18px 26px;
    color: #ede5d4;
    background: #20352bf5;
    border: 1px solid #8b9c72;
    box-shadow: 0 14px 50px #050e11a6;
    border-radius: 4px;
}
.phase-announcement[data-phase='night'] {
    background: #192a36f5;
    border-color: #869dad;
}
.phase-announcement[data-phase='voting'] {
    background: #382f29f5;
    border-color: #bf997a;
}
.phase-announcement span {
    font-size: 12px;
    color: #c7d3c0;
}
.phase-announcement strong {
    display: block;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 25px;
    font-weight: 500;
}
.phase-announcement p {
    margin: 5px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: #d1d6c9;
}
.phase-announcement svg {
    flex-shrink: 0;
}
.phase-arrival-enter-active,
.phase-arrival-leave-active {
    transition:
        opacity 400ms ease,
        transform 400ms ease;
}
.phase-arrival-enter-from,
.phase-arrival-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
@media (max-width: 900px) {
    .phase-announcement-region {
        top: 12px;
    }
    .phase-announcement {
        padding: 14px 18px;
    }
    .phase-announcement strong {
        font-size: 22px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .phase-arrival-enter-active,
    .phase-arrival-leave-active {
        transition: none;
    }
    .phase-arrival-enter-from,
    .phase-arrival-leave-to {
        transform: none;
    }
}
</style>
