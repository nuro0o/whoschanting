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
import SealedCharacter from '@/components/chanting/SealedCharacter.vue';
import CreatorMirror from '@/components/chanting/CreatorMirror.vue';
import {
    creatorLockedOptions,
    defaultCreator,
    type CreatorRecipe,
} from '@/lib/creator';
import { characterIds, roomRequest, type Character } from '@/lib/chanting';
import {
    cosmeticAccents,
    cosmeticBackgrounds,
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
const characters = computed(() => data.value.characters ?? props.characters);
const originalCharacters = computed(() =>
    characters.value.filter(
        (item) => item.id !== 'custom' && characterIds.indexOf(item.id) < 16,
    ),
);
const earnedCharacters = computed(() =>
    characters.value.filter(
        (item) => characterIds.indexOf(item.id) >= 16 && !item.seasonal,
    ),
);
const seasonalCharacters = computed(() =>
    characters.value.filter((item) => item.seasonal),
);
const wardrobeCharacterGroups = computed(() => [
    {
        id: 'earned',
        name: 'Earned in the village',
        description:
            'New faces, earned through play. Every character is cosmetic.',
        characters: earnedCharacters.value,
    },
    {
        id: 'seasonal',
        name: 'Seasonal characters',
        description:
            'A secret for each class. Unlocked characters are yours to keep and wear with any role.',
        characters: seasonalCharacters.value,
    },
]);
const availableCharacters = computed(
    () => characters.value.filter((item) => item.unlocked !== false).length,
);
const draft = reactive({
    ...data.value.profile.equipped,
    background: data.value.profile.equipped.background ?? 'plain',
    creator: data.value.profile.equipped.creator
        ? { ...data.value.profile.equipped.creator }
        : null,
});
const pending = ref(false);
const error = ref('');
const saved = ref(false);
const previewElement = ref<HTMLElement | null>(null);
const tab = ref<'wardrobe' | 'achievements' | 'season'>('wardrobe');
const tabs = [
    { id: 'wardrobe', name: 'Wardrobe' },
    { id: 'achievements', name: 'Achievements' },
    { id: 'season', name: 'Season record' },
] as const;
const changed = computed(() =>
    Object.keys(draft).some(
        (key) =>
            JSON.stringify(draft[key as keyof typeof draft] ?? null) !==
            JSON.stringify(
                data.value.profile.equipped[key as keyof typeof draft] ?? null,
            ),
    ),
);
const mirrorOpen = ref(draft.character === 'custom');
const lockedPieces = computed(() =>
    creatorLockedOptions(draft.creator, data.value.creator),
);
const mirrorRecipe = computed({
    get: () => draft.creator ?? data.value.creator?.default ?? defaultCreator,
    set: (recipe: CreatorRecipe) => {
        draft.creator = { ...recipe };
        draft.character = 'custom';
        saved.value = false;
    },
});
function showPortraits() {
    mirrorOpen.value = false;
    if (lockedPieces.value.length) {
        draft.creator = data.value.profile.equipped.creator
            ? { ...data.value.profile.equipped.creator }
            : null;
        draft.character = data.value.profile.equipped.character;
    }
}
function openMirror() {
    mirrorOpen.value = true;
    draft.creator = { ...mirrorRecipe.value };
    draft.character = 'custom';
    saved.value = false;
}
const titleName = computed(
    () =>
        data.value.cosmetics.titles.find((item) => item.id === draft.title)
            ?.name ?? 'Newcomer',
);
const allAchievements = computed(() => [
    ...data.value.achievements,
    ...(data.value.seasonal_achievements?.achievements ?? []),
]);
const earned = computed(
    () => allAchievements.value.filter((item) => item.earned_at).length,
);
const groups = [
    { field: 'accent', catalog: 'accents', name: 'Accent color' },
    { field: 'background', catalog: 'backgrounds', name: 'Portrait backdrop' },
    { field: 'frame', catalog: 'frames', name: 'Portrait frame' },
    { field: 'title', catalog: 'titles', name: 'Your title' },
] as const;
const previewCharacter = computed(
    () => draft.character ?? characters.value[0]?.id ?? 'fisherman',
);
const characterName = computed(() =>
    previewCharacter.value === 'custom'
        ? 'Your creation'
        : (characters.value.find((item) => item.id === previewCharacter.value)
              ?.name ?? 'The Fisherman'),
);
function revert() {
    if (pending.value) return;
    Object.assign(draft, data.value.profile.equipped, {
        creator: data.value.profile.equipped.creator
            ? { ...data.value.profile.equipped.creator }
            : null,
    });
    error.value = '';
    saved.value = false;
}
function revealFocusedControl(event: FocusEvent) {
    const target = event.target;
    if (
        !(target instanceof HTMLElement) ||
        !target.closest('.wardrobe-controls')
    )
        return;
    // Native focus scrolling does not account for a sticky sibling above the form.
    requestAnimationFrame(() => {
        if (
            document.activeElement !== target ||
            !window.matchMedia('(max-width: 850px)').matches
        )
            return;
        const preview = previewElement.value?.getBoundingClientRect();
        if (!preview) return;
        const control = (
            target.closest('label') ?? target
        ).getBoundingClientRect();
        if (control.top < preview.bottom + 16) {
            window.scrollBy({
                top: control.top - preview.bottom - 16,
                behavior: 'instant',
            });
        }
    });
}
async function save() {
    if (pending.value || !changed.value || lockedPieces.value.length) return;
    pending.value = true;
    error.value = '';
    saved.value = false;
    try {
        const result = await roomRequest<{ progression: ProgressionData }>(
            '/account/customization',
            { ...draft },
        );
        data.value = result.progression;
        Object.assign(draft, result.progression.profile.equipped, {
            creator: result.progression.profile.equipped.creator
                ? { ...result.progression.profile.equipped.creator }
                : null,
        });
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
                    :creator="draft.creator"
                    :frame="draft.frame"
                    :accent="draft.accent"
                    :background="draft.background"
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
                                }}<small> / {{ allAchievements.length }}</small>
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
                    >{{ earned }}/{{ allAchievements.length }}</span
                >
            </button>
        </nav>
        <section
            v-show="tab === 'wardrobe'"
            id="progression-panel-wardrobe"
            class="reputation-panel wardrobe-panel"
            role="tabpanel"
            aria-labelledby="progression-tab-wardrobe"
            tabindex="0"
        >
            <div class="reputation-section-intro">
                <p class="account-kicker">02 / Familiar face, new details</p>
                <h2>Dress for suspicion.</h2>
                <p>
                    Make an unlocked character your own with accent colors,
                    backdrops, frames, and a title. Every detail is cosmetic;
                    your role stays a secret.
                </p>
            </div>
            <div
                v-if="data.creator"
                class="wardrobe-mode"
                role="group"
                aria-label="Choose how to make your character"
            >
                <button
                    type="button"
                    :aria-pressed="!mirrorOpen"
                    :disabled="pending"
                    @click="showPortraits"
                >
                    Village portraits <span>Choose a familiar face</span>
                </button>
                <button
                    type="button"
                    :aria-pressed="mirrorOpen"
                    :disabled="pending"
                    @click="openMirror"
                >
                    Create your own <span>Step into the looking glass</span
                    ><Sparkles :size="19" aria-hidden="true" />
                </button>
            </div>
            <form
                class="wardrobe-form"
                :class="{ 'wardrobe-form--mirror': mirrorOpen && data.creator }"
                :aria-busy="pending"
                @submit.prevent="save"
                @focusin="revealFocusedControl"
            >
                <CreatorMirror
                    v-if="mirrorOpen && data.creator"
                    v-model="mirrorRecipe"
                    :catalog="data.creator"
                    :disabled="pending"
                    :changed="changed"
                    :notice="
                        saved && !changed
                            ? 'Your appearance is saved.'
                            : undefined
                    "
                    :error="error"
                    :frame="draft.frame"
                    :accent="draft.accent"
                    :background="draft.background"
                    @revert="revert"
                />
                <aside
                    v-show="!mirrorOpen || !data.creator"
                    ref="previewElement"
                    class="wardrobe-preview"
                    aria-label="Live character preview"
                    :style="{
                        '--identity-accent':
                            cosmeticAccents[draft.accent] ??
                            cosmeticAccents.sea,
                    }"
                >
                    <div class="preview-heading">
                        <span>YOUR VILLAGE IDENTITY</span
                        ><span
                            class="preview-status"
                            :class="{ 'is-unsaved': changed }"
                            >{{ changed ? 'Unsaved' : 'Equipped' }}</span
                        >
                    </div>
                    <div class="preview-portraits">
                        <figure class="preview-full">
                            <CharacterPortrait
                                :character="previewCharacter"
                                :creator="draft.creator"
                                :frame="draft.frame"
                                :accent="draft.accent"
                                :background="draft.background"
                            />
                            <figcaption>Portrait</figcaption>
                        </figure>
                        <figure class="preview-table">
                            <CharacterPortrait
                                :character="previewCharacter"
                                :creator="draft.creator"
                                :frame="draft.frame"
                                :accent="draft.accent"
                                :background="draft.background"
                            />
                            <figcaption>At the table</figcaption>
                        </figure>
                    </div>
                    <h3>{{ page.props.auth.user.name }}</h3>
                    <p class="preview-title">{{ titleName }}</p>
                    <p class="preview-character">{{ characterName }}</p>
                    <p v-if="draft.character === null" class="preview-note">
                        Example portrait. No preference chooses a random
                        original character when you join.
                    </p>
                    <p v-else class="preview-note">
                        Your saved look appears when you join a room. Active
                        matches keep their current look.
                    </p>
                    <a href="#wardrobe-save" class="preview-save-link"
                        >{{
                            changed ? 'Review & save changes' : 'Save controls'
                        }}
                        <ArrowRight :size="13"
                    /></a>
                </aside>
                <div class="wardrobe-controls">
                    <fieldset
                        v-show="!mirrorOpen || !data.creator"
                        class="wardrobe-fieldset"
                        :disabled="pending"
                    >
                        <legend>
                            Base character
                            <span class="character-count"
                                >{{ availableCharacters }} /
                                {{ characters.length }} available</span
                            >
                        </legend>
                        <p class="wardrobe-help">
                            A familiar face for your next room. You can still
                            change it in the lobby. No preference picks randomly
                            from the original 16 characters.
                        </p>
                        <div class="wardrobe-characters">
                            <label
                                class="wardrobe-character"
                                :class="{
                                    'is-selected': draft.character === null,
                                }"
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
                                v-for="character in originalCharacters"
                                :key="character.id"
                                class="wardrobe-character"
                                :class="{
                                    'is-selected':
                                        draft.character === character.id,
                                }"
                                ><input
                                    v-model="draft.character"
                                    type="radio"
                                    name="character"
                                    :value="character.id"
                                    @change="saved = false"
                                /><CharacterPortrait
                                    :character="character.id"
                                    :accent="draft.accent"
                                    :background="draft.background"
                                    decorative
                                /><span>{{ character.name }}</span></label
                            >
                        </div>
                        <button
                            v-if="data.creator"
                            type="button"
                            class="wardrobe-open-mirror"
                            @click="openMirror"
                        >
                            <CharacterPortrait
                                v-if="draft.creator"
                                character="custom"
                                :creator="draft.creator"
                                decorative
                            /><Sparkles
                                v-else
                                :size="22"
                                aria-hidden="true"
                            /><span
                                ><strong>{{
                                    draft.creator
                                        ? 'Your creation'
                                        : 'Create your own villager'
                                }}</strong
                                ><small>{{
                                    draft.creator
                                        ? 'Wear and edit in the looking glass'
                                        : 'Choose a face, hair, hat, and clothes'
                                }}</small></span
                            ><ArrowRight :size="17" aria-hidden="true" />
                        </button>
                        <template
                            v-for="characterGroup in wardrobeCharacterGroups"
                            :key="characterGroup.id"
                        >
                            <h3 class="wardrobe-unlock-heading">
                                {{ characterGroup.name }}
                            </h3>
                            <p class="wardrobe-help">
                                {{ characterGroup.description }}
                            </p>
                            <div
                                class="wardrobe-characters wardrobe-characters--earned"
                            >
                                <label
                                    v-for="character in characterGroup.characters"
                                    :key="character.id"
                                    class="wardrobe-character"
                                    :class="{
                                        'is-selected':
                                            draft.character === character.id,
                                        'is-locked':
                                            character.unlocked === false,
                                    }"
                                >
                                    <input
                                        v-model="draft.character"
                                        type="radio"
                                        name="character"
                                        :value="character.id"
                                        :disabled="character.unlocked === false"
                                        :aria-label="
                                            character.hidden
                                                ? `? · ${character.role_name}`
                                                : character.name
                                        "
                                        :aria-describedby="`wardrobe-${character.id}-requirement`"
                                        @change="saved = false"
                                    />
                                    <SealedCharacter
                                        v-if="character.hidden"
                                        class="character-portrait"
                                    />
                                    <CharacterPortrait
                                        v-else
                                        :character="character.id"
                                        :accent="draft.accent"
                                        :background="draft.background"
                                        decorative
                                    />
                                    <span class="wardrobe-character-name">{{
                                        character.name
                                    }}</span>
                                    <small
                                        :id="`wardrobe-${character.id}-requirement`"
                                        class="wardrobe-character-requirement"
                                    >
                                        <LockKeyhole
                                            v-if="character.unlocked === false"
                                            :size="12"
                                            aria-hidden="true"
                                        />
                                        <Check
                                            v-else
                                            :size="12"
                                            aria-hidden="true"
                                        />
                                        {{
                                            character.unlocked === false
                                                ? `Locked · ${character.requirement}`
                                                : 'Unlocked · ready to wear'
                                        }}
                                    </small>
                                </label>
                            </div>
                        </template>
                    </fieldset>
                    <fieldset
                        v-for="group in groups"
                        :key="group.field"
                        :disabled="pending"
                        class="wardrobe-fieldset"
                    >
                        <legend>{{ group.name }}</legend>
                        <p
                            v-if="group.field === 'background'"
                            class="wardrobe-help"
                        >
                            A colored mat around your portrait, keeping the
                            original artwork intact.
                        </p>
                        <div
                            class="wardrobe-options"
                            :class="`wardrobe-options--${group.field}`"
                        >
                            <label
                                v-for="item in data.cosmetics[group.catalog]"
                                :key="item.id"
                                class="wardrobe-choice"
                                :class="{
                                    'is-selected':
                                        draft[group.field] === item.id,
                                    'is-locked': !item.unlocked,
                                }"
                                ><input
                                    v-model="draft[group.field]"
                                    type="radio"
                                    :name="group.field"
                                    :value="item.id"
                                    :disabled="!item.unlocked"
                                    :aria-describedby="`cosmetic-${group.field}-${item.id}`"
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
                                ><span
                                    v-else-if="group.field === 'background'"
                                    class="backdrop-swatch"
                                    :style="{
                                        background:
                                            cosmeticBackgrounds[item.id],
                                    }"
                                    aria-hidden="true"
                                    ><i></i></span
                                ><CharacterPortrait
                                    v-else-if="group.field === 'frame'"
                                    class="frame-swatch"
                                    :character="previewCharacter"
                                    :creator="draft.creator"
                                    :frame="item.id"
                                    :accent="draft.accent"
                                    :background="draft.background"
                                    decorative /><span
                                    class="wardrobe-choice-copy"
                                    ><strong>{{ item.name }}</strong
                                    ><small
                                        :id="`cosmetic-${group.field}-${item.id}`"
                                        >{{
                                            item.unlocked
                                                ? draft[group.field] === item.id
                                                    ? 'Selected'
                                                    : 'Unlocked'
                                                : item.requirement
                                        }}</small
                                    ></span
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
                    <p v-if="error" class="wardrobe-error" role="alert">
                        {{ error }}
                    </p>
                    <p
                        v-if="lockedPieces.length"
                        class="wardrobe-error"
                        role="status"
                    >
                        Choose unlocked creator pieces before saving your
                        appearance.
                    </p>
                    <div id="wardrobe-save" class="wardrobe-save">
                        <p role="status">
                            {{
                                saved && !changed
                                    ? 'Your customization is saved.'
                                    : changed
                                      ? 'You have unsaved changes.'
                                      : 'Your equipped details appear beside you in the village.'
                            }}
                        </p>
                        <div class="wardrobe-save-actions">
                            <button
                                type="button"
                                class="wardrobe-revert"
                                :disabled="pending || !changed"
                                @click="revert"
                            >
                                Revert changes
                            </button>
                            <button
                                type="submit"
                                :disabled="
                                    pending ||
                                    !changed ||
                                    lockedPieces.length > 0
                                "
                            >
                                {{ pending ? 'Saving…' : 'Save customization'
                                }}<Check
                                    v-if="saved && !changed"
                                    :size="16"
                                /><ArrowRight v-else :size="16" />
                            </button>
                        </div>
                    </div>
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
            <section
                v-if="data.seasonal_achievements"
                class="seasonal-collection"
                aria-labelledby="seasonal-achievements-title"
            >
                <header class="seasonal-heading">
                    <div>
                        <p class="account-kicker">The season's secrets</p>
                        <h3 id="seasonal-achievements-title">
                            Seasonal achievements
                        </h3>
                    </div>
                    <p class="seasonal-dates">
                        {{ recordDate(data.season.starts_at) }} —
                        {{ recordDate(data.seasonal_achievements.ends_at) }}
                        <span>UTC</span>
                    </p>
                </header>
                <p class="seasonal-description">
                    Three hidden achievements. Your class is your only clue.
                    Characters you unlock are yours to keep; unfinished progress
                    resets with the season.
                </p>
                <ul class="seasonal-secrets">
                    <li
                        v-for="secret in data.seasonal_achievements
                            .achievements"
                        :key="secret.id"
                        :class="{ 'is-revealed': secret.earned_at }"
                    >
                        <CharacterPortrait
                            v-if="secret.earned_at && secret.character"
                            :character="secret.character.id"
                            decorative
                            class="seasonal-portrait"
                        />
                        <SealedCharacter v-else class="seasonal-portrait" />
                        <div class="seasonal-secret-copy">
                            <p class="seasonal-class">{{ secret.role_name }}</p>
                            <h4 v-if="secret.earned_at && secret.character">
                                {{ secret.character.name }}
                            </h4>
                            <span v-else class="sr-only">?</span>
                            <p v-if="secret.earned_at" class="seasonal-earned">
                                <Check :size="12" aria-hidden="true" /> Earned
                                {{ recordDate(secret.earned_at) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
            <h3 v-if="data.seasonal_achievements" class="lifetime-heading">
                Lifetime achievements
            </h3>
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
.character-count {
    display: inline-block;
    margin-left: 8px;
    color: var(--account-muted);
    font:
        11px 'DM Sans',
        sans-serif;
}
.wardrobe-unlock-heading {
    margin: 24px 0 12px;
    padding-top: 20px;
    border-top: 1px solid var(--account-line);
    font-size: 20px;
}
.wardrobe-characters.wardrobe-characters--earned {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.wardrobe-characters--earned .wardrobe-character {
    padding: 17px 12px 14px;
    gap: 10px;
    min-width: 0;
    text-align: center;
}
.wardrobe-characters--earned .character-portrait {
    width: 64px;
    height: 74px;
    border-radius: 36px 36px 2px 2px;
}
.wardrobe-character.is-locked {
    cursor: not-allowed;
}
.wardrobe-character.is-locked .character-portrait {
    filter: saturate(0.7);
}
.wardrobe-character:focus-within {
    outline: 2px solid var(--account-green);
    outline-offset: 2px;
}
.wardrobe-character-name {
    font-size: 13px;
    line-height: 1.4;
}
.wardrobe-character-requirement {
    color: var(--account-muted);
    font-size: 11px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}
.wardrobe-character-requirement svg {
    display: inline;
    vertical-align: -1px;
    margin-right: 3px;
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

.wardrobe-panel {
    display: block;
}
.wardrobe-panel > .reputation-section-intro {
    max-width: 650px;
    margin-bottom: 30px;
}
.wardrobe-form {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    gap: 38px;
    align-items: start;
}
.wardrobe-controls {
    min-width: 0;
}
.wardrobe-preview {
    position: sticky;
    top: 24px;
    background: #15272b;
    border: 1px solid #61736b;
    padding: 20px;
    color: #f1ecdb;
    box-shadow: 4px 4px 0 var(--account-deep);
    text-align: center;
}
.preview-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    text-align: left;
    font-size: 8px;
    letter-spacing: 1px;
    color: #c2cdbb;
}
.preview-status {
    color: #b8cfaa;
    font-size: 9px;
    letter-spacing: 0;
}
.preview-status.is-unsaved {
    color: #e0ba82;
}
.preview-portraits {
    display: flex;
    align-items: end;
    justify-content: center;
    gap: 26px;
    margin: 28px 0 21px;
}
.preview-portraits figure {
    margin: 0;
}
.preview-full .character-portrait {
    width: 116px;
    height: 132px;
    border-radius: 65px 65px 3px 3px;
}
.preview-table .character-portrait {
    width: 61px;
    height: 61px;
    border-radius: 20px 20px 7px 7px;
    border-width: 2px;
}
.preview-portraits figcaption {
    margin-top: 16px;
    font-size: 9px;
    color: #bac8be;
}
.wardrobe-preview h3 {
    font-size: 25px;
    overflow-wrap: anywhere;
}
.preview-title {
    margin: 7px 0;
    color: var(--identity-accent);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-size: 10px;
}
.preview-character {
    color: #c7d1c6;
    font-size: 11px;
}
.preview-note {
    font-size: 10px;
    color: #b8c5bc;
    line-height: 1.7;
    margin-top: 18px;
    padding-top: 15px;
    border-top: 1px solid #51635a;
}
.preview-save-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    color: #d1dcbc;
    font-size: 11px;
    margin-top: 16px;
    min-height: 30px;
}
.wardrobe-choice:focus-within {
    outline: 2px solid var(--account-green);
    outline-offset: 2px;
}
.wardrobe-choice:not(.is-locked):hover {
    border-color: var(--account-green);
}
.wardrobe-choice:has(input:disabled) {
    cursor: not-allowed;
}
.backdrop-swatch {
    display: grid;
    place-items: center;
    width: 30px;
    height: 35px;
    border-radius: 16px 16px 2px 2px;
    flex-shrink: 0;
    border: 1px solid #778174;
}
.backdrop-swatch i {
    width: 62%;
    height: 71%;
    border-radius: inherit;
    background: #1d3238;
    border: 1px solid #d9dfc14d;
}
.frame-swatch.character-portrait {
    width: 28px;
    height: 32px;
    margin: 7px 8px;
    border-radius: 16px 16px 2px 2px;
}
.wardrobe-save {
    flex-wrap: wrap;
    scroll-margin-top: 24px;
}
.wardrobe-save-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.wardrobe-save .wardrobe-revert {
    background: transparent;
    border: 1px solid var(--account-line);
    color: var(--account-text);
}
.wardrobe-save button:not(:disabled):hover {
    filter: brightness(1.12);
}
.wardrobe-save button:focus-visible,
.preview-save-link:focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 3px;
}
@media (max-width: 1100px) {
    .wardrobe-form {
        grid-template-columns: 245px minmax(0, 1fr);
        gap: 26px;
    }
    .wardrobe-preview {
        padding: 16px;
    }
    .preview-portraits {
        gap: 20px;
    }
    .preview-full .character-portrait {
        width: 100px;
        height: 116px;
    }
}
@media (max-width: 850px) {
    .wardrobe-form {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
    }
    .wardrobe-preview {
        z-index: 3;
        top: 8px;
        display: grid;
        grid-template-columns: 140px minmax(0, 1fr);
        gap: 3px 16px;
        align-items: center;
        text-align: left;
        padding: 12px 16px;
    }
    .preview-heading {
        grid-column: 1 / -1;
        margin-bottom: 7px;
    }
    .preview-portraits {
        grid-column: 1;
        grid-row: 2 / 6;
        gap: 18px;
        margin: 6px 0;
        align-items: center;
    }
    .preview-full .character-portrait {
        width: 64px;
        height: 74px;
        border-radius: 35px 35px 3px 3px;
    }
    .preview-table .character-portrait {
        width: 49px;
        height: 49px;
        border-radius: 18px 18px 5px 5px;
    }
    .preview-portraits figcaption {
        margin-top: 10px;
        font-size: 8px;
    }
    .wardrobe-preview h3 {
        font-size: 19px;
        grid-column: 2;
        grid-row: 2;
    }
    .preview-title,
    .preview-character {
        margin: 0;
        grid-column: 2;
        font-size: 9px;
    }
    .preview-save-link {
        grid-column: 2;
        grid-row: 5;
        justify-content: start;
        font-size: 9px;
        min-height: 24px;
        margin: 0;
    }
    .preview-note {
        grid-column: 1 / -1;
        grid-row: 6;
        font-size: 9px;
        padding-top: 8px;
        margin-top: 5px;
    }
    .wardrobe-save {
        scroll-margin-top: 230px;
    }
    .wardrobe-controls input,
    .wardrobe-controls button,
    .wardrobe-choice,
    .wardrobe-character {
        scroll-margin-top: 230px;
        scroll-margin-bottom: 24px;
    }
}
@media (max-width: 400px) {
    .wardrobe-preview {
        grid-template-columns: 121px minmax(0, 1fr);
        padding: 10px;
        gap: 3px 12px;
    }
    .preview-portraits {
        gap: 12px;
    }
    .preview-full .character-portrait {
        width: 58px;
        height: 68px;
    }
    .preview-table .character-portrait {
        width: 43px;
        height: 43px;
    }
    .wardrobe-choice {
        gap: 7px;
        padding: 11px 8px;
    }
    .wardrobe-options--frame,
    .wardrobe-options--background {
        grid-template-columns: minmax(0, 1fr);
    }
}
.wardrobe-mode {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}
.wardrobe-mode button {
    position: relative;
    text-align: left;
    border: 1px solid var(--account-line);
    padding: 16px 20px;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 20px;
}
.wardrobe-mode button span {
    display: block;
    font-family: 'DM Sans', sans-serif;
    color: var(--account-muted);
    font-size: 10px;
    margin-top: 6px;
}
.wardrobe-mode button svg {
    position: absolute;
    right: 18px;
    top: 20px;
    color: var(--account-brass);
}
.wardrobe-mode button[aria-pressed='true'] {
    border-color: var(--account-green);
    background: color-mix(in srgb, var(--account-green) 9%, transparent);
}
.wardrobe-mode button:focus-visible,
.wardrobe-open-mirror:focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 3px;
}
.wardrobe-open-mirror {
    display: flex;
    align-items: center;
    gap: 15px;
    text-align: left;
    border: 1px solid var(--account-line);
    padding: 15px;
    margin-top: 15px;
    width: 100%;
}
.wardrobe-open-mirror > span {
    flex: 1;
}
.wardrobe-open-mirror strong,
.wardrobe-open-mirror small {
    display: block;
    font-weight: 400;
}
.wardrobe-open-mirror strong {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 17px;
}
.wardrobe-open-mirror small {
    font-size: 10px;
    color: var(--account-muted);
    margin-top: 4px;
}
.wardrobe-open-mirror .character-portrait {
    width: 42px;
    height: 49px;
    border-radius: 24px 24px 3px 3px;
    flex: none;
}
.wardrobe-form--mirror {
    display: block;
}
.wardrobe-form--mirror .wardrobe-controls {
    margin-top: 30px;
}
@media (min-width: 851px) {
    .wardrobe-form--mirror .wardrobe-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 28px;
    }
    .wardrobe-form--mirror .wardrobe-save,
    .wardrobe-form--mirror .wardrobe-error {
        grid-column: 1 / -1;
    }
}
@media (max-width: 700px) {
    .wardrobe-mode {
        gap: 8px;
    }
    .wardrobe-mode button {
        padding: 13px 12px;
        font-size: 17px;
    }
    .wardrobe-mode button span {
        font-size: 9px;
        line-height: 1.5;
    }
    .wardrobe-mode button svg {
        display: none;
    }
}
</style>
<style scoped>
.seasonal-collection {
    margin: 28px 0 36px;
    padding: 26px;
    border: 1px solid #ad94624d;
    border-top: 3px solid var(--account-brass);
    background: linear-gradient(120deg, #ac915708, transparent 65%), #182d2b;
}
.seasonal-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
}
.seasonal-heading h3 {
    font-size: 28px;
    margin-top: 6px;
}
.seasonal-dates {
    color: #d3c7a8;
    font-size: 11px;
    white-space: nowrap;
}
.seasonal-dates span {
    color: var(--account-muted);
}
.seasonal-description {
    color: var(--account-muted);
    font-size: 12px;
    line-height: 1.8;
    max-width: 660px;
    margin: 14px 0 24px;
}
.seasonal-secrets {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    list-style: none;
    padding: 0;
    gap: 20px;
}
.seasonal-secrets li {
    min-width: 0;
}
.seasonal-secrets .seasonal-portrait {
    display: grid;
    width: 100%;
    height: auto;
    aspect-ratio: 1;
    border-radius: 2px;
    font-size: 36px;
}
.seasonal-secret-copy {
    padding: 15px 0 0;
}
.seasonal-class {
    font-size: 12px;
    color: #d3c7a8;
    letter-spacing: 0.06em;
}
.seasonal-secret-copy h4 {
    margin-top: 7px;
    font-size: 23px;
}
.seasonal-earned {
    color: var(--account-green);
    font-size: 10px;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.lifetime-heading {
    font-size: 24px;
    margin-bottom: 8px;
}
@media (max-width: 650px) {
    .seasonal-collection {
        padding: 20px 16px;
    }
    .seasonal-heading {
        display: block;
    }
    .seasonal-heading h3 {
        font-size: 25px;
    }
    .seasonal-dates {
        margin-top: 12px;
        font-size: 10px;
        white-space: normal;
    }
    .seasonal-secrets {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .seasonal-secrets li {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        border-top: 1px solid #ad946233;
        padding-top: 16px;
    }
    .seasonal-secrets li:first-child {
        border: 0;
        padding-top: 0;
    }
    .seasonal-secrets .seasonal-portrait {
        font-size: 23px;
    }
    .seasonal-secret-copy {
        padding: 0;
    }
    .seasonal-secret-copy h4 {
        font-size: 20px;
    }
}
</style>
