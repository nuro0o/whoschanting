<script setup lang="ts">
import { t } from '@/i18n';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { PopoverContent, PopoverRoot, PopoverTrigger } from 'reka-ui';
import LegalLinks from '@/components/LegalLinks.vue';
import { ArrowDown, Eye, Menu, Moon, Users, Vote, Waves } from '@lucide/vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import RoleGuide from '@/components/chanting/RoleGuide.vue';
import VillageScene from '@/components/chanting/VillageScene.vue';
import GameGlossary from '@/components/chanting/GameGlossary.vue';
import CharacterCarousel from '@/components/chanting/CharacterCarousel.vue';
import { defaultCharacters, type Character } from '@/lib/chanting';
import '../../css/chanting.css';
import '../../css/tutorial-invitation.css';

const page = usePage();
const menuOpen = ref(false);
const mobileNavigation = useMediaQuery('(max-width: 1050px)');
const navigationLinks = computed(() => [
    { href: '/rooms', label: t('welcome.navigation.rooms') },
    { href: '#how-to-play', label: t('welcome.navigation.how_to_play') },
    { href: '#roles', label: t('welcome.navigation.roles') },
    { href: '/tutorial', label: t('welcome.navigation.practice') },
    ...(page.props.auth.user
        ? [{ href: '/dashboard', label: t('welcome.navigation.account') }]
        : [
              { href: '/login', label: t('welcome.navigation.sign_in') },
              { href: '/register', label: t('welcome.navigation.register') },
          ]),
]);
let navigationTarget: string | null = null;

watch(mobileNavigation, () => {
    menuOpen.value = false;
});

function selectNavigation(href: string, event: MouseEvent) {
    navigationTarget = href.startsWith('#') ? href.slice(1) : null;
    if (navigationTarget) event.preventDefault();
    menuOpen.value = false;
}

function focusNavigationTarget(target: string) {
    const section = document.getElementById(target);
    section?.scrollIntoView({ block: 'start' });
    const control =
        target === 'play' ? section?.querySelector('input') : section;
    control?.focus({ preventScroll: true });
}

function restoreNavigationFocus(event: Event) {
    if (!mobileNavigation.value) {
        event.preventDefault();
        nextTick(() => {
            document
                .querySelector<HTMLAnchorElement>('.desktop-navigation a')
                ?.focus();
        });
        return;
    }
    if (!navigationTarget) return;
    event.preventDefault();
    const target = navigationTarget;
    navigationTarget = null;
    nextTick(() => focusNavigationTarget(target));
}

function focusPlay() {
    if (menuOpen.value) navigationTarget = 'play';
    menuOpen.value = false;
    nextTick(() => focusNavigationTarget('play'));
}

withDefaults(
    defineProps<{
        characters?: Character[];
        preferredCharacter?: string | null;
        rules?: {
            min_players: number;
            max_players: number;
            town_roles_min_players?: Record<string, number>;
            cult_roles_min_players?: Record<string, number>;
        };
    }>(),
    {
        characters: () => defaultCharacters,
        rules: () => ({ min_players: 3, max_players: 15 }),
    },
);
</script>
<template>
    <Head :title="t('welcome.meta.title')"
        ><meta name="description" :content="t('welcome.meta.description')"
    /></Head>
    <div class="chanting home-page">
        <header class="site-header">
            <a
                href="/"
                class="wordmark"
                :aria-label="t('welcome.navigation.home')"
                ><span class="brand-eye"><Eye :size="26" /></span>
                {{ t('welcome.brand.name')
                }}<span class="brand-question">?</span></a
            >
            <nav
                class="desktop-navigation"
                :aria-label="t('welcome.navigation.label')"
            >
                <a
                    v-for="link in navigationLinks"
                    :key="link.href"
                    :href="link.href"
                    class="quiet-link"
                    :class="{ 'account-signup': link.href === '/register' }"
                    >{{ link.label
                    }}<ArrowDown v-if="link.href === '#how-to-play'" :size="14"
                /></a>
            </nav>
            <div class="mobile-navigation">
                <a
                    href="#play"
                    class="header-play"
                    @click.prevent="focusPlay"
                    >{{ t('welcome.navigation.play') }}</a
                >
                <PopoverRoot v-model:open="menuOpen">
                    <PopoverTrigger
                        class="header-menu"
                        :aria-label="t('welcome.navigation.menu')"
                    >
                        <Menu :size="21" aria-hidden="true" />
                    </PopoverTrigger>
                    <PopoverContent
                        :aria-label="t('welcome.navigation.label')"
                        :style="{ zIndex: 10 }"
                        align="end"
                        :side-offset="12"
                        @close-auto-focus="restoreNavigationFocus"
                    >
                        <div class="mobile-menu-panel">
                            <nav :aria-label="t('welcome.navigation.label')">
                                <a
                                    v-for="link in navigationLinks"
                                    :key="link.href"
                                    :href="link.href"
                                    class="quiet-link"
                                    @click="selectNavigation(link.href, $event)"
                                    >{{ link.label }}</a
                                >
                            </nav>
                        </div>
                    </PopoverContent>
                </PopoverRoot>
            </div>
        </header>
        <main>
            <section class="landing-hero" aria-labelledby="hero-title">
                <div class="hero-art">
                    <VillageScene />
                    <div class="village-caption">
                        <span class="live-dot"></span>
                        {{ t('welcome.hero.caption') }}
                    </div>
                </div>
                <div class="hero-content">
                    <p class="eyebrow">
                        <span></span> {{ t('welcome.hero.eyebrow') }}
                    </p>
                    <h1 id="hero-title">
                        {{ t('welcome.hero.title_line_one') }}<br />{{
                            t('welcome.hero.title_line_two')
                        }}
                        <em>{{ t('welcome.hero.title_emphasis') }}</em>
                    </h1>
                    <p class="hero-description">
                        {{ t('welcome.hero.description_line_one')
                        }}<br class="desktop-break" />
                        {{ t('welcome.hero.description_line_two') }}
                    </p>
                    <div class="hero-facts">
                        <span
                            ><Users :size="15" />{{
                                t('welcome.hero.player_count', {
                                    minPlayers: rules.min_players,
                                    maxPlayers: rules.max_players,
                                })
                            }}</span
                        ><span
                            ><Moon :size="15" />
                            {{ t('welcome.hero.secret_roles') }}</span
                        ><span
                            ><Waves :size="16" />
                            {{ t('welcome.hero.suspicion') }}</span
                        >
                    </div>
                    <RoomEntry
                        id="play"
                        :characters="characters"
                        :preferred-character="preferredCharacter"
                        compact-characters
                    />
                    <p class="tutorial-invitation">
                        {{ t('welcome.tutorial.intro') }}
                        <a href="/tutorial" class="quiet-link">{{
                            t('welcome.tutorial.link')
                        }}</a>
                        <span>{{ t('welcome.tutorial.details') }}</span>
                    </p>
                </div>
                <span class="hero-side-note" aria-hidden="true">{{
                    t('welcome.hero.side_note')
                }}</span>
            </section>
            <section
                id="how-to-play"
                tabindex="-1"
                class="how-section"
                aria-labelledby="how-title"
            >
                <div class="section-intro">
                    <p class="eyebrow">
                        {{ t('welcome.how_to_play.eyebrow') }}
                    </p>
                    <h2 id="how-title">
                        {{ t('welcome.how_to_play.title') }}<br /><em>{{
                            t('welcome.how_to_play.title_emphasis')
                        }}</em>
                    </h2>
                    <p>{{ t('welcome.how_to_play.intro') }}</p>
                </div>
                <div class="how-steps">
                    <article>
                        <span class="step-number">{{
                            t('welcome.how_to_play.gather.label')
                        }}</span>
                        <div class="step-symbol">
                            <Users :size="32" :stroke-width="1.2" />
                        </div>
                        <h3>{{ t('welcome.how_to_play.gather.title') }}</h3>
                        <p>{{ t('welcome.how_to_play.gather.description') }}</p>
                    </article>
                    <article>
                        <span class="step-number">{{
                            t('welcome.how_to_play.deceive.label')
                        }}</span>
                        <div class="step-symbol coral">
                            <Eye :size="34" :stroke-width="1.2" />
                        </div>
                        <h3>{{ t('welcome.how_to_play.deceive.title') }}</h3>
                        <p>
                            {{ t('welcome.how_to_play.deceive.description') }}
                        </p>
                    </article>
                    <article>
                        <span class="step-number">{{
                            t('welcome.how_to_play.decide.label')
                        }}</span>
                        <div class="step-symbol">
                            <Vote :size="32" :stroke-width="1.2" />
                        </div>
                        <h3>{{ t('welcome.how_to_play.decide.title') }}</h3>
                        <p>{{ t('welcome.how_to_play.decide.description') }}</p>
                    </article>
                </div>
                <details class="rules-details">
                    <summary>
                        {{ t('welcome.rules.summary') }}
                        <span aria-hidden="true">+</span>
                    </summary>
                    <div class="rules-copy">
                        <p>
                            <strong>{{
                                t('welcome.rules.phases.title')
                            }}</strong>
                            {{ t('welcome.rules.phases.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.role_card.title')
                            }}</strong>
                            {{ t('welcome.rules.role_card.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.curses.title')
                            }}</strong>
                            {{ t('welcome.rules.curses.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.ritual.title')
                            }}</strong>
                            {{ t('welcome.rules.ritual.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.final_vote.title')
                            }}</strong>
                            {{ t('welcome.rules.final_vote.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.setup.title')
                            }}</strong>
                            {{ t('welcome.rules.setup.description') }}
                        </p>
                    </div>
                </details>
                <GameGlossary />
            </section>
            <CharacterCarousel :characters="characters" />
            <RoleGuide
                id="roles"
                tabindex="-1"
                class="home-roles"
                :minimum-players="{
                    ...rules.town_roles_min_players,
                    ...rules.cult_roles_min_players,
                }"
            />
        </main>
        <footer class="site-footer">
            <span><Eye :size="16" /> {{ t('welcome.brand.full_name') }}</span>
            <p>{{ t('welcome.footer.tagline') }}</p>
            <span class="footer-edition">{{
                t('welcome.footer.edition')
            }}</span>
            <LegalLinks />
        </footer>
    </div>
</template>

<style scoped>
.mobile-navigation {
    display: none;
}

.landing-hero {
    padding-block: 44px 44px;
}

#play {
    scroll-margin-top: 20px;
}

.home-roles {
    width: min(1268px, 88%);
    margin-inline: auto;
}

.how-steps {
    margin-block: 30px 24px;
}

@media (max-width: 1050px) {
    .home-page .site-header {
        height: 72px;
        min-height: 72px;
        flex-wrap: nowrap;
        gap: 12px;
        padding-block: 0;
    }

    .site-header .desktop-navigation {
        display: none;
    }

    .mobile-navigation {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .header-play,
    .header-menu {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        border-radius: 4px;
    }

    .header-play {
        padding: 0 16px;
        background: var(--green);
        color: var(--ink);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .header-play:hover {
        background: var(--cream);
    }

    .header-menu {
        width: 44px;
        border: 1px solid var(--line);
        background: var(--panel);
        color: var(--cream);
    }

    .header-menu:hover,
    .header-menu[data-state='open'] {
        border-color: var(--green);
        color: var(--green);
    }

    .mobile-menu-panel {
        z-index: 10;
        min-width: 220px;
        max-width: calc(100vw - 32px);
        padding: 8px;
        border: 1px solid var(--line, #344346);
        border-radius: 4px;
        background: var(--ink, #101d20);
        color: var(--cream, #eee9d5);
        font-family: 'DM Sans', sans-serif;
        box-shadow: 0 16px 40px #0006;
    }

    .mobile-menu-panel nav {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0;
    }

    .mobile-menu-panel .quiet-link {
        min-height: 44px;
        padding: 10px 14px;
        color: var(--cream, #eee9d5);
        font-size: 14px;
        text-decoration: none;
    }

    .mobile-menu-panel .quiet-link:hover {
        background: var(--panel, #18282b);
    }

    .header-play:focus-visible,
    .header-menu:focus-visible,
    .mobile-menu-panel .quiet-link:focus-visible {
        outline: 2px solid var(--green, #bdcd9c);
        outline-offset: 3px;
    }
}

@media (max-width: 700px) {
    .landing-hero {
        padding-block: 28px 30px;
    }

    .hero-content h1 {
        margin-top: 18px;
    }

    .hero-facts {
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-block: 18px 22px;
    }

    .how-steps {
        margin-block: 18px;
    }

    .how-steps article {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr);
        column-gap: 14px;
        padding: 18px 0;
    }

    .step-number,
    .how-steps h3 {
        grid-column: 2;
    }

    .step-symbol {
        position: static;
        grid-column: 1;
        grid-row: 1 / 3;
        width: 44px;
        height: 44px;
        margin: 0;
    }

    .step-symbol svg {
        width: 26px;
        height: 26px;
    }

    .how-steps h3 {
        margin-top: 5px;
    }

    .how-steps p {
        grid-column: 1 / -1;
        font-size: 13px;
        line-height: 1.7;
        margin-top: 10px;
    }

    .rules-details {
        margin-top: 20px;
    }
}

@media (max-width: 400px) {
    .wordmark {
        font-size: 19px;
        gap: 7px;
    }

    .brand-eye {
        width: 29px;
        height: 29px;
    }

    .header-play {
        padding-inline: 13px;
    }
}
</style>
