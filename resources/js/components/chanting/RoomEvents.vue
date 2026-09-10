<script setup lang="ts">
import { ScrollText } from '@lucide/vue';
defineProps<{ log: string[] }>();
</script>
<template>
    <section class="game-panel room-events" aria-label="Public events">
        <div class="briefing-events">
            <div class="briefing-events-heading">
                <h2>
                    <ScrollText :size="16" aria-hidden="true" /> Public events
                </h2>
                <span>Public · newest first</span>
            </div>
            <ol
                v-if="log.slice(-2).reverse().length"
                class="latest-events"
                aria-label="Latest public events"
                aria-live="polite"
                aria-relevant="additions text"
            >
                <li
                    v-for="(entry, index) in log.slice(-2).reverse()"
                    :key="`${log.length - index}:${entry}`"
                >
                    {{ entry }}
                </li>
            </ol>
            <p v-else class="briefing-empty">
                The village waits for its story to begin.
            </p>
            <details v-if="log.length" class="event-history">
                <summary>
                    Full public history
                    <span>{{ log.length }} events</span>
                </summary>
                <ol
                    class="events-list"
                    aria-label="Full public history, newest first"
                    tabindex="0"
                >
                    <li
                        v-for="(entry, index) in [...log].reverse()"
                        :key="index"
                    >
                        {{ entry }}
                    </li>
                </ol>
            </details>
        </div>
    </section>
</template>
