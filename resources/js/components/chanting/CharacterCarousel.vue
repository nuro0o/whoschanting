<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import { defaultCharacters, type Character } from '@/lib/chanting';

const props = defineProps<{ characters: Character[] }>();
const featuredIds = [
    'ferryman',
    'prowler',
    'trickster',
    'cartographer',
    'drifter',
    'baker',
];
// Include the preview even when a player's available roster omits an unlock.
const featured = computed(() =>
    featuredIds.map(
        (id) =>
            props.characters.find((character) => character.id === id) ??
            defaultCharacters.find((character) => character.id === id)!,
    ),
);
const activeIndex = ref(0);
const activeCharacter = computed(() => featured.value[activeIndex.value]);
let touchStart: { x: number; y: number } | undefined;

function offset(index: number) {
    const total = featuredIds.length;
    return (
        ((index - activeIndex.value + total + total / 2) % total) - total / 2
    );
}

function move(direction: number) {
    activeIndex.value =
        (activeIndex.value + direction + featuredIds.length) %
        featuredIds.length;
}

function onKeydown(event: KeyboardEvent) {
    switch (event.key) {
        case 'ArrowLeft':
            move(-1);
            break;
        case 'ArrowRight':
            move(1);
            break;
        case 'Home':
            activeIndex.value = 0;
            break;
        case 'End':
            activeIndex.value = featuredIds.length - 1;
            break;
        default:
            return;
    }
    event.preventDefault();
}

function onTouchStart(event: TouchEvent) {
    const touch = event.touches[0];
    touchStart =
        event.touches.length === 1
            ? { x: touch.clientX, y: touch.clientY }
            : undefined;
}

function onTouchEnd(event: TouchEvent) {
    if (!touchStart) return;
    const touch = event.changedTouches[0];
    const deltaX = touch.clientX - touchStart.x;
    const deltaY = touch.clientY - touchStart.y;
    touchStart = undefined;
    if (Math.abs(deltaX) > 40 && Math.abs(deltaX) > Math.abs(deltaY)) {
        move(deltaX < 0 ? 1 : -1);
    }
}
</script>

<template>
    <section class="neighbors-preview" aria-labelledby="character-title">
        <div class="neighbors-intro">
            <p class="eyebrow">A FAMILIAR FACE. AN UNFAMILIAR ALIBI.</p>
            <h2 id="character-title">Meet your <em>neighbors.</em></h2>
            <p class="neighbors-collection">
                <strong>20+ characters</strong>
                <span>with plenty more faces to unlock.</span>
            </p>
        </div>
        <div
            class="neighbors-carousel"
            role="region"
            aria-roledescription="carousel"
            aria-label="Featured neighbors"
            tabindex="0"
            @keydown="onKeydown"
        >
            <div
                class="neighbors-stage"
                @touchstart.passive="onTouchStart"
                @touchend.passive="onTouchEnd"
                @touchcancel="touchStart = undefined"
            >
                <div
                    v-for="(character, index) in featured"
                    :key="character.id"
                    class="neighbor-slide"
                    :class="{
                        'is-active': index === activeIndex,
                        'is-distant': Math.abs(offset(index)) === 2,
                        'is-hidden': Math.abs(offset(index)) > 2,
                    }"
                    :style="{ '--offset': offset(index) }"
                    role="group"
                    aria-roledescription="slide"
                    :aria-label="`${index + 1} of ${featured.length}: ${character.name}`"
                    :aria-hidden="index !== activeIndex"
                >
                    <button
                        type="button"
                        class="neighbor-face"
                        :aria-label="`Show ${character.name}`"
                        tabindex="-1"
                        @click="activeIndex = index"
                    >
                        <CharacterPortrait
                            :character="character.id"
                            decorative
                        />
                        <span class="neighbor-name">{{ character.name }}</span>
                    </button>
                </div>
            </div>
            <div class="neighbors-controls">
                <button
                    type="button"
                    class="neighbor-arrow"
                    aria-label="Previous character"
                    @click="move(-1)"
                >
                    <ChevronLeft :size="19" aria-hidden="true" />
                </button>
                <div
                    class="neighbors-dots"
                    role="group"
                    aria-label="Choose a character"
                >
                    <button
                        v-for="(character, index) in featured"
                        :key="character.id"
                        type="button"
                        class="neighbor-dot"
                        :aria-label="`Show ${character.name}, ${index + 1} of ${featured.length}`"
                        :aria-current="
                            index === activeIndex ? 'true' : undefined
                        "
                        @click="activeIndex = index"
                    >
                        <span></span>
                    </button>
                </div>
                <button
                    type="button"
                    class="neighbor-arrow"
                    aria-label="Next character"
                    @click="move(1)"
                >
                    <ChevronRight :size="19" aria-hidden="true" />
                </button>
            </div>
            <p class="neighbors-position" aria-live="polite" aria-atomic="true">
                <span class="sr-only"
                    >{{ activeCharacter.name }}. Character
                </span>
                {{ String(activeIndex + 1).padStart(2, '0') }}
                <span aria-hidden="true">/</span><span class="sr-only">of</span>
                {{ String(featured.length).padStart(2, '0') }}
            </p>
        </div>
        <p class="neighbors-note">
            Pick a face you love when you sign in. Guests get a surprise.<br />
            Every character can have any role. Trust nobody’s wardrobe.
        </p>
    </section>
</template>

<style scoped>
.neighbors-preview {
    max-width: 1260px;
    margin-inline: auto;
    padding: 42px 28px 40px;
    border-top: 1px solid #bdcd9c25;
    text-align: center;
}

.neighbors-intro h2 {
    font-size: clamp(30px, 4vw, 44px);
    margin: 7px 0 10px;
}

.neighbors-intro .eyebrow {
    justify-content: center;
}

.neighbors-collection {
    display: flex;
    justify-content: center;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 3px 9px;
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.neighbors-collection strong {
    color: var(--coral);
    font-size: 16px;
    font-weight: 600;
}

.neighbors-carousel {
    max-width: 940px;
    margin: 25px auto 0;
    border-radius: 4px;
}

.neighbors-stage {
    --step: clamp(108px, 16vw, 175px);
    position: relative;
    height: 205px;
    overflow: hidden;
    touch-action: pan-y;
}

.neighbor-slide {
    --scale: 0.8;
    position: absolute;
    top: 8px;
    left: 50%;
    width: 164px;
    transform: translateX(calc(-50% + var(--offset) * var(--step)))
        scale(var(--scale));
    transform-origin: center 72%;
    opacity: 0.7;
    transition:
        transform 350ms ease,
        opacity 350ms ease;
}

.neighbor-slide.is-active {
    --scale: 1;
    z-index: 2;
    opacity: 1;
}

.neighbor-slide.is-distant {
    --scale: 0.66;
    opacity: 0.45;
}

.neighbor-slide.is-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.neighbor-face {
    width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--cream);
}

.neighbor-face :deep(.character-portrait) {
    width: 100%;
    border-radius: 48% 48% 4px 4px;
}

.is-active .neighbor-face :deep(.character-portrait) {
    border-color: var(--coral);
    box-shadow:
        0 0 0 4px #e19b820d,
        0 8px 22px #0004;
}

.neighbor-name {
    display: block;
    margin-top: 10px;
    font-size: 13px;
    white-space: nowrap;
}

.is-active .neighbor-name {
    color: var(--cream);
    font-weight: 600;
}

.neighbors-controls,
.neighbors-dots {
    display: flex;
    align-items: center;
    justify-content: center;
}

.neighbors-controls {
    gap: 14px;
    margin-top: 4px;
}

.neighbor-arrow {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: transparent;
    color: var(--cream);
    transition:
        background 150ms,
        border-color 150ms;
}

.neighbor-arrow:hover {
    border-color: var(--coral);
    background: #e19b8210;
}

.neighbor-dot {
    display: grid;
    place-items: center;
    width: 25px;
    height: 40px;
    padding: 0;
    border: 0;
    background: transparent;
}

.neighbor-dot span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--muted);
}

.neighbor-dot[aria-current='true'] span {
    width: 7px;
    height: 7px;
    background: var(--coral);
    box-shadow: 0 0 0 4px #e19b8214;
}

.neighbor-dot:hover span {
    background: var(--coral);
}

.neighbors-carousel:focus-visible,
.neighbors-carousel button:focus-visible {
    outline: 2px solid var(--coral);
    outline-offset: 4px;
}

.neighbors-position {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 10px;
    font-variant-numeric: tabular-nums;
    letter-spacing: 1px;
}

.neighbors-note {
    margin: 19px 0 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
}

@media (max-width: 600px) {
    .neighbors-preview {
        padding: 32px 18px;
    }

    .neighbors-intro .eyebrow {
        font-size: 9px;
        letter-spacing: 1.5px;
    }

    .neighbors-carousel {
        margin-top: 20px;
    }

    .neighbors-stage {
        --step: 116px;
        height: 185px;
        margin-inline: -18px;
    }

    .neighbor-slide {
        width: 144px;
    }

    .neighbor-slide.is-distant {
        opacity: 0;
        visibility: hidden;
    }

    .neighbors-note {
        font-size: 11px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .neighbor-slide,
    .neighbor-arrow {
        transition: none;
    }
}
</style>
