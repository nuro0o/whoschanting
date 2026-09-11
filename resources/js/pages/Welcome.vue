<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDown, Eye, Moon, Users, Vote, Waves } from '@lucide/vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import RoleGuide from '@/components/chanting/RoleGuide.vue';
import VillageScene from '@/components/chanting/VillageScene.vue';
import GameGlossary from '@/components/chanting/GameGlossary.vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
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
        rules: () => ({ min_players: 3, max_players: 10 }),
    },
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
                <a href="#roles" class="quiet-link">Roles</a>
                <a href="/tutorial" class="quiet-link">Practice solo</a>
                <a href="#how-to-play" class="quiet-link"
                    >How to play <ArrowDown :size="14"
                /></a>
                <a
                    v-if="$page.props.auth.user"
                    href="/dashboard"
                    class="quiet-link"
                    >My account</a
                >
                <template v-else>
                    <a href="/login" class="quiet-link">Sign in</a>
                    <a href="/register" class="quiet-link account-signup"
                        >Create account</a
                    >
                </template>
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
                    <RoomEntry
                        :characters="characters"
                        :preferred-character="preferredCharacter"
                    />
                    <p class="tutorial-invitation">
                        First time here?
                        <a href="/tutorial" class="quiet-link"
                            >Try a guided round →</a
                        >
                        <span>Solo · No account needed · No timer</span>
                    </p>
                </div>
                <span class="hero-side-note" aria-hidden="true"
                    >WELCOME TO THE END OF THE WORLD. MAYBE.</span
                >
            </section>
            <section class="character-parade" aria-labelledby="character-title">
                <div>
                    <p class="eyebrow">A FAMILIAR FACE. AN UNFAMILIAR ALIBI.</p>
                    <h2 id="character-title">Meet your <em>neighbors.</em></h2>
                    <p>
                        Pick a face you love when you sign in. Guests get a
                        surprise.<br />Every character can have any role. Trust
                        nobody’s wardrobe.
                    </p>
                </div>
                <ul>
                    <li v-for="character in characters" :key="character.id">
                        <CharacterPortrait
                            :character="character.id"
                            decorative
                        /><span>{{ character.name }}</span>
                    </li>
                </ul>
            </section>
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
                            gets a secret role. Some get a much darker agenda.
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
                            vote per day. A tie for most votes, or abstention
                            winning the vote, banishes nobody. If you miss a
                            deadline, your action is forfeited. Banished players
                            can watch until the match ends.
                        </p>
                        <p>
                            <strong>Your role card tells the truth.</strong>
                            Cultists know each other and share one mission. The
                            Oracle sees an alignment, but the Veilweaver can
                            reverse one player’s reading for that night. Your
                            own role and mission are never disguised.
                        </p>
                        <p>
                            <strong>Curses wake at dawn.</strong> The Veilweaver
                            curses the player they veil; Acolytes may choose a
                            curse target while chanting. Soul Bind and Mind Mist
                            lock you in a challenge until solved or expired,
                            while the game keeps moving. At ritual level 3, from
                            two-thirds progress, misdirection can redirect the
                            next chosen target once, unless you first untangle
                            its rune rings. Every curse fades at the following
                            dawn.
                        </p>
                        <p>
                            Early curses have three simple seals to break. At
                            ritual level 2, solve two moderate seals; at level
                            3, face one harder challenge. Each cleared seal
                            stays cleared, even if you refresh.
                        </p>
                        <p>
                            <strong>The ritual is a race.</strong> When the
                            ritual track fills, the village gets one final
                            discussion and vote. Banish every remaining cultist
                            to win for the town; otherwise the cult summons
                            Cthulhu when that vote resolves. Each cultist can
                            add one step per night by chanting, if their shared
                            mission’s condition is met: chant together, avoid
                            investigation, or keep cultists safe in the previous
                            vote. The cult also wins if no town players remain,
                            or if the final two players are one cultist and one
                            town player.
                        </p>
                        <p>
                            <strong
                                >A small gathering, a quicker ritual.</strong
                            >
                            With 3 players, the ritual takes 3 steps; with 4, it
                            takes 4. The lone cultist adds one step each night
                            they chant, even if the Oracle investigates them.
                        </p>
                        <table
                            v-if="rules.ritual_goals?.length"
                            class="ritual-goals"
                        >
                            <caption>
                                How many steps complete the ritual?
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Players</th>
                                    <th scope="col">Steps to finish</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="goal in rules.ritual_goals"
                                    :key="goal.players"
                                >
                                    <td>{{ goal.players }}</td>
                                    <td>{{ goal.steps }} steps</td>
                                </tr>
                            </tbody>
                        </table>
                        <p>
                            <strong>Finished discussing?</strong> Mark yourself
                            ready for voting. Everyone sees your ready badge,
                            and voting starts as soon as every living player is
                            ready, or when the timer ends.
                        </p>
                        <p>
                            <strong
                                >First-edition rules, ready to evolve.</strong
                            >
                            Classic has {{ rules.min_players }}–{{
                                rules.max_players
                            }}
                            players and one Oracle. There is 1 cultist with 3–4
                            players, 2 with 5–6, 3 with 7–8, and 4 with 9–10. In
                            Classic, one cultist is the Veilweaver. Larger
                            gatherings replace an Acolyte with a Dreamweaver and
                            Townsperson seats with special Town roles; see the
                            role guide for their player thresholds. Hosts can
                            choose the Illusions roster with 5+ players for
                            Phantasm, Counterfeiter and Exorcist, plus
                            Oathkeeper at 8+. Default phases last 25 seconds for
                            the reveal, 45 for night, 90 for discussion and 45
                            for voting. These balance settings are provisional
                            and configurable.
                        </p>
                    </div>
                </details>
                <GameGlossary />
            </section>
        </main>
        <footer class="site-footer">
            <span><Eye :size="16" /> who’s chanting?</span>
            <p>Made for friends. Not necessarily for friendships.</p>
            <span class="footer-edition">PRIVATE ROOMS · FIRST EDITION</span>
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
