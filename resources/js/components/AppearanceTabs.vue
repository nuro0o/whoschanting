<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        class="appearance-options"
        role="group"
        aria-label="Account appearance"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            class="appearance-option"
            :class="{ 'is-selected': appearance === value }"
        >
            <span
                class="appearance-preview"
                :class="'appearance-preview--' + value"
                aria-hidden="true"
                ><i></i><b></b
            ></span>
            <span class="appearance-option-label"
                ><component :is="Icon" :size="14" /><span>{{ label }}</span
                ><Check v-if="appearance === value" :size="13"
            /></span>
        </button>
    </div>
</template>
