<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    KeyRound,
    LoaderCircle,
    Plus,
} from '@lucide/vue';
import {
    defaultCharacters,
    RoomError,
    roomRequest,
    type Character,
} from '@/lib/chanting';
import CharacterPicker from './CharacterPicker.vue';
import CharacterPortrait from './CharacterPortrait.vue';
import ModeSelector from './ModeSelector.vue';
import {
    customModeError,
    defaultModeSetup,
    modeName,
    modeSubmission,
} from '@/lib/gameModes';

const props = withDefaults(
    defineProps<{
        initialCode?: string;
        characters?: Character[];
        preferredCharacter?: string | null;
        initialName?: string;
        compactCharacters?: boolean;
    }>(),
    { characters: () => defaultCharacters },
);
const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);
const character = ref(
    props.preferredCharacter || props.characters[0]?.id || 'mariner',
);
const mode = ref(props.initialCode ? 'join' : 'create');
const createTab = ref<'details' | 'modes'>('details');
const setup = ref(defaultModeSetup());
const setupError = computed(() => customModeError(setup.value));
function switchCreateTab(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    createTab.value =
        event.key === 'Home'
            ? 'details'
            : event.key === 'End'
              ? 'modes'
              : createTab.value === 'details'
                ? 'modes'
                : 'details';
    document.getElementById(`create-tab-${createTab.value}`)?.focus();
}
const name = ref((props.initialName ?? '').slice(0, 24));
const selectedCharacter = computed(() =>
    props.characters.find((item) => item.id === character.value),
);
const code = ref(props.initialCode ?? '');
const pending = ref(false);
const error = ref('');
async function enter() {
    if (mode.value === 'create' && setupError.value) {
        error.value = setupError.value;
        createTab.value = 'modes';
        return;
    }
    pending.value = true;
    error.value = '';
    try {
        const data = await roomRequest<{ code: string }>(
            mode.value === 'create' ? '/rooms' : '/rooms/join',
            {
                name: name.value.trim(),
                ...(signedIn.value ? { character: character.value } : {}),
                ...(mode.value === 'join'
                    ? { code: code.value.trim().toUpperCase() }
                    : { setup: modeSubmission(setup.value) }),
            },
        );
        window.location.assign(`/rooms/${encodeURIComponent(data.code)}`);
    } catch (cause) {
        error.value =
            mode.value === 'join' &&
            cause instanceof RoomError &&
            cause.status === 404
                ? 'No room found with that code. Check the code and try again.'
                : cause instanceof Error
                  ? cause.message
                  : 'Something went wrong. Try again.';
        pending.value = false;
    }
}
</script>

<template>
    <section class="entry-panel" aria-label="Enter the village">
        <div
            v-if="!initialCode"
            class="entry-tabs"
            role="group"
            aria-label="Room action"
        >
            <button
                type="button"
                :disabled="pending"
                :class="{ selected: mode === 'create' }"
                :aria-pressed="mode === 'create'"
                @click="
                    mode = 'create';
                    error = '';
                "
            >
                <Plus :size="16" /> Create a room
            </button>
            <button
                type="button"
                :disabled="pending"
                :class="{ selected: mode === 'join' }"
                :aria-pressed="mode === 'join'"
                @click="
                    mode = 'join';
                    error = '';
                "
            >
                <KeyRound :size="16" /> Join friends
            </button>
        </div>
        <form class="entry-form" @submit.prevent="enter">
            <label for="player-name">What should the village call you?</label>
            <input
                id="player-name"
                v-model="name"
                required
                maxlength="24"
                autocomplete="nickname"
                placeholder="Your suspiciously innocent name"
                :disabled="pending"
            />
            <template v-if="mode === 'join'">
                <label for="room-code">Secret room code</label>
                <input
                    id="room-code"
                    v-model="code"
                    required
                    maxlength="8"
                    autocapitalize="characters"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="e.g. MIST42"
                    class="code-input"
                    :disabled="pending || !!initialCode"
                />
            </template>
            <div
                v-if="mode === 'create'"
                class="entry-tabs create-tabs"
                role="tablist"
                aria-label="Create room settings"
                @keydown="switchCreateTab"
            >
                <button
                    id="create-tab-details"
                    type="button"
                    role="tab"
                    aria-controls="create-panel-details"
                    :aria-selected="createTab === 'details'"
                    :tabindex="createTab === 'details' ? 0 : -1"
                    :class="{ selected: createTab === 'details' }"
                    :disabled="pending"
                    @click="createTab = 'details'"
                >
                    Room details
                </button>
                <button
                    id="create-tab-modes"
                    type="button"
                    role="tab"
                    aria-controls="create-panel-modes"
                    :aria-selected="createTab === 'modes'"
                    :tabindex="createTab === 'modes' ? 0 : -1"
                    :class="{ selected: createTab === 'modes' }"
                    :disabled="pending"
                    @click="createTab = 'modes'"
                >
                    Modes
                </button>
            </div>
            <div
                v-if="mode === 'create'"
                v-show="createTab === 'modes'"
                id="create-panel-modes"
                role="tabpanel"
                aria-labelledby="create-tab-modes"
            >
                <ModeSelector v-model="setup" :disabled="pending" />
            </div>
            <div
                v-show="mode === 'join' || createTab === 'details'"
                id="create-panel-details"
                :role="mode === 'create' ? 'tabpanel' : undefined"
                :aria-labelledby="
                    mode === 'create' ? 'create-tab-details' : undefined
                "
            >
                <details
                    v-if="signedIn && compactCharacters"
                    class="entry-character-disclosure"
                >
                    <summary
                        :aria-disabled="pending"
                        @click="pending && $event.preventDefault()"
                        @keydown="
                            pending &&
                            ['Enter', ' '].includes($event.key) &&
                            $event.preventDefault()
                        "
                    >
                        <CharacterPortrait
                            :character="character"
                            :creator="selectedCharacter?.creator"
                            decorative
                        /><span
                            ><small>Your character</small
                            ><strong>{{
                                selectedCharacter?.name ?? 'Choose a villager'
                            }}</strong></span
                        ><span class="entry-character-change">
                            <span class="entry-character-expand">Change</span>
                            <span class="entry-character-collapse">Close</span>
                            <ChevronRight :size="18" aria-hidden="true" />
                        </span>
                    </summary>
                    <CharacterPicker
                        v-model="character"
                        :characters="characters"
                        :disabled="pending"
                    />
                </details>
                <CharacterPicker
                    v-else-if="signedIn"
                    v-model="character"
                    :characters="characters"
                    :disabled="pending"
                />
                <p v-else class="guest-character-note">
                    The village will choose a random character for you.
                    <a href="/login">Sign in</a> or
                    <a href="/register">create an account</a> to pick your own.
                    Looks never reveal your role.
                </p>
                <p v-if="mode === 'create'" class="entry-mode-summary">
                    Mode: <strong>{{ modeName(setup) }}</strong
                    ><button
                        type="button"
                        class="quiet-link"
                        :disabled="pending"
                        @click="createTab = 'modes'"
                    >
                        Change mode
                    </button>
                </p>
            </div>
            <p v-if="error" class="form-error" role="alert">{{ error }}</p>
            <button
                class="button primary entry-submit"
                :disabled="
                    pending ||
                    !name.trim() ||
                    (mode === 'join' && !code.trim()) ||
                    (mode === 'create' && !!setupError)
                "
            >
                <template v-if="pending"
                    ><span role="status">Entering the village…</span
                    ><LoaderCircle :size="18" class="spin" /></template
                ><template v-else
                    >{{
                        mode === 'create'
                            ? 'Gather your suspects'
                            : 'Enter the village'
                    }}<ArrowRight :size="18"
                /></template>
            </button>
            <p class="entry-note">
                {{
                    signedIn
                        ? 'Private rooms. Familiar faces. Unfamiliar motives.'
                        : 'Play as a guest. Bring friends. Keep secrets.'
                }}
            </p>
        </form>
    </section>
</template>

<style scoped>
.create-tabs {
    margin-top: 4px;
}
.entry-mode-summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding-top: 16px;
    color: var(--muted);
    font-size: 12px;
}
.entry-mode-summary strong {
    color: var(--cream);
}
.entry-mode-summary button {
    margin-left: auto;
}
</style>
