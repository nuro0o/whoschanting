<script setup lang="ts">
import { computed } from 'vue';
import CreatedCharacter from './CreatedCharacter.vue';
import type { CreatorRecipe } from '@/lib/creator';
import { characterIds, defaultCharacters } from '@/lib/chanting';
import { cosmeticAccents, cosmeticBackgrounds } from '@/lib/progression';
const props = defineProps<{
    character: string;
    creator?: CreatorRecipe | null;
    decorative?: boolean;
    frame?: string;
    accent?: string;
    background?: string;
}>();
const index = computed(() =>
    Math.max(0, characterIds.indexOf(props.character)),
);
const portraitImages: Record<string, string> = {
    tidecaller: '/assets/chanting/unlocks/tidecaller.png',
    cartographer: '/assets/chanting/unlocks/cartographer.png',
    maskmaker: '/assets/chanting/unlocks/maskmaker.png',
    drowned_regent: '/assets/chanting/unlocks/drowned_regent.png',
};
const portraitImage = computed(() => portraitImages[props.character]);
const label = computed(() =>
    props.character === 'custom'
        ? 'Your created villager'
        : defaultCharacters[index.value].name,
);
const tileIndex = computed(() => index.value % 8);
const backdrop = computed(() =>
    props.background && props.background !== 'plain'
        ? cosmeticBackgrounds[props.background]
        : undefined,
);
const artwork = computed(() => {
    if (props.character === 'custom')
        return {
            backgroundImage:
                'radial-gradient(ellipse at 50% 35%, #64776a, #213b3b)',
        };
    const standalone =
        portraitImage.value ||
        (['ferryman', 'trickster'].includes(props.character)
            ? `/assets/chanting/${props.character === 'ferryman' ? 'ferryman-shadow' : 'trickster-clown'}.png`
            : undefined);
    return {
        backgroundImage: `url(${standalone ?? `/assets/chanting/${index.value >= 8 && index.value < 16 ? 'characters-hooded-reimagined-illustrated' : 'characters-reimagined-illustrated'}.png`})`,
        backgroundSize: portraitImage.value
            ? 'cover'
            : standalone
              ? '100% 100%'
              : '400% 200%',
        backgroundPosition: standalone
            ? 'center'
            : `${((tileIndex.value % 4) * 100) / 3}% ${Math.floor(tileIndex.value / 4) * 100}%`,
    };
});
</script>
<template>
    <span
        class="character-portrait"
        :class="{
            'character-portrait--hooded': index >= 8 && index < 16,
            'character-portrait--ferryman': character === 'ferryman',
            'character-portrait--trickster': character === 'trickster',
            'portrait-frame-copper': frame === 'copper',
            'portrait-frame-lantern': frame === 'lantern',
            'portrait-frame-tidal': frame === 'tidal',
        }"
        :role="decorative ? undefined : 'img'"
        :aria-hidden="decorative || undefined"
        :aria-label="decorative ? undefined : label"
        :style="{
            '--portrait-accent':
                cosmeticAccents[accent ?? 'sea'] ?? cosmeticAccents.sea,
            ...artwork,
            ...(backdrop
                ? {
                      backgroundImage: backdrop,
                      backgroundSize: 'cover',
                      backgroundPosition: 'center',
                  }
                : {}),
        }"
    >
        <CreatedCharacter
            v-if="character === 'custom'"
            :recipe="creator"
            :class="{ 'created-with-backdrop': backdrop }"
        />
        <span
            v-else-if="backdrop"
            class="portrait-artwork"
            :style="artwork"
        ></span>
    </span>
</template>
<style scoped>
.character-portrait.portrait-frame-copper {
    outline: 2px solid #b58561;
    outline-offset: 2px;
    box-shadow: 0 0 0 5px #4d3830;
}
.character-portrait.portrait-frame-lantern {
    outline: 2px solid var(--portrait-accent);
    outline-offset: 3px;
    box-shadow:
        0 0 0 6px #263831,
        0 0 16px color-mix(in srgb, var(--portrait-accent) 40%, transparent);
}
.character-portrait.portrait-frame-tidal {
    outline: 3px double var(--portrait-accent);
    outline-offset: 4px;
    box-shadow: 0 0 0 8px #203c42;
}
.character-portrait {
    position: relative;
    border-color: var(--portrait-accent);
}
:global(.table-seat.is-me .character-portrait) {
    outline: 1px solid #b7c994;
    outline-offset: 3px;
}
:global(.table-seat.is-selectable:not(.is-selected):hover .character-portrait) {
    border-color: #e7eccb;
}
:global(.table-seat.is-selected .character-portrait) {
    border-color: #f1c49f;
    outline: 2px solid #f1c49f;
    outline-offset: 3px;
}
.created-with-backdrop {
    inset: 8%;
}
.portrait-artwork {
    position: absolute;
    inset: 8%;
    border-radius: inherit;
    background-repeat: no-repeat;
    box-shadow: 0 1px 4px #08151566;
    border: 1px solid
        color-mix(in srgb, var(--portrait-accent) 70%, transparent);
}
</style>
