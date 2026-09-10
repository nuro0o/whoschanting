import { onBeforeUnmount, onBeforeUpdate, onUpdated, ref } from 'vue';

export function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Measure around Vue's patch so changing controls never collapse in one frame.
export function usePanelMotion() {
    const panel = ref<HTMLElement>();
    let height = 0;
    let animation: Animation | undefined;

    onBeforeUpdate(() => {
        height = panel.value?.getBoundingClientRect().height ?? 0;
    });
    onUpdated(() => {
        animation?.cancel();
        const element = panel.value;
        if (!element || !height || prefersReducedMotion()) return;
        const nextHeight = element.getBoundingClientRect().height;
        if (!nextHeight || Math.abs(height - nextHeight) < 1) return;
        animation = element.animate(
            [{ height: `${height}px` }, { height: `${nextHeight}px` }],
            { duration: 240, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
        );
    });
    onBeforeUnmount(() => animation?.cancel());
    return panel;
}
