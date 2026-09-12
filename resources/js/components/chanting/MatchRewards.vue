<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import { defaultCharacters, seasonalCharacters } from '@/lib/chanting';
import type { MatchReward } from '@/lib/progression';
const props = defineProps<{ linked: boolean; reward?: MatchReward | null }>();
const unlockedCharacters = computed(() =>
    defaultCharacters.filter((character) =>
        props.reward?.characters?.includes(character.id),
    ),
);
const page = usePage();
</script>
<template>
    <section
        class="game-panel match-rewards"
        aria-labelledby="match-rewards-title"
    >
        <Sparkles :size="22" :stroke-width="1.4" aria-hidden="true" />
        <div v-if="reward">
            <p class="eyebrow">YOUR ACCOUNT REWARD</p>
            <h3 id="match-rewards-title">
                +{{ reward.xp }} XP
                <span v-if="reward.level_after > reward.level_before"
                    >· Level {{ reward.level_after }} reached</span
                >
            </h3>
            <p>
                {{
                    reward.achievements.length
                        ? `${reward.achievements.length} achievement${reward.achievements.length === 1 ? '' : 's'} earned. `
                        : ''
                }}Your lifetime and season records are updated.
            </p>
            <div v-if="unlockedCharacters.length" class="character-rewards">
                <h4>
                    New
                    {{
                        unlockedCharacters.length === 1
                            ? 'character'
                            : 'characters'
                    }}
                    unlocked
                </h4>
                <ul>
                    <li
                        v-for="character in unlockedCharacters"
                        :key="character.id"
                    >
                        <CharacterPortrait
                            :character="character.id"
                            decorative
                        />
                        <span>{{
                            seasonalCharacters[character.id]?.name ??
                            character.name
                        }}</span>
                    </li>
                </ul>
                <p>
                    Ready to wear in your next room. Choose your new look in the
                    wardrobe.
                </p>
            </div>
            <Link href="/progression"
                >View your progression <ArrowRight :size="14"
            /></Link>
        </div>
        <div v-else-if="linked">
            <h3 id="match-rewards-title">Your next chapter awaits.</h3>
            <p>
                Rewards require a completed match with at least one submitted
                night action and one submitted vote. Abstention counts.
            </p>
            <Link href="/progression"
                >View your progression <ArrowRight :size="14"
            /></Link>
        </div>
        <div v-else>
            <h3 id="match-rewards-title">
                Give your next match a place in the ledger.
            </h3>
            <p v-if="page.props.auth?.user">
                This seat was not linked to your account when the match started.
                Join or create your next room while signed in with a verified
                account to earn rewards.
            </p>
            <p v-else>
                Play with a verified account to earn levels, achievements, and
                cosmetics in future matches.
            </p>
            <Link v-if="page.props.auth?.user" href="/progression"
                >Prepare your account for the next match <ArrowRight :size="14"
            /></Link>
            <Link v-else href="/register"
                >Create an account <ArrowRight :size="14"
            /></Link>
        </div>
    </section>
</template>
<style scoped>
.match-rewards {
    display: flex;
    align-items: start;
    gap: 16px;
}
.match-rewards > svg {
    flex-shrink: 0;
    color: var(--sage, #b9cda8);
    margin-top: 3px;
}
.match-rewards h3 {
    margin: 5px 0 8px;
    font-size: 22px;
}
.match-rewards h3 span {
    font-family: 'DM Sans', sans-serif;
    font-size: 11px;
}
.match-rewards p:not(.eyebrow) {
    font-size: 12px;
    line-height: 1.7;
    opacity: 0.85;
}
.match-rewards a {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 12px;
    margin-top: 12px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.match-rewards > div {
    min-width: 0;
}
.character-rewards {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #b9cda833;
}
.character-rewards h4 {
    font-size: 16px;
    color: var(--sage, #b9cda8);
}
.character-rewards ul {
    list-style: none;
    padding: 0;
    margin: 12px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 12px 22px;
}
.character-rewards li {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
}
.character-rewards .character-portrait {
    width: 44px;
    height: 50px;
    flex-shrink: 0;
}
</style>
