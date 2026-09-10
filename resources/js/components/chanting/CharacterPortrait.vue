<script setup lang="ts">
import { computed } from 'vue';
import { characterIds, defaultCharacters } from '@/lib/chanting';
const props = defineProps<{ character: string; decorative?: boolean }>();
const index = computed(() =>
    Math.max(0, characterIds.indexOf(props.character)),
);
const label = computed(() => defaultCharacters[index.value].name);
</script>
<template>
    <span
        class="character-portrait"
        :role="decorative ? undefined : 'img'"
        :aria-hidden="decorative || undefined"
        :aria-label="decorative ? undefined : label"
        :style="{
            backgroundPosition: `${((index % 4) * 100) / 3}% ${Math.floor(index / 4) * 100}%`,
        }"
    ></span>
</template>
