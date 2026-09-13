import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/** Track both Inertia visits and same-document anchor navigation. */
export function useAccountLocation() {
    const page = usePage();
    const href = ref(page.url);
    const syncBrowserLocation = () => {
        href.value = window.location.href;
    };

    watch(
        () => page.url,
        (url) => {
            href.value = url;
        },
    );
    onMounted(() => {
        syncBrowserLocation();
        window.addEventListener('hashchange', syncBrowserLocation);
        window.addEventListener('popstate', syncBrowserLocation);
    });
    onUnmounted(() => {
        window.removeEventListener('hashchange', syncBrowserLocation);
        window.removeEventListener('popstate', syncBrowserLocation);
    });

    return computed(() => new URL(href.value, 'http://account.local'));
}
