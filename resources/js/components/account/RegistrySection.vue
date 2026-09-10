<script setup lang="ts">
import { useId } from 'vue';
defineProps<{ number: string; title: string; description?: string }>();
const headingId = useId();
</script>
<template>
    <section class="registry-section" :aria-labelledby="headingId">
        <header class="registry-section-margin">
            <span class="registry-section-number" aria-hidden="true">{{
                number
            }}</span>
            <h2 :id="headingId">{{ title }}</h2>
            <p v-if="description">{{ description }}</p>
        </header>
        <div class="registry-section-body"><slot /></div>
    </section>
</template>
