<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    LockKeyhole,
    Shield,
    Sparkles,
    Trophy,
    Waves,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import { roomRequest, type Character } from '@/lib/chanting';
import {
    cosmeticAccents,
    progressPercent,
    recordDate,
    type ProgressionData,
} from '@/lib/progression';

const props = defineProps<{
    progression: ProgressionData;
    characters: Character[];
}>();
const page = usePage();
const data = ref(props.progression);
const draft = reactive({ ...data.value.profile.equipped });
const pending = ref(false);
const error = ref('');
const saved = ref(false);
const tab = ref<'wardrobe' | 'achievements' | 'season'>('wardrobe');
const tabs = [
    { id: 'wardrobe', name: 'Wardrobe' },
    { id: 'achievements', name: 'Achievements' },
    { id: 'season', name: 'Season record' },
] as const;
const changed = computed(() =>
    Object.keys(draft).some(
        (key) =>
            draft[key as keyof typeof draft] !==
            data.value.profile.equipped[key as keyof typeof draft],
    ),
);
const titleName = computed(
    () =>
        data.value.cosmetics.titles.find((item) => item.id === draft.title)
            ?.name ?? 'Newcomer',
);
const earned = computed(
    () => data.value.achievements.filter((item) => item.earned_at).length,
);
const groups = [
    { field: 'title', catalog: 'titles', name: 'Your title' },
    { field: 'frame', catalog: 'frames', name: 'Portrait frame' },
    { field: 'accent', catalog: 'accents', name: 'Accent color' },
] as const;
async function save() {
    if (pending.value || !changed.value) return;
    pending.value = true;
    error.value = '';
    saved.value = false;
    try {
        const result = await roomRequest<{ progression: ProgressionData }>(
            '/account/customization',
            { ...draft },
        );
        data.value = result.progression;
        Object.assign(draft, result.progression.profile.equipped);
        saved.value = true;
    } catch (caught) {
        error.value =
            caught instanceof Error
                ? caught.message
                : 'Your customization could not be saved. Please try again.';
    } finally {
        pending.value = false;
    }
}
function moveTab(event: KeyboardEvent, index: number) {
    let next = index;
    if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
    else if (event.key === 'ArrowLeft')
        next = (index + tabs.length - 1) % tabs.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = tabs.length - 1;
    else return;
    event.preventDefault();
    tab.value = tabs[next].id;
    document.getElementById(`progression-tab-${tab.value}`)?.focus();
}
</script>

<template>
    <Head title="Your reputation" />
    <div class="reputation-page">
        <header class="account-page-heading">
            <div>
                <p class="account-kicker">
                    The village ledger <span>/</span> Progression
                </p>
                <h1>Make a name. <em>Keep a secret.</em></h1>
            </div>
            <Link href="/dashboard" class="account-text-link"
                >Take your seat <ArrowRight :size="16"
            /></Link>
        </header>
        <section class="reputation-overview" aria-label="Your resident record">
            <div
                class="reputation-passport"
                :style="{
                    '--identity-accent':
                        cosmeticAccents[draft.accent] ?? cosmeticAccents.sea,
                }"
            >
                <div class="passport-top">
                    <span>RESIDENT OF THE VILLAGE</span
                    ><Shield :size="17" :stroke-width="1.3" />
                </div>
                <CharacterPortrait
                    :character="
                        draft.character ?? characters[0]?.id ?? 'fisherman'
                    "
                    :frame="draft.frame"
                    :accent="draft.accent"
                    class="passport-portrait"
                />
                <h2>{{ page.props.auth.user.name }}</h2>
                <p class="passport-title">{{ titleName }}</p>
                <div class="passport-level">
                    <span>LEVEL</span><strong>{{ data.profile.level }}</strong
                    ><span
                        >{{ data.profile.xp.toLocaleString() }} LIFETIME
                        XP</span
                    >
                </div>
                <p class="passport-note">
                    {{
                        changed
                            ? 'Wardrobe preview · not saved yet'
                            : 'Your public identity in the village'
                    }}
                </p>
            </div>
            <div class="reputation-record">
                <div class="reputation-level">
                    <p class="account-kicker">01 / A reputation earned</p>
                    <h2>Every night leaves a mark.</h2>
                    <p>
                        Play your part, earn experience, and collect a little
                        history.
                    </p>
                    <div class="xp-caption">
                        <strong>Level {{ data.profile.level }}</strong
                        ><span
                            >{{ data.profile.level_xp }} /
                            {{ data.profile.next_level_xp }} XP to level
                            {{ data.profile.level + 1 }}</span
                        >
                    </div>
                    <progress
                        :value="
                            progressPercent(
                                data.profile.level_xp,
                                data.profile.next_level_xp,
                            )
                        "
                        max="100"
                        aria-label="Progress to next level"
                    ></progress>
                    <dl class="reputation-stats">
                        <div>
                            <dd>{{ data.profile.matches }}</dd>
                            <dt>Matches</dt>
                        </div>
                        <div>
                            <dd>{{ data.profile.wins }}</dd>
                            <dt>Victories</dt>
                        </div>
                        <div>
                            <dd>
                                {{ earned
                                }}<small>
                                    / {{ data.achievements.length }}</small
                                >
                            </dd>
                            <dt>Achievements</dt>
                        </div>
                    </dl>
                </div>
                <div class="reputation-season">
                    <Waves :size="30" :stroke-width="1.2" />
                    <div>
                        <p class="account-kicker">{{ data.season.name }}</p>
                        <h3>
                            {{ data.season.tier.name }}
                            <span
                                >·
                                {{ data.season.xp.toLocaleString() }} XP</span
                            >
                        </h3>
                        <p>
                            {{
                                data.season.next_tier_xp === null
                                    ? 'Highest season tier reached.'
                                    : `${Math.max(0, data.season.next_tier_xp - data.season.xp)} XP to the next tier.`
                            }}
                            Ends {{ recordDate(data.season.ends_at) }} (UTC).
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <nav
            class="reputation-tabs"
            role="tablist"
            aria-label="Progression sections"
        >
            <button
                v-for="(item, index) in tabs"
                :id="`progression-tab-${item.id}`"
                :key="item.id"
                type="button"
                role="tab"
                :aria-selected="tab === item.id"
                :aria-controls="`progression-panel-${item.id}`"
                :tabindex="tab === item.id ? 0 : -1"
                @click="tab = item.id"
                @keydown="moveTab($event, index)"
            >
                {{ item.name
                }}<span v-if="item.id === 'achievements'"
                    >{{ earned }}/{{ data.achievements.length }}</span
                >
            </button>
        </nav>
        <section
            v-show="tab === 'wardrobe'"
            id="progression-panel-wardrobe"
            class="reputation-panel"
            role="tabpanel"
            aria-labelledby="progression-tab-wardrobe"
            tabindex="0"
        >
            <div class="reputation-section-intro">
                <p class="account-kicker">02 / Familiar face, new details</p>
                <h2>Dress for suspicion.</h2>
                <p>
                    Titles, frames, and colors are cosmetic. Your character
                    never reveals your role, and unlocks never make you
                    stronger.
                </p>
            </div>
            <form
                class="wardrobe-form"
                :aria-busy="pending"
                @submit.prevent="save"
            >
                <fieldset
                    v-for="group in groups"
                    :key="group.field"
                    :disabled="pending"
                    class="wardrobe-fieldset"
                >
                    <legend>{{ group.name }}</legend>
                    <div class="wardrobe-options">
                        <label
                            v-for="item in data.cosmetics[group.catalog]"
                            :key="item.id"
                            class="wardrobe-choice"
                            :class="{
                                'is-selected': draft[group.field] === item.id,
                                'is-locked': !item.unlocked,
                            }"
                            ><input
                                v-model="draft[group.field]"
                                type="radio"
                                :name="group.field"
                                :value="item.id"
                                :disabled="!item.unlocked"
                                @change="saved = false" /><span
                                v-if="group.field === 'accent'"
                                class="accent-swatch"
                                :style="{
                                    background:
                                        cosmeticAccents[item.id] ??
                                        cosmeticAccents.sea,
                                }"
                                aria-hidden="true"
                            ></span
                            ><span class="wardrobe-choice-copy"
                                ><strong>{{ item.name }}</strong
                                ><small>{{
                                    item.unlocked
                                        ? draft[group.field] === item.id
                                            ? 'Selected'
                                            : 'Unlocked'
                                        : item.requirement
                                }}</small></span
                            ><LockKeyhole
                                v-if="!item.unlocked"
                                :size="13"
                                aria-label="Locked" /><Check
                                v-else-if="draft[group.field] === item.id"
                                :size="14"
                                aria-hidden="true"
                        /></label>
                    </div>
                </fieldset>
                <fieldset class="wardrobe-fieldset" :disabled="pending">
                    <legend>Preferred character</legend>
                    <p class="wardrobe-help">
                        A familiar face for your next room. You can still change
                        it in the lobby.
                    </p>
                    <div class="wardrobe-characters">
                        <label
                            class="wardrobe-character"
                            :class="{ 'is-selected': draft.character === null }"
                            ><input
                                v-model="draft.character"
                                type="radio"
                                name="character"
                                :value="null"
                                @change="saved = false"
                            /><span class="character-any"
                                ><Sparkles :size="24" /></span
                            ><span>No preference</span></label
                        ><label
                            v-for="character in characters"
                            :key="character.id"
                            class="wardrobe-character"
                            :class="{
                                'is-selected': draft.character === character.id,
                            }"
                            ><input
                                v-model="draft.character"
                                type="radio"
                                name="character"
                                :value="character.id"
                                @change="saved = false"
                            /><CharacterPortrait
                                :character="character.id"
                                decorative
                            /><span>{{ character.name }}</span></label
                        >
                    </div>
                </fieldset>
                <p v-if="error" class="wardrobe-error" role="alert">
                    {{ error }}
                </p>
                <div class="wardrobe-save">
                    <p role="status">
                        {{
                            saved && !changed
                                ? 'Your customization is saved.'
                                : changed
                                  ? 'You have unsaved changes.'
                                  : 'Your equipped details appear beside you in the village.'
                        }}
                    </p>
                    <button type="submit" :disabled="pending || !changed">
                        {{ pending ? 'Saving…' : 'Save customization'
                        }}<Check
                            v-if="saved && !changed"
                            :size="16"
                        /><ArrowRight v-else :size="16" />
                    </button>
                </div>
            </form>
        </section>
        <section
            v-show="tab === 'achievements'"
            id="progression-panel-achievements"
            class="reputation-panel"
            role="tabpanel"
            aria-labelledby="progression-tab-achievements"
            tabindex="0"
        >
            <div class="reputation-section-intro">
                <p class="account-kicker">02 / Stories worth keeping</p>
                <h2>A collection of small legends.</h2>
                <p>
                    Achievements stay with your account across seasons. Earn
                    them through completed, eligible matches.
                </p>
            </div>
            <ul class="achievement-ledger">
                <li
                    v-for="achievement in data.achievements"
                    :key="achievement.id"
                    :class="{ 'is-earned': achievement.earned_at }"
                >
                    <span class="achievement-seal"
                        ><Trophy
                            v-if="achievement.earned_at"
                            :size="21"
                            :stroke-width="1.4" /><LockKeyhole
                            v-else
                            :size="20"
                            :stroke-width="1.3"
                    /></span>
                    <div>
                        <h3>{{ achievement.name }}</h3>
                        <p>{{ achievement.description }}</p>
                        <span
                            v-if="achievement.earned_at"
                            class="achievement-earned"
                            >Earned
                            {{ recordDate(achievement.earned_at) }}</span
                        ><template v-else
                            ><progress
                                :value="
                                    Math.min(
                                        achievement.current,
                                        achievement.target,
                                    )
                                "
                                :max="achievement.target"
                                :aria-label="`${achievement.name} progress`"
                            ></progress
                            ><small
                                >{{
                                    Math.min(
                                        achievement.current,
                                        achievement.target,
                                    )
                                }}
                                / {{ achievement.target }}</small
                            ></template
                        >
                    </div>
                </li>
            </ul>
        </section>
        <section
            v-show="tab === 'season'"
            id="progression-panel-season"
            class="reputation-panel"
            role="tabpanel"
            aria-labelledby="progression-tab-season"
            tabindex="0"
        >
            <div class="reputation-section-intro">
                <p class="account-kicker">02 / The turning tide</p>
                <h2>{{ data.season.name }}</h2>
                <p>
                    {{ recordDate(data.season.starts_at) }} –
                    {{ recordDate(data.season.ends_at) }} (UTC). A fresh season
                    every quarter. Lifetime levels, cosmetics, and your past
                    season record stay yours.
                </p>
            </div>
            <div>
                <ol class="season-tiers">
                    <li
                        v-for="tier in data.season.tiers"
                        :key="tier.id"
                        :class="{ 'is-unlocked': tier.unlocked }"
                    >
                        <Check v-if="tier.unlocked" :size="17" /><LockKeyhole
                            v-else
                            :size="15"
                        /><strong>{{ tier.name }}</strong
                        ><span>{{ tier.xp.toLocaleString() }} XP</span
                        ><small>{{
                            tier.id === data.season.tier.id
                                ? 'Current tier'
                                : tier.unlocked
                                  ? 'Reached'
                                  : 'Ahead'
                        }}</small>
                    </li>
                </ol>
                <p class="season-total">
                    This season: {{ data.season.matches }} matches ·
                    {{ data.season.wins }} victories ·
                    {{ data.season.xp.toLocaleString() }} XP
                </p>
                <h3 class="record-subtitle">Previous seasons</h3>
                <p v-if="!data.season.history.length" class="record-empty">
                    Your first season is still being written. Completed seasons
                    will appear here.
                </p>
                <ul v-else class="season-history">
                    <li v-for="season in data.season.history" :key="season.id">
                        <strong>{{ season.id }}</strong
                        ><span
                            >{{ season.tier }} ·
                            {{ season.xp.toLocaleString() }} XP</span
                        ><small
                            >{{ season.matches }} matches ·
                            {{ season.wins }} wins</small
                        >
                    </li>
                </ul>
                <h3 class="record-subtitle">Recent rewards</h3>
                <p v-if="!data.recent_rewards.length" class="record-empty">
                    No rewards yet. Join a room with your verified account and
                    see a match through.
                </p>
                <ul v-else class="recent-rewards">
                    <li
                        v-for="reward in data.recent_rewards"
                        :key="reward.match_id"
                    >
                        <div>
                            <strong>{{
                                reward.won
                                    ? 'A victory to remember'
                                    : 'Another night survived in memory'
                            }}</strong
                            ><small
                                >{{ recordDate(reward.earned_at) }} ·
                                {{ reward.season_id }}</small
                            >
                        </div>
                        <span>+{{ reward.xp }} XP</span>
                    </li>
                </ul>
            </div>
        </section>
        <aside class="reputation-rules">
            <Shield :size="20" :stroke-width="1.4" />
            <div>
                <h2>Earned at the table.</h2>
                <p>
                    Join or create a room with a verified account. Finish a
                    match with at least one submitted night action and one vote
                    (abstaining counts): 80 XP for participation, 40 more for a
                    win, and 20 more for submitting every eligible night action
                    and vote. Guests can play; account rewards begin with future
                    matches after signing up and verifying.
                </p>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.reputation-overview {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 48px;
    align-items: stretch;
    margin: 32px 0 35px;
}
.reputation-passport {
    position: relative;
    background: #15272b;
    color: #f1ecdb;
    border: 1px solid #60706a;
    padding: 21px 27px 19px;
    text-align: center;
    box-shadow: 5px 5px 0 var(--account-deep);
}
.passport-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 8px;
    letter-spacing: 1.8px;
    color: #c2cdbb;
}
.passport-portrait {
    display: block;
    width: 116px;
    height: 132px;
    margin: 29px auto 20px;
    border-radius: 65px 65px 3px 3px;
    border: 1px solid var(--identity-accent);
}
.reputation-passport h2 {
    font-size: 29px;
    line-height: 1.2;
    overflow-wrap: anywhere;
}
.passport-title {
    color: var(--identity-accent);
    font-size: 11px;
    letter-spacing: 1.7px;
    text-transform: uppercase;
    margin: 9px 0 20px;
}
.passport-level {
    border-top: 1px solid #52605a;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    padding-top: 17px;
}
.passport-level span {
    font-size: 8px;
    letter-spacing: 1.2px;
}
.passport-level strong {
    font:
        32px 'Fraunces',
        Georgia,
        serif;
    color: var(--identity-accent);
}
.passport-note {
    font-size: 9px;
    color: #b5c4ba;
    margin-top: 10px;
}
.reputation-record {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 24px;
    padding: 12px 0 0;
    min-width: 0;
}
.reputation-level h2 {
    font-size: 31px;
    letter-spacing: -0.8px;
    margin: 10px 0;
}
.reputation-level > p:not(.account-kicker) {
    color: var(--account-muted);
    font-size: 12px;
}
.xp-caption {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin: 30px 0 10px;
    font-size: 11px;
}
.xp-caption span {
    color: var(--account-muted);
}
progress {
    appearance: none;
    display: block;
    width: 100%;
    height: 5px;
    background: var(--account-deep);
    border: 0;
    border-radius: 0;
    accent-color: var(--account-green);
}
progress::-webkit-progress-bar {
    background: var(--account-deep);
}
progress::-webkit-progress-value {
    background: var(--account-green);
}
progress::-moz-progress-bar {
    background: var(--account-green);
}
.reputation-stats {
    display: flex;
    gap: 40px;
    margin-top: 25px;
}
.reputation-stats dd {
    font:
        29px 'Fraunces',
        Georgia,
        serif;
}
.reputation-stats small {
    font:
        12px 'DM Sans',
        sans-serif;
    color: var(--account-muted);
}
.reputation-stats dt {
    font-size: 10px;
    color: var(--account-muted);
    margin-top: 5px;
}
.reputation-season {
    display: flex;
    gap: 19px;
    padding: 24px 0 4px;
    border-top: 1px solid var(--account-line);
    align-items: center;
}
.reputation-season > svg {
    color: var(--account-brass);
}
.reputation-season h3 {
    font-size: 24px;
    margin: 5px 0;
}
.reputation-season h3 span {
    font:
        12px 'DM Sans',
        sans-serif;
    color: var(--account-muted);
}
.reputation-season div > p:last-child {
    font-size: 10px;
    color: var(--account-muted);
    line-height: 1.7;
}
.reputation-tabs {
    display: flex;
    gap: 32px;
    border-bottom: 1px solid var(--account-line);
}
.reputation-tabs button {
    padding: 17px 0;
    position: relative;
    font-size: 12px;
    color: var(--account-muted);
}
.reputation-tabs button[aria-selected='true'] {
    color: var(--account-text);
}
.reputation-tabs button[aria-selected='true']::after {
    position: absolute;
    content: '';
    height: 2px;
    bottom: -1px;
    inset-inline: 0;
    background: var(--account-green);
}
.reputation-tabs button span {
    margin-left: 9px;
    font-size: 9px;
    color: var(--account-brass);
}
.reputation-panel {
    display: grid;
    grid-template-columns: 230px minmax(0, 1fr);
    gap: 55px;
    padding: 35px 0;
}
.reputation-section-intro h2 {
    font-size: 27px;
    line-height: 1.2;
    letter-spacing: -0.6px;
    margin: 13px 0;
}
.reputation-section-intro > p:last-child {
    font-size: 12px;
    color: var(--account-muted);
    line-height: 1.8;
}
.wardrobe-fieldset {
    border: 0;
    padding: 0;
    margin: 0 0 25px;
    min-width: 0;
}
.wardrobe-fieldset legend {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 20px;
    margin-bottom: 12px;
}
.wardrobe-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.wardrobe-choice {
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid var(--account-line);
    padding: 12px 11px;
    cursor: pointer;
    min-width: 0;
}
.wardrobe-choice.is-selected {
    border-color: var(--account-green);
    background: color-mix(in srgb, var(--account-green) 8%, transparent);
}
.wardrobe-choice.is-locked {
    cursor: not-allowed;
    background: color-mix(in srgb, var(--account-deep) 40%, transparent);
}
.wardrobe-choice input {
    accent-color: var(--account-green);
    width: 12px;
    height: 12px;
    flex-shrink: 0;
}
.wardrobe-choice-copy {
    flex: 1;
    min-width: 0;
}
.wardrobe-choice strong {
    display: block;
    font-size: 11px;
    font-weight: 500;
}
.wardrobe-choice small {
    display: block;
    font-size: 9px;
    color: var(--account-muted);
    line-height: 1.6;
    margin-top: 3px;
}
.wardrobe-choice > svg {
    color: var(--account-muted);
}
.accent-swatch {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid #263a3870;
    flex-shrink: 0;
}
.wardrobe-help {
    font-size: 11px;
    color: var(--account-muted);
    margin: -3px 0 14px;
}
.wardrobe-characters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
}
.wardrobe-character {
    border: 1px solid var(--account-line);
    padding: 10px 5px;
    display: flex;
    align-items: center;
    flex-direction: column;
    gap: 8px;
    position: relative;
    cursor: pointer;
}
.wardrobe-character.is-selected {
    border-color: var(--account-green);
    background: color-mix(in srgb, var(--account-green) 8%, transparent);
}
.wardrobe-character > input {
    position: absolute;
    top: 6px;
    left: 6px;
    width: 11px;
    height: 11px;
    accent-color: var(--account-green);
}
.wardrobe-character .character-portrait,
.character-any {
    width: 40px;
    height: 47px;
    display: grid;
    place-items: center;
    border-radius: 24px 24px 2px 2px;
    margin-top: 4px;
}
.character-any {
    color: var(--account-brass);
}
.wardrobe-character > span:last-child {
    font-size: 8px;
    text-align: center;
    line-height: 1.5;
}
.wardrobe-save {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-top: 1px solid var(--account-line);
    padding-top: 20px;
}
.wardrobe-save p {
    color: var(--account-muted);
    font-size: 10px;
}
.wardrobe-save button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    min-height: 44px;
    background: var(--primary);
    color: var(--primary-foreground);
    padding: 12px 18px;
    font-size: 11px;
    flex-shrink: 0;
}
.wardrobe-save button:disabled {
    opacity: 0.55;
}
.wardrobe-error {
    color: var(--destructive);
    font-size: 12px;
    padding: 14px 0;
}
.achievement-ledger {
    list-style: none;
    margin: 0;
    padding: 0;
}
.achievement-ledger li {
    display: flex;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--account-line);
}
.achievement-ledger li:first-child {
    padding-top: 0;
}
.achievement-seal {
    display: grid;
    place-items: center;
    width: 43px;
    height: 50px;
    flex-shrink: 0;
    border: 1px solid var(--account-line);
    border-radius: 24px 24px 2px 2px;
    color: var(--account-muted);
}
.is-earned .achievement-seal {
    color: var(--account-green);
    border-color: var(--account-green);
}
.achievement-ledger li > div {
    flex: 1;
}
.achievement-ledger h3 {
    font-size: 21px;
}
.achievement-ledger p {
    color: var(--account-muted);
    font-size: 11px;
    line-height: 1.7;
    margin-top: 5px;
}
.achievement-ledger progress {
    max-width: 220px;
    height: 3px;
    margin: 13px 0 6px;
}
.achievement-ledger small,
.achievement-earned {
    font-size: 9px;
    color: var(--account-muted);
}
.achievement-earned {
    display: block;
    color: var(--account-green);
    margin-top: 10px;
}
.season-tiers {
    list-style: none;
    margin: 0;
    padding: 0;
}
.season-tiers li {
    display: grid;
    grid-template-columns: 18px 1fr auto;
    align-items: center;
    gap: 4px 12px;
    padding: 15px 0;
    border-bottom: 1px solid var(--account-line);
}
.season-tiers li:first-child {
    padding-top: 0;
}
.season-tiers strong {
    font:
        21px 'Fraunces',
        Georgia,
        serif;
}
.season-tiers span,
.season-tiers small {
    font-size: 10px;
    color: var(--account-muted);
}
.season-tiers small {
    grid-column: 2/4;
}
.season-tiers .is-unlocked > svg {
    color: var(--account-green);
}
.season-total {
    font-size: 11px;
    color: var(--account-muted);
    margin-top: 17px;
}
.record-subtitle {
    font-size: 23px;
    margin: 30px 0 12px;
}
.record-empty {
    font-size: 12px;
    color: var(--account-muted);
    line-height: 1.8;
}
.season-history,
.recent-rewards {
    list-style: none;
    margin: 0;
    padding: 0;
}
.season-history li,
.recent-rewards li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 7px 20px;
    border-bottom: 1px solid var(--account-line);
    padding: 13px 0;
    font-size: 11px;
}
.season-history small {
    flex-basis: 100%;
    color: var(--account-muted);
    font-size: 10px;
}
.recent-rewards strong {
    font-size: 11px;
    font-weight: 500;
}
.recent-rewards small {
    display: block;
    color: var(--account-muted);
    font-size: 9px;
    margin-top: 4px;
}
.recent-rewards li > span {
    color: var(--account-green);
    font-weight: 600;
}
.reputation-rules {
    border-top: 1px solid var(--account-line);
    padding: 25px 0 0;
    display: flex;
    gap: 16px;
    color: var(--account-muted);
}
.reputation-rules > svg {
    margin-top: 3px;
    color: var(--account-brass);
}
.reputation-rules h2 {
    font-size: 21px;
    color: var(--account-text);
}
.reputation-rules p {
    font-size: 11px;
    line-height: 1.8;
    margin-top: 9px;
    max-width: 850px;
}
@media (max-width: 1000px) {
    .reputation-overview {
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 30px;
    }
    .reputation-panel {
        grid-template-columns: 190px minmax(0, 1fr);
        gap: 30px;
    }
    .wardrobe-characters {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .reputation-stats {
        gap: 25px;
    }
    .reputation-level h2 {
        font-size: 28px;
    }
}
@media (max-width: 700px) {
    .account-page-heading {
        align-items: start;
    }
    .account-page-heading > a {
        display: none;
    }
    .reputation-overview {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
        margin-top: 25px;
    }
    .reputation-passport {
        max-width: 400px;
        width: 100%;
        justify-self: center;
    }
    .reputation-record {
        padding-top: 0;
    }
    .reputation-level h2 {
        font-size: 28px;
    }
    .reputation-stats {
        justify-content: space-between;
    }
    .reputation-panel {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
        padding: 28px 0;
    }
    .reputation-section-intro h2 {
        font-size: 27px;
        margin: 9px 0;
    }
    .reputation-section-intro > p:last-child {
        font-size: 11px;
    }
    .reputation-tabs {
        gap: 22px;
        justify-content: space-between;
    }
    .reputation-tabs button {
        font-size: 11px;
    }
    .reputation-tabs button span {
        display: none;
    }
    .wardrobe-options {
        gap: 7px;
    }
    .wardrobe-choice {
        padding: 10px 8px;
        gap: 6px;
    }
    .wardrobe-characters {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .wardrobe-save {
        align-items: stretch;
        flex-direction: column;
        gap: 13px;
    }
    .wardrobe-save button {
        width: 100%;
    }
    .reputation-season {
        padding-top: 20px;
    }
    .xp-caption {
        margin-top: 23px;
    }
    .reputation-rules p {
        font-size: 10px;
    }
}
</style>
