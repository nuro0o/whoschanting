<script setup lang="ts">
import type { Player } from '@/lib/chanting';
import CharacterPortrait from './CharacterPortrait.vue';

defineProps<{ player?: Player; name: string }>();
</script>

<template>
    <span class="recap-villager">
        <CharacterPortrait
            v-if="player"
            :character="player.character"
            :creator="player.customization?.creator"
            :accent="player.customization?.accent"
            :background="player.customization?.background"
            decorative
        />
        <span v-else class="recap-villager-fallback" aria-hidden="true">—</span>
        <strong>{{ name }}</strong>
    </span>
</template>

<style scoped>
.recap-villager {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    gap: 9px;
    color: var(--cream);
    line-height: 1.4;
}
.recap-villager strong {
    min-width: 0;
    overflow-wrap: anywhere;
    font-size: 13px;
    font-weight: 600;
}
.recap-villager :deep(.character-portrait),
.recap-villager-fallback {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    border-radius: 10px;
}
.recap-villager-fallback {
    display: grid;
    place-items: center;
    border: 1px dashed var(--line);
    color: var(--muted);
}
</style>
