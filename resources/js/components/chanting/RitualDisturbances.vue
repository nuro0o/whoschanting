<script setup lang="ts">
import { onBeforeUnmount, onMounted, shallowRef, watch } from 'vue';
import {
    RitualDisturbanceDirector,
    type DisturbanceContext,
    type RitualDisturbance,
} from '@/lib/ritualDisturbances';

const props = defineProps<Omit<DisturbanceContext, 'reducedMotion'>>();
const emit = defineEmits<{ change: [event: RitualDisturbance | null] }>();
const event = shallowRef<RitualDisturbance | null>(null);
const director = new RitualDisturbanceDirector();
let timer: ReturnType<typeof setInterval> | undefined;
let motion: MediaQueryList | undefined;
let typingUntil = 0;
function tick() {
    const now = Date.now();
    const next = director.update(
        {
            ...props,
            allowed: props.allowed && !document.hidden && now >= typingUntil,
            reducedMotion: motion?.matches ?? false,
        },
        now,
    );
    if (next !== event.value) {
        event.value = next;
        emit('change', next);
    }
}
function typing(input: KeyboardEvent) {
    if (
        (input.target as Element | null)?.closest(
            'input, textarea, [contenteditable="true"]',
        )
    ) {
        typingUntil = Date.now() + 3000;
        tick();
    }
}
watch(
    () => ({ ...props }),
    () => {
        if (motion) tick();
    },
    { flush: 'sync' },
);
onMounted(() => {
    motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    motion.addEventListener('change', tick);
    document.addEventListener('visibilitychange', tick);
    document.addEventListener('keydown', typing);
    timer = setInterval(tick, 250);
    tick();
});
onBeforeUnmount(() => {
    clearInterval(timer);
    motion?.removeEventListener('change', tick);
    document.removeEventListener('visibilitychange', tick);
    document.removeEventListener('keydown', typing);
    emit('change', null);
});
</script>

<template>
    <div
        v-if="event && event.kind !== 'chat'"
        :key="event.id"
        class="ritual-disturbance"
        :class="[
            `ritual-disturbance--${event.kind}`,
            `ritual-disturbance--${event.side}`,
        ]"
        :style="{ top: `${event.top}%` }"
        aria-hidden="true"
    >
        <span v-if="event.kind === 'glyphs'" class="ritual-disturbance__glyph"
            >∴ ⊙ ⋔ ∴</span
        >
        <span v-else-if="event.kind === 'echo' || event.kind === 'whisper'">{{
            event.text
        }}</span>
        <span v-else class="ritual-disturbance__shadow"></span>
    </div>
</template>

<style scoped>
.ritual-disturbance {
    position: fixed;
    z-index: 40;
    max-width: min(230px, 36vw);
    pointer-events: none;
    user-select: none;
    color: #bed0b3;
    font:
        italic clamp(13px, 1.4vw, 18px) Georgia,
        serif;
    letter-spacing: 0.12em;
    line-height: 1.8;
    text-shadow: 0 0 12px #75a788;
    opacity: 0;
    animation: uneasy-presence 3.2s ease-in-out both;
}
.ritual-disturbance--left {
    left: 3vw;
}
.ritual-disturbance--right {
    right: 3vw;
    text-align: right;
}
.ritual-disturbance__glyph {
    font-size: 34px;
    letter-spacing: 0.25em;
}
.ritual-disturbance__shadow {
    display: block;
    width: clamp(35px, 8vw, 110px);
    height: 220px;
    border-radius: 55% 45% 15% 40%;
    background:
        radial-gradient(ellipse at 50% 20%, #030d0be0 0 14%, transparent 17%),
        radial-gradient(ellipse at 48% 75%, #030d0bb0 10%, transparent 65%);
    filter: blur(5px);
}
.ritual-disturbance--whisper {
    filter: blur(0.4px);
}
@keyframes uneasy-presence {
    0%,
    100% {
        opacity: 0;
        transform: translateY(7px);
    }
    25%,
    70% {
        opacity: 0.58;
        transform: translateY(0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .ritual-disturbance {
        animation: none;
        opacity: 0.45;
    }
}
</style>
