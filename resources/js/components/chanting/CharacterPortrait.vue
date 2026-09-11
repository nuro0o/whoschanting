<script setup lang="ts">
import { computed } from 'vue';
import { characterIds, defaultCharacters } from '@/lib/chanting';
import { cosmeticAccents } from '@/lib/progression';
const props = defineProps<{
    character: string;
    decorative?: boolean;
    frame?: string;
    accent?: string;
}>();
const index = computed(() =>
    Math.max(0, characterIds.indexOf(props.character)),
);
const label = computed(() => defaultCharacters[index.value].name);
const tileIndex = computed(() => index.value % 8);
</script>
<template>
    <span
        class="character-portrait"
        :class="{
            'character-portrait--hooded': index >= 8,
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
            backgroundPosition: `${((tileIndex % 4) * 100) / 3}% ${Math.floor(tileIndex / 4) * 100}%`,
        }"
    ></span>
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
    border-color: var(--portrait-accent);
}
</style>
