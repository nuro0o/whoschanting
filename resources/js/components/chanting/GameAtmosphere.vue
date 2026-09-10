<script setup lang="ts">
import type { RoomState } from '@/lib/chanting';

defineProps<{ phase: RoomState['phase'] }>();
</script>

<template>
    <div
        class="game-atmosphere"
        :class="{ 'is-night': phase === 'night' }"
        aria-hidden="true"
    >
        <div class="game-atmosphere__scene game-atmosphere__morning"></div>
        <div class="game-atmosphere__scene game-atmosphere__night"></div>
        <div class="game-atmosphere__haze"></div>
    </div>
</template>
