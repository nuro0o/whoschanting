<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDown, Eye, Moon, Users, Vote, Waves } from '@lucide/vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import VillageScene from '@/components/chanting/VillageScene.vue';
import '../../css/chanting.css';
withDefaults(
    defineProps<{ rules?: { min_players: number; max_players: number } }>(),
    { rules: () => ({ min_players: 5, max_players: 10 }) },
);
</script>
<template>
    <Head title="Trust your friends. Mostly."
        ><meta
            name="description"
            content="A private-room social deduction game. Gather your friends, uncover the cult, and save a very suspicious little village."
    /></Head>
    <div class="chanting home-page">
        <header class="site-header">
            <a href="/" class="wordmark" aria-label="Who's Chanting? home"
                ><span class="brand-eye"><Eye :size="26" /></span> who’s
                chanting<span class="brand-question">?</span></a
            >
            <nav aria-label="Main navigation">
                <a href="#how-to-play" class="quiet-link"
                    >How to play <ArrowDown :size="14" /></a
                ><span class="edition-label"
                    >A LITTLE PARANOIA. A LOT OF FRIENDS.</span
                >
            </nav>
        </header>
        <main>
            <section class="landing-hero" aria-labelledby="hero-title">
                <div class="hero-art">
                    <VillageScene />
                    <div class="village-caption">
                        <span class="live-dot"></span> SOMEWHERE THE SEA IS
                        LISTENING.
                    </div>
                </div>
                <div class="hero-content">
                    <p class="eyebrow"><span></span> A SOCIAL DEDUCTION GAME</p>
                    <h1 id="hero-title">
                        Lovely village.<br />Terrible <em>secrets.</em>
                    </h1>
                    <p class="hero-description">
                        Someone’s summoning an ancient god.<br
                            class="desktop-break"
                        />
                        Probably one of your friends.
                    </p>
                    <p class="hero-invitation">
                        Read the room. Hide your intentions. Find the cult<br
                            class="desktop-break"
                        />
                        before the chanting gets a little too loud.
                    </p>
                    <div class="hero-facts">
                        <span
                            ><Users :size="15" />{{ rules.min_players }}–{{
                                rules.max_players
                            }}
                            friends</span
                        ><span><Moon :size="15" /> Secret roles</span
                        ><span><Waves :size="16" /> Endless suspicion</span>
                    </div>
                    <RoomEntry />
                </div>
                <span class="hero-side-note" aria-hidden="true"
                    >WELCOME TO THE END OF THE WORLD. MAYBE.</span
                >
            </section>
            <section
                id="how-to-play"
                class="how-section"
                aria-labelledby="how-title"
            >
                <div class="section-intro">
                    <p class="eyebrow">
                        GOOD COMPANY. QUESTIONABLE ALLEGIANCES.
                    </p>
                    <h2 id="how-title">
                        One village. Two sides.<br /><em
                            >Nobody’s telling the whole truth.</em
                        >
                    </h2>
                    <p>A tiny guide to a very bad night.</p>
                </div>
                <div class="how-steps">
                    <article>
                        <span class="step-number">01 / GATHER</span>
                        <div class="step-symbol">
                            <Users :size="32" :stroke-width="1.2" />
                        </div>
                        <h3>Invite the usual suspects.</h3>
                        <p>
                            Create a private room and share the code. Everyone
                            gets a secret role. Two of you get a much darker
                            agenda.
                        </p>
                    </article>
                    <article>
                        <span class="step-number">02 / DECEIVE</span>
                        <div class="step-symbol coral">
                            <Eye :size="34" :stroke-width="1.2" />
                        </div>
                        <h3>A little ritual after dark.</h3>
                        <p>
                            The cult chants. The Oracle investigates. The
                            Veilweaver bends the truth. By day, every story
                            deserves a second look.
                        </p>
                    </article>
                    <article>
                        <span class="step-number">03 / DECIDE</span>
                        <div class="step-symbol">
                            <Vote :size="32" :stroke-width="1.2" />
                        </div>
                        <h3>Trust your gut. Or don’t.</h3>
                        <p>
                            Discuss and vote to banish a suspect. Unmask all
                            cultists to save the town—or finish the ritual to
                            wake what waits below.
                        </p>
                    </article>
                </div>
                <details class="rules-details">
                    <summary>
                        The finer points of impending doom <span>+</span>
                    </summary>
                    <div class="rules-copy">
                        <p>
                            <strong>Night, discussion, vote. Repeat.</strong>
                            Living players act once per night and cast one final
                            vote per day. Ties and abstentions banish nobody. If
                            you miss a deadline, your action is forfeited.
                            Banished players can watch until the match ends.
                        </p>
                        <p>
                            <strong>Your role card tells the truth.</strong>
                            Cultists know each other and share one mission. The
                            Oracle sees an alignment, but the Veilweaver can
                            reverse one player’s reading for that night. Your
                            own role and mission are never disguised.
                        </p>
                        <p>
                            <strong>The ritual is a race.</strong> A cultist may
                            earn one token per night by chanting when the shared
                            mission’s condition is met: chant together, avoid
                            investigation, or keep cultists safe in the previous
                            vote. The public track must reach max(6,
                            ceil(players × 1.2)) tokens. The cult also wins if
                            no townspeople remain.
                        </p>
                        <p>
                            <strong
                                >First-edition rules, ready to evolve.</strong
                            >
                            {{ rules.min_players }}–{{ rules.max_players }}
                            players, two cultists and one Oracle. Default phases
                            last 25 seconds for the reveal, 45 for night, 90 for
                            discussion and 45 for voting. These balance settings
                            are provisional and configurable.
                        </p>
                    </div>
                </details>
            </section>
        </main>
        <footer class="site-footer">
            <span><Eye :size="16" /> who’s chanting?</span>
            <p>Made for friends. Not necessarily for friendships.</p>
            <span class="footer-edition">PRIVATE ROOMS · FIRST EDITION</span>
        </footer>
    </div>
</template>
