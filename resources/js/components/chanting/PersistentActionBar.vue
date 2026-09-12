<script setup lang="ts">
import { Check, Eye, LoaderCircle } from '@lucide/vue';
defineProps<{
    phase: string;
    time: string;
    urgent: boolean;
    title: string;
    consequence: string;
    receipt: string;
    label: string;
    disabled: boolean;
    pending: boolean;
    reveal: boolean;
    canReview: boolean;
}>();
const emit = defineEmits<{ confirm: []; reveal: []; review: [] }>();
</script>
<template>
    <section class="persistent-action-bar" aria-label="Current turn controls">
        <div class="action-clock" :class="{ urgent }">
            <span>{{ phase }}</span
            ><strong>{{ time || '—' }}</strong>
        </div>
        <div class="action-summary">
            <strong>{{ title }}</strong>
            <p v-if="consequence">{{ consequence }}</p>
            <p v-if="receipt" class="action-receipt" role="status">
                <Check :size="14" />{{ receipt }}
            </p>
        </div>
        <div class="action-buttons">
            <button v-if="canReview" class="button" @click="emit('review')">
                Choose action
            </button>
            <button
                v-if="reveal"
                class="button primary"
                @click="emit('reveal')"
            >
                <Eye :size="16" /> Reveal action
            </button>
            <button
                v-else-if="label"
                class="button primary"
                :disabled="disabled"
                @click="emit('confirm')"
            >
                <LoaderCircle v-if="pending" :size="16" class="spin" /><Check
                    v-else
                    :size="16"
                />{{ pending ? 'Sending…' : label }}
            </button>
        </div>
    </section>
</template>
<style scoped>
.persistent-action-bar {
    display: flex;
    align-items: center;
    gap: 18px;
    min-width: 0;
    padding: 14px 18px;
    color: #f0eadb;
    background: #162724;
    border: 1px solid #637863;
    border-radius: 4px;
    box-shadow: 0 -5px 24px #08130f60;
}
.action-clock {
    min-width: 90px;
    display: grid;
    gap: 3px;
    padding-right: 18px;
    border-right: 1px solid #526254;
}
.action-clock span {
    color: #becbbd;
    font-size: 12px;
}
.action-clock strong {
    font-size: 24px;
    font-variant-numeric: tabular-nums;
}
.action-clock.urgent strong {
    color: #f2ae91;
}
.action-summary {
    flex: 1;
    min-width: 0;
}
.action-summary > strong {
    font-size: 14px;
}
.action-summary p {
    margin: 4px 0 0;
    font-size: 13px;
    line-height: 1.45;
    color: #c8d3c3;
    overflow-wrap: anywhere;
}
.action-receipt {
    display: flex;
    align-items: start;
    gap: 6px;
}
.action-receipt svg {
    flex-shrink: 0;
    margin-top: 2px;
}
.action-buttons {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.action-buttons .button {
    min-height: 44px;
    padding: 10px 14px;
    font-size: 14px;
}
.action-buttons .button:disabled {
    opacity: 1;
    background: #334239;
    color: #c0cbbc;
    border-color: #526254;
}
@media (max-width: 900px) {
    .persistent-action-bar {
        gap: 8px 12px;
        padding: 10px 12px;
        flex-wrap: wrap;
        border-radius: 0;
        border-inline: 0;
        border-bottom: 0;
    }
    .action-clock {
        min-width: 70px;
        padding-right: 12px;
    }
    .action-clock strong {
        font-size: 20px;
    }
    .action-summary p {
        font-size: 12px;
        max-height: 4.5em;
        overflow-y: auto;
    }
    .action-buttons {
        width: 100%;
    }
    .action-buttons .button {
        flex: 1;
    }
}
</style>
