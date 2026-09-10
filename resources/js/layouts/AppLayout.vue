<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Eye, LogOut, Waves } from '@lucide/vue';
import { onMounted, onUnmounted } from 'vue';
import { logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import '../../css/chanting.css';
import '../../css/account.css';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
const page = usePage();
const navigation = [
    { number: '01', label: 'Ledger', href: '/dashboard' },
    { number: '02', label: 'Profile', href: '/settings/profile' },
    { number: '03', label: 'Security', href: '/settings/security' },
    { number: '04', label: 'Appearance', href: '/settings/appearance' },
];
const isCurrent = (href: string) => page.url.split('?')[0] === href;
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
                    :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    :class="{ 'is-current': isCurrent(item.href) }"
                    ><span class="registry-chapter-number" aria-hidden="true">{{
                        item.number
                    }}</span
                    ><span>{{ item.label }}</span></Link
                >
            </nav>
            <p class="registry-chapter-note">
                <Waves :size="19" :stroke-width="1.2" aria-hidden="true" />
                Every resident has a record.
            </p>
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
        </footer>
    </div>
</template>
