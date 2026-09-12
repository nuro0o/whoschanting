<script setup lang="ts">
import { t } from '@/i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Check, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';

const props = defineProps<{
    anchor: HTMLElement | null;
    trigger: HTMLElement | null;
    title: string;
    description: string;
    summary: string;
    error?: string | null;
}>();
const open = defineModel<boolean>({ default: false });
const placement = ref<{ left: number; top: number; width: number } | null>(
    null,
);
let clickedOutside = false;

function position() {
    const rect = props.anchor?.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const available = rect ? viewportWidth - rect.right - 32 : 0;
    placement.value =
        rect && viewportWidth >= 768 && available >= 380
            ? {
                  left: rect.right + 16,
                  top: Math.max(
                      16,
                      Math.min(rect.top, window.innerHeight - 536),
                  ),
                  width: Math.min(480, available),
              }
            : null;
}
const panelStyle = computed(() =>
    placement.value
        ? {
              left: `${placement.value.left}px`,
              top: `${placement.value.top}px`,
              width: `${placement.value.width}px`,
              maxHeight: `calc(100dvh - ${placement.value.top + 16}px)`,
          }
        : undefined,
);
function stopPositioning() {
    window.removeEventListener('resize', position);
    window.removeEventListener('scroll', position, true);
}
watch(open, (isOpen) => {
    if (!isOpen) {
        stopPositioning();
        return;
    }
    clickedOutside = false;
    const rect = props.anchor?.getBoundingClientRect();
    if (rect && (rect.bottom > window.innerHeight - 24 || rect.top < 16)) {
        window.scrollBy({ top: rect.top - 24, behavior: 'instant' });
    }
    position();
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
});
function restoreFocus(event: Event) {
    event.preventDefault();
    if (!open.value && !clickedOutside)
        props.trigger?.focus({ preventScroll: true });
}
function onInteractOutside(event: CustomEvent<{ originalEvent: Event }>) {
    const target = event.detail.originalEvent.target;
    if (
        target instanceof Element &&
        props.anchor?.contains(target) &&
        target.closest('[data-entry-editor-trigger]')
    ) {
        event.preventDefault();
        return;
    }
    clickedOutside = !!placement.value;
}
onBeforeUnmount(stopPositioning);
</script>

<template>
    <DialogRoot v-model:open="open" :modal="!placement">
        <DialogPortal>
            <DialogOverlay class="entry-editor-overlay" />
            <DialogContent
                class="chanting entry-editor"
                :class="{ 'entry-editor--side': placement }"
                :style="panelStyle"
                @close-auto-focus="restoreFocus"
                @interact-outside="onInteractOutside"
            >
                <header class="entry-editor-header">
                    <div>
                        <DialogTitle>{{ title }}</DialogTitle>
                        <DialogDescription>{{ description }}</DialogDescription>
                    </div>
                    <DialogClose
                        class="entry-editor-close"
                        :aria-label="t('entryEditorPanel.close')"
                    >
                        <X :size="20" aria-hidden="true" />
                    </DialogClose>
                </header>
                <div class="entry-editor-body"><slot /></div>
                <footer class="entry-editor-footer">
                    <p :class="{ 'form-error': error }" role="status">
                        {{ error || summary }}
                    </p>
                    <DialogClose class="button primary"
                        ><Check :size="16" aria-hidden="true" />
                        {{ t('entryEditorPanel.done') }}</DialogClose
                    >
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.entry-editor-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: rgb(5 14 15 / 35%);
}
.chanting.entry-editor {
    position: fixed;
    z-index: 61;
    inset: auto 12px max(12px, env(safe-area-inset-bottom));
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: calc(100dvh - 24px - env(safe-area-inset-bottom));
    height: 720px;
    max-width: 600px;
    margin-inline: auto;
    border: 1px solid #708574;
    border-radius: 8px;
    background: #182b29;
    box-shadow: 0 24px 80px #030b0b99;
    overflow: hidden;
}
.chanting.entry-editor--side {
    right: auto;
    bottom: auto;
    margin: 0;
    border-radius: 5px;
}
.entry-editor-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px 19px;
    border-bottom: 1px solid var(--line);
}
.entry-editor-header h2 {
    font-size: 26px;
    line-height: 1.2;
}
.entry-editor-header p {
    margin-top: 7px;
    color: var(--muted);
    font-size: 12px;
}
.entry-editor-close {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 3px;
    background: transparent;
    color: var(--cream);
}
.entry-editor-close:hover {
    border-color: var(--green);
    background: #bdcd9c12;
}
.entry-editor-body {
    min-height: 0;
    flex: 1;
    padding: 20px 24px 24px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-color: #657b69 #182b29;
    scrollbar-width: thin;
}
.entry-editor-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 24px;
    border-top: 1px solid var(--line);
    background: #152522;
}
.entry-editor-footer p {
    color: var(--muted);
    font-size: 12px;
    overflow-wrap: anywhere;
}
.entry-editor-footer p.form-error {
    color: var(--coral);
}
.entry-editor-footer .button {
    flex-shrink: 0;
}
.entry-editor-body :deep(.character-picker) {
    margin: 0;
}
.entry-editor-body :deep(.character-choices--earned) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.entry-editor-body :deep(.character-choices .character-portrait) {
    width: min(100%, 72px);
}
@media (max-width: 420px) {
    .entry-editor-header,
    .entry-editor-footer {
        padding: 16px;
    }
    .entry-editor-body {
        padding: 18px 16px;
    }
    .entry-editor-body :deep(.character-choices--earned) {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
