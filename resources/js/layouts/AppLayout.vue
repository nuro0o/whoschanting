<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    Eye,
    LogOut,
    Palette,
    ShieldCheck,
    UserRound,
    Waves,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import { logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import '../../css/chanting.css';
import '../../css/account.css';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const navigation = [
    { label: 'Village ledger', href: '/dashboard', icon: BookOpen },
    { label: 'Profile', href: '/settings/profile', icon: UserRound },
    { label: 'Security', href: '/settings/security', icon: ShieldCheck },
    { label: 'Appearance', href: '/settings/appearance', icon: Palette },
];
const isCurrent = (href: string) => page.url.split('?')[0] === href;
onMounted(() => document.body.classList.add('account-theme'));
onUnmounted(() => document.body.classList.remove('account-theme'));
</script>

<template>
    <div class="account-shell">
        <a class="account-skip" href="#account-main">Skip to content</a>
        <header class="account-header">
            <Link
                href="/"
                class="account-brand"
                aria-label="Who's Chanting? home"
            >
                <span class="account-brand-eye"
                    ><Eye :size="23" :stroke-width="1.4"
                /></span>
                <span
                    >who’s chanting<span class="account-question">?</span></span
                >
            </Link>
            <Link href="/" class="account-home-link"
                >Back to the village <ArrowUpRight :size="16"
            /></Link>
        </header>
        <div class="account-layout">
            <aside class="account-sidebar">
                <div class="account-sidebar-top">
                    <p class="account-kicker">Your account</p>
                    <nav class="account-nav" aria-label="Account navigation">
                        <Link
                            v-for="item in navigation"
                            :key="item.href"
                            :href="item.href"
                            :aria-current="
                                isCurrent(item.href) ? 'page' : undefined
                            "
                            :class="{ 'is-current': isCurrent(item.href) }"
                        >
                            <component
                                :is="item.icon"
                                :size="18"
                                :stroke-width="1.5"
                            />
                            <span>{{ item.label }}</span>
                            <span
                                v-if="isCurrent(item.href)"
                                class="account-nav-mark"
                                aria-hidden="true"
                                >✦</span
                            >
                        </Link>
                    </nav>
                </div>
                <div class="account-sidebar-bottom">
                    <div class="account-sidebar-note">
                        <Waves
                            :size="26"
                            :stroke-width="1"
                            aria-hidden="true"
                        />
                        <p>The sea keeps secrets.<br />So should you.</p>
                    </div>
                    <div class="account-signed-in">
                        <span class="account-initial" aria-hidden="true">{{
                            user.name.slice(0, 1).toUpperCase()
                        }}</span>
                        <div>
                            <span>Signed in as</span
                            ><strong>{{ user.name }}</strong>
                        </div>
                    </div>
                    <Link
                        :href="logout()"
                        as="button"
                        class="account-logout"
                        data-test="logout-button"
                        @click="router.flushAll()"
                        ><LogOut :size="16" /> Log out</Link
                    >
                </div>
            </aside>
            <main id="account-main" class="account-main" tabindex="-1">
                <slot />
            </main>
        </div>
        <footer class="account-footer">
            <span>WHO’S CHANTING?</span
            ><span>A familiar face. A questionable alibi.</span
            ><Link href="/#how-to-play"
                >How to play <ArrowUpRight :size="13"
            /></Link>
        </footer>
    </div>
</template>
