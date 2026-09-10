<script setup lang="ts">
import { computed } from 'vue';
import { characterIds, defaultCharacters } from '@/lib/chanting';
const props = defineProps<{ character: string; decorative?: boolean }>();
const index = computed(() =>
    Math.max(0, characterIds.indexOf(props.character)),
);
const label = computed(() => defaultCharacters[index.value].name);
const tileIndex = computed(() => index.value % 8);
</script>
<template>
    <span
        class="character-portrait"
        :class="{ 'character-portrait--hooded': index >= 8 }"
        :role="decorative ? undefined : 'img'"
        :aria-hidden="decorative || undefined"
        :aria-label="decorative ? undefined : label"
        :style="{
            backgroundPosition: `${((tileIndex % 4) * 100) / 3}% ${Math.floor(tileIndex / 4) * 100}%`,
        }"
    ></span>
</template>
