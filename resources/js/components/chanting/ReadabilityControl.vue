<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { Type } from '@lucide/vue';
import '../../../css/readability.css';
const settings = ref<HTMLDetailsElement>();
const settingsOpen = ref(false);
const panelId = useId();
const panelStyle = ref<Record<string, string>>({});
function positionPanel() {
    if (!settings.value?.open) return;
    const trigger = settings.value
        .querySelector('summary')
        ?.getBoundingClientRect();
    if (!trigger) return;
    const width = Math.min(300, window.innerWidth - 32);
    const left = Math.max(
        16,
        Math.min(trigger.right - width, window.innerWidth - width - 16),
    );
    const top = Math.max(
        16,
        Math.min(trigger.bottom + 8, window.innerHeight - 180),
    );
    panelStyle.value = {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
        maxHeight: `${window.innerHeight - top - 16}px`,
    };
}
function toggled() {
    settingsOpen.value = settings.value?.open ?? false;
    positionPanel();
}
function closeSettings(event: Event) {
    if (!settings.value?.open || settings.value.contains(event.target as Node))
        return;
    const focusWasInside = settings.value.contains(document.activeElement);
    settings.value.open = false;
    if (focusWasInside) settings.value.querySelector('summary')?.focus();
}
function escapeSettings(event: KeyboardEvent) {
    if (event.key !== 'Escape' || !settings.value?.open) return;
    event.preventDefault();
    settings.value.open = false;
    settings.value.querySelector('summary')?.focus();
}
const largeText = ref(false);
const highContrast = ref(false);
const unavailable = ref(false);
const key = 'chanting-readability-v1';
function apply() {
    document.documentElement.dataset.chantingText = largeText.value
        ? 'large'
        : 'normal';
    document.documentElement.dataset.chantingContrast = highContrast.value
        ? 'high'
        : 'normal';
}
function save() {
    apply();
    void nextTick(positionPanel);
    try {
        localStorage.setItem(
            key,
            JSON.stringify({
                largeText: largeText.value,
                highContrast: highContrast.value,
            }),
        );
        unavailable.value = false;
    } catch {
        unavailable.value = true;
    }
}
onMounted(() => {
    document.addEventListener('pointerdown', closeSettings);
    document.addEventListener('keydown', escapeSettings);
    window.addEventListener('resize', positionPanel);
    window.addEventListener('scroll', positionPanel, true);
    // Existing document preferences survive navigation when storage is blocked.
    largeText.value = document.documentElement.dataset.chantingText === 'large';
    highContrast.value =
        document.documentElement.dataset.chantingContrast === 'high';
    try {
        const saved = JSON.parse(localStorage.getItem(key) || 'null');
        if (saved && typeof saved === 'object') {
            largeText.value = saved.largeText === true;
            highContrast.value = saved.highContrast === true;
        }
    } catch {
        unavailable.value = true;
    }
    apply();
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeSettings);
    document.removeEventListener('keydown', escapeSettings);
    window.removeEventListener('resize', positionPanel);
    window.removeEventListener('scroll', positionPanel, true);
});
</script>
<template>
    <details ref="settings" class="readability-control" @toggle="toggled">
        <summary :aria-expanded="settingsOpen" :aria-controls="panelId">
            <Type :size="16" /> Readability
        </summary>
        <div :id="panelId" class="readability-options" :style="panelStyle">
            <label
                ><input v-model="largeText" type="checkbox" @change="save" />
                Larger text</label
            ><label
                ><input v-model="highContrast" type="checkbox" @change="save" />
                Higher contrast panels</label
            >
            <p>
                {{
                    unavailable
                        ? 'Applies for this page. Browser preference saving is unavailable.'
                        : 'Saved on this browser.'
                }}
            </p>
        </div>
    </details>
</template>
<style scoped>
.readability-control {
    position: relative;
    z-index: 61;
    color: #e7e9d8;
    font-size: 14px;
}
.readability-control summary {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    cursor: pointer;
    padding: 8px 10px;
    border: 1px solid #ffffff26;
    border-radius: 6px;
}
.readability-control summary:focus-visible {
    outline: 2px solid #dcecbc;
    outline-offset: 3px;
}
.readability-options {
    position: fixed;
    z-index: 61;
    width: min(300px, calc(100vw - 32px));
    overflow-y: auto;
    overscroll-behavior: contain;
    box-shadow: 0 14px 40px #06131480;
    text-align: left;
    padding: 12px;
    background: #102922;
    border: 1px solid #ffffff30;
    border-radius: 6px;
}
.readability-options label {
    display: flex;
    gap: 10px;
    align-items: center;
    min-height: 44px;
    font-size: 14px;
    cursor: pointer;
}
.readability-options input {
    width: 18px;
    height: 18px;
    accent-color: #b1d4b5;
}
.readability-options p {
    font-size: 12px;
    color: #c2d2c6;
    max-width: 250px;
    line-height: 1.5;
}
</style>
