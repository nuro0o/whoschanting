<script setup lang="ts">
import { t } from '@/i18n';
import { Head } from '@inertiajs/vue3';
import { ArrowDown, Eye, Moon, Users, Vote, Waves } from '@lucide/vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import RoleGuide from '@/components/chanting/RoleGuide.vue';
import VillageScene from '@/components/chanting/VillageScene.vue';
import GameGlossary from '@/components/chanting/GameGlossary.vue';
import CharacterCarousel from '@/components/chanting/CharacterCarousel.vue';
import { defaultCharacters, type Character } from '@/lib/chanting';
import '../../css/chanting.css';
import '../../css/tutorial-invitation.css';
withDefaults(
    defineProps<{
        characters?: Character[];
        preferredCharacter?: string | null;
        rules?: {
            min_players: number;
            max_players: number;
            town_roles_min_players?: Record<string, number>;
            cult_roles_min_players?: Record<string, number>;
            ritual_goals?: { players: number; steps: number }[];
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
            <nav :aria-label="t('welcome.navigation.label')">
                <a href="/rooms" class="quiet-link">{{
                    t('welcome.navigation.rooms')
                }}</a>
                <a href="#roles" class="quiet-link">{{
                    t('welcome.navigation.roles')
                }}</a>
                <a href="/tutorial" class="quiet-link">{{
                    t('welcome.navigation.practice')
                }}</a>
                <a href="#how-to-play" class="quiet-link"
                    >{{ t('welcome.navigation.how_to_play') }}
                    <ArrowDown :size="14"
                /></a>
                <a
                    v-if="$page.props.auth.user"
                    href="/dashboard"
                    class="quiet-link"
                    >{{ t('welcome.navigation.account') }}</a
                >
                <template v-else>
                    <a href="/login" class="quiet-link">{{
                        t('welcome.navigation.sign_in')
                    }}</a>
                    <a href="/register" class="quiet-link account-signup">{{
                        t('welcome.navigation.register')
                    }}</a>
                </template>
            </nav>
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
                    <p class="hero-invitation">
                        {{ t('welcome.hero.invitation_line_one')
                        }}<br class="desktop-break" />
                        {{ t('welcome.hero.invitation_line_two') }}
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
            <CharacterCarousel :characters="characters" />
            <RoleGuide
                id="roles"
                class="home-roles"
                :minimum-players="{
                    ...rules.town_roles_min_players,
                    ...rules.cult_roles_min_players,
                }"
            />
            <section
                id="how-to-play"
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
                        {{ t('welcome.rules.summary') }} <span>+</span>
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
                        <p>{{ t('welcome.rules.curses.difficulty') }}</p>
                        <p>
                            <strong>{{
                                t('welcome.rules.ritual.title')
                            }}</strong>
                            {{ t('welcome.rules.ritual.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.small_gathering.title')
                            }}</strong>
                            {{ t('welcome.rules.small_gathering.description') }}
                        </p>
                        <table
                            v-if="rules.ritual_goals?.length"
                            class="ritual-goals"
                        >
                            <caption>
                                {{
                                    t('welcome.rules.ritual_goals.caption')
                                }}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">
                                        {{
                                            t(
                                                'welcome.rules.ritual_goals.players',
                                            )
                                        }}
                                    </th>
                                    <th scope="col">
                                        {{
                                            t(
                                                'welcome.rules.ritual_goals.steps_heading',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="goal in rules.ritual_goals"
                                    :key="goal.players"
                                >
                                    <td>{{ goal.players }}</td>
                                    <td>
                                        {{
                                            t(
                                                'welcome.rules.ritual_goals.steps',
                                                { steps: goal.steps },
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p>
                            <strong>{{
                                t('welcome.rules.ready.title')
                            }}</strong>
                            {{ t('welcome.rules.ready.description') }}
                        </p>
                        <p>
                            <strong>{{
                                t('welcome.rules.balance.title')
                            }}</strong>
                            {{
                                t('welcome.rules.balance.description', {
                                    minPlayers: rules.min_players,
                                    maxPlayers: rules.max_players,
                                })
                            }}
                        </p>
                    </div>
                </details>
                <GameGlossary />
            </section>
        </main>
        <footer class="site-footer">
            <span><Eye :size="16" /> {{ t('welcome.brand.full_name') }}</span>
            <p>{{ t('welcome.footer.tagline') }}</p>
            <span class="footer-edition">{{
                t('welcome.footer.edition')
            }}</span>
        </footer>
    </div>
</template>

<style scoped>
.home-roles {
    width: min(1268px, 88%);
    margin-inline: auto;
}

@media (max-width: 900px) {
    .site-header {
        height: auto;
        min-height: 100px;
        flex-wrap: wrap;
        gap: 16px;
        padding-block: 18px;
    }

    .site-header nav {
        flex-wrap: wrap;
        gap: 12px 20px;
    }
}

@media (max-width: 400px) {
    .site-header nav {
        display: grid;
        grid-template-columns: repeat(2, max-content);
        justify-content: space-between;
    }
}
</style>
