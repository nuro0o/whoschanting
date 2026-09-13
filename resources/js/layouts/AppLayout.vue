<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import LegalLinks from '@/components/LegalLinks.vue';
import {
    ArrowUpRight,
    Eye,
    LogOut,
    Shirt,
    ShoppingBag,
    Trophy,
} from '@lucide/vue';
import { onMounted, onUnmounted } from 'vue';
import { useAccountLocation } from '@/composables/useAccountLocation';
import { logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import '../../css/chanting.css';
import '../../css/account.css';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
const location = useAccountLocation();
const navigation = [
    { number: '01', label: 'Ledger', href: '/dashboard' },
    { icon: ShoppingBag, label: 'Store', href: '/settings/profile#store' },
    {
        icon: Shirt,
        label: 'Wardrobe',
        href: '/progression?tab=wardrobe#progression-sections',
    },
    {
        icon: Trophy,
        label: 'Progression',
        href: '/progression?tab=achievements',
    },
    { number: '05', label: 'Profile', href: '/settings/profile' },
    { number: '06', label: 'Purchases', href: '/account/purchases' },
    { number: '07', label: 'Security', href: '/settings/security' },
    { number: '08', label: 'Appearance', href: '/settings/appearance' },
];
const isCurrent = (href: string) => {
    const current = location.value;
    if (current.pathname === '/settings/profile') {
        return (
            href ===
            (current.hash === '#store'
                ? '/settings/profile#store'
                : '/settings/profile')
        );
    }
    if (current.pathname === '/progression') {
        return (
            href ===
            (['achievements', 'season'].includes(
                current.searchParams.get('tab') ?? '',
            )
                ? '/progression?tab=achievements'
                : '/progression?tab=wardrobe#progression-sections')
        );
    }
    return current.pathname === href;
};
onMounted(() => document.body.classList.add('account-theme'));
onUnmounted(() => document.body.classList.remove('account-theme'));
</script>

<template>
    <div class="account-shell registry-shell">
        <a class="account-skip" href="#account-main">Skip to content</a>
        <header class="registry-masthead">
            <p class="registry-masthead-note">
                The village register<span>Somewhere the sea is listening.</span>
            </p>
            <Link
                href="/"
                class="account-brand"
                aria-label="Who's Chanting? home"
                ><span class="account-brand-eye"
                    ><Eye :size="23" :stroke-width="1.4" /></span
                ><span
                    >who’s chanting<span class="account-question">?</span></span
                ></Link
            >
            <div class="registry-masthead-actions">
                <Link href="/" class="registry-return"
                    ><span>Back to village</span
                    ><ArrowUpRight :size="15" /></Link
                ><Link
                    :href="logout()"
                    as="button"
                    class="registry-logout"
                    data-test="logout-button"
                    @click="router.flushAll()"
                    ><LogOut :size="15" /><span>Log out</span></Link
                >
            </div>
        </header>
        <div class="registry-chapter-bar">
            <nav class="registry-chapters" aria-label="Account navigation">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    preserve-state
                    :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    :class="{
                        'is-current': isCurrent(item.href),
                        'registry-destination': item.icon,
                    }"
                    ><component
                        v-if="item.icon"
                        :is="item.icon"
                        :size="16"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    /><span
                        v-else
                        class="registry-chapter-number"
                        aria-hidden="true"
                        >{{ item.number }}</span
                    ><span>{{ item.label }}</span></Link
                >
            </nav>
        </div>
        <main id="account-main" class="registry-main" tabindex="-1">
            <slot />
        </main>
        <footer class="registry-footer">
            <span>WHO’S CHANTING?</span>
            <p>The sea keeps secrets. So should you.</p>
            <Link href="/#how-to-play"
                >How to play <ArrowUpRight :size="13"
            /></Link>
            <LegalLinks />
        </footer>
    </div>
</template>
<style scoped>
.registry-footer {
    flex-wrap: wrap;
}
</style>
