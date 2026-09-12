<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    KeyRound,
    LoaderCircle,
    Plus,
    SlidersHorizontal,
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
import EntryEditorPanel from './EntryEditorPanel.vue';
import SealedCharacter from './SealedCharacter.vue';
import {
    customModeError,
    defaultModeSetup,
    modeName,
    modeSubmission,
    rosterTotals,
} from '@/lib/gameModes';

const props = withDefaults(
    defineProps<{
        initialCode?: string;
        pinRequired?: boolean;
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
const entryElement = ref<HTMLElement | null>(null);
const editorTrigger = ref<HTMLElement | null>(null);
const editor = ref<'characters' | 'modes' | null>(null);
const editorOpen = computed({
    get: () => editor.value !== null,
    set: (value: boolean) => {
        if (!value) editor.value = null;
    },
});
function openEditor(value: 'characters' | 'modes', event: MouseEvent) {
    editorTrigger.value = event.currentTarget as HTMLElement;
    editor.value = editor.value === value ? null : value;
}
const setup = ref(defaultModeSetup());
const setupError = computed(() => customModeError(setup.value));
const modeDetail = computed(() =>
    setup.value.mode === 'custom'
        ? `${rosterTotals(setup.value.roles).total} players · Your cast, your rules`
        : 'Choose the rules for your village',
);
const name = ref((props.initialName ?? '').slice(0, 24));
const selectedCharacter = computed(() =>
    props.characters.find((item) => item.id === character.value),
);
const selectedCharacterName = computed(() =>
    selectedCharacter.value?.hidden
        ? `? · ${selectedCharacter.value.role_name ?? 'Sealed character'}`
        : (selectedCharacter.value?.name ?? 'Choose a villager'),
);
const code = ref(props.initialCode ?? '');
const createPin = ref('');
const joinPin = ref('');
const pin = computed({
    get: () => (mode.value === 'create' ? createPin.value : joinPin.value),
    set: (value: string) => {
        if (mode.value === 'create') createPin.value = value;
        else joinPin.value = value;
    },
});
const pending = ref(false);
const error = ref('');
async function enter() {
    if (mode.value === 'create' && setupError.value) {
        error.value = setupError.value;
        editor.value = 'modes';
        return;
    }
    pending.value = true;
    error.value = '';
    try {
        const data = await roomRequest<{ code: string }>(
            mode.value === 'create' ? '/rooms' : '/rooms/join',
            {
                name: name.value.trim(),
                pin: pin.value || null,
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
    <section
        ref="entryElement"
        class="entry-panel"
        aria-label="Enter the village"
    >
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
                    editor = null;
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
            <label for="lobby-pin">{{
                mode === 'create'
                    ? 'Lobby PIN (optional)'
                    : pinRequired
                      ? 'Lobby PIN'
                      : 'Lobby PIN (if required)'
            }}</label>
            <input
                id="lobby-pin"
                v-model="pin"
                type="password"
                inputmode="numeric"
                pattern="[0-9]{4,8}"
                minlength="4"
                maxlength="8"
                :autocomplete="
                    mode === 'create' ? 'new-password' : 'current-password'
                "
                :placeholder="
                    mode === 'create'
                        ? 'Leave blank for no PIN'
                        : 'Ask your host for the PIN'
                "
                aria-describedby="lobby-pin-help"
                :disabled="pending"
            />
            <p id="lobby-pin-help" class="pin-help">
                {{
                    mode === 'create'
                        ? 'Use 4–8 digits and share them with your friends. You can change or remove the PIN in the lobby.'
                        : 'Protected lobbies need a 4–8 digit PIN, even when joining through an invite link.'
                }}
            </p>
            <div class="entry-settings">
                <button
                    v-if="signedIn"
                    type="button"
                    class="entry-setting"
                    data-entry-editor-trigger
                    aria-haspopup="dialog"
                    :aria-expanded="editor === 'characters'"
                    :disabled="pending"
                    @click="openEditor('characters', $event)"
                >
                    <SealedCharacter
                        v-if="selectedCharacter?.hidden"
                        class="character-portrait"
                    />
                    <CharacterPortrait
                        v-else
                        :character="character"
                        :creator="selectedCharacter?.creator"
                        decorative
                    />
                    <span class="entry-setting-copy"
                        ><small>Your character</small
                        ><strong>{{ selectedCharacterName }}</strong></span
                    >
                    <ChevronRight
                        :size="19"
                        class="entry-setting-arrow"
                        aria-hidden="true"
                    />
                </button>
                <p v-else class="guest-character-note">
                    The village will choose a random character for you.
                    <a href="/login">Sign in</a> or
                    <a href="/register">create an account</a> to pick your own.
                    Looks never reveal your role.
                </p>
                <button
                    v-if="mode === 'create'"
                    type="button"
                    class="entry-setting"
                    data-entry-editor-trigger
                    aria-haspopup="dialog"
                    :aria-expanded="editor === 'modes'"
                    :disabled="pending"
                    @click="openEditor('modes', $event)"
                >
                    <span class="entry-setting-icon"
                        ><SlidersHorizontal :size="21" aria-hidden="true"
                    /></span>
                    <span class="entry-setting-copy"
                        ><small>Game mode</small
                        ><strong>{{ modeName(setup) }}</strong
                        ><span>{{ modeDetail }}</span></span
                    >
                    <ChevronRight
                        :size="19"
                        class="entry-setting-arrow"
                        aria-hidden="true"
                    />
                </button>
            </div>
            <p
                v-if="mode === 'create' && setupError"
                class="form-error"
                role="status"
            >
                {{ setupError }} Open Game mode to adjust your cast.
            </p>
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
        <EntryEditorPanel
            v-model="editorOpen"
            :anchor="entryElement"
            :trigger="editorTrigger"
            :title="
                editor === 'characters'
                    ? 'Choose your character'
                    : 'Choose your game mode'
            "
            :description="
                editor === 'characters'
                    ? 'A familiar face. An unfamiliar alibi.'
                    : 'Set the mood for a suspicious evening.'
            "
            :summary="
                editor === 'characters'
                    ? selectedCharacterName
                    : modeName(setup)
            "
            :error="editor === 'modes' ? setupError : null"
        >
            <CharacterPicker
                v-if="editor === 'characters'"
                v-model="character"
                :characters="characters"
                :disabled="pending"
                searchable
            />
            <ModeSelector v-else v-model="setup" :disabled="pending" />
        </EntryEditorPanel>
    </section>
</template>

<style scoped>
.pin-help {
    margin: 0 0 8px;
    color: var(--account-muted, var(--muted));
    font-size: 13px;
    line-height: 1.5;
}
.entry-settings {
    margin: 6px 0 2px;
    border-block: 1px solid var(--account-line, var(--line));
}
.entry-setting {
    display: flex;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 13px 0;
    border: 0;
    background: transparent;
    color: var(--account-text, var(--cream));
    text-align: left;
}
.entry-setting + .entry-setting {
    border-top: 1px solid var(--account-line, var(--line));
}
.entry-setting > .character-portrait,
.entry-setting-icon {
    width: 46px;
    flex-shrink: 0;
}
.entry-setting-icon {
    display: grid;
    place-items: center;
    height: 46px;
    border: 1px solid var(--account-line, var(--line));
    border-radius: 4px;
    color: var(--account-green, var(--green));
    background: #bdcd9c08;
}
.entry-setting-copy {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.entry-setting-copy small {
    color: var(--account-muted, var(--muted));
    font-size: 10px;
}
.entry-setting-copy strong {
    font-size: 14px;
    font-weight: 500;
    overflow-wrap: anywhere;
}
.entry-setting-copy > span {
    color: var(--account-muted, var(--muted));
    font-size: 10px;
}
.entry-setting-arrow {
    margin-left: auto;
    flex-shrink: 0;
    color: var(--account-green, var(--green));
    transition: transform 0.15s;
}
.entry-setting:hover .entry-setting-arrow,
.entry-setting[aria-expanded='true'] .entry-setting-arrow {
    transform: translateX(3px);
}
.entry-setting[aria-expanded='true'] strong {
    color: var(--account-green, var(--green));
}
@media (prefers-reduced-motion: reduce) {
    .entry-setting-arrow {
        transition: none;
    }
}
</style>
