<script setup lang="ts">
import { t } from '@/i18n';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    Globe,
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
        ? t('roomEntry.mode.custom_description', {
              count: rosterTotals(setup.value.roles).total,
          })
        : t('roomEntry.mode.description'),
);
const name = ref((props.initialName ?? '').slice(0, 24));
const selectedCharacter = computed(() =>
    props.characters.find((item) => item.id === character.value),
);
const selectedCharacterName = computed(() =>
    selectedCharacter.value?.hidden
        ? t('roomEntry.character.hidden', {
              role:
                  selectedCharacter.value.role_name ??
                  t('roomEntry.character.sealed'),
          })
        : (selectedCharacter.value?.name ?? t('roomEntry.character.choose')),
);
const code = ref(props.initialCode ?? '');
const createPin = ref('');
const visibility = ref<'private' | 'public'>('private');
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
                    : {
                          setup: modeSubmission(setup.value),
                          visibility: visibility.value,
                      }),
            },
        );
        window.location.assign(`/rooms/${encodeURIComponent(data.code)}`);
    } catch (cause) {
        error.value =
            mode.value === 'join' &&
            cause instanceof RoomError &&
            cause.status === 404
                ? t('roomEntry.errors.room_not_found')
                : cause instanceof Error
                  ? cause.message
                  : t('roomEntry.errors.unexpected');
        pending.value = false;
    }
}
</script>

<template>
    <section
        ref="entryElement"
        class="entry-panel"
        :aria-label="t('roomEntry.label')"
    >
        <div
            v-if="!initialCode"
            class="entry-tabs"
            role="group"
            :aria-label="t('roomEntry.tabs.label')"
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
                <Plus :size="16" /> {{ t('roomEntry.tabs.create') }}
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
                <KeyRound :size="16" /> {{ t('roomEntry.tabs.join') }}
            </button>
        </div>
        <a v-if="!initialCode" href="/rooms" class="entry-browser-link">
            <Globe :size="16" aria-hidden="true" />
            {{ t('roomEntry.room_browser') }}
            <ArrowRight :size="15" aria-hidden="true" />
        </a>
        <form class="entry-form" @submit.prevent="enter">
            <label for="player-name">{{ t('roomEntry.name.label') }}</label>
            <input
                id="player-name"
                v-model="name"
                required
                maxlength="24"
                autocomplete="nickname"
                :placeholder="t('roomEntry.name.placeholder')"
                :disabled="pending"
            />
            <template v-if="mode === 'join'">
                <label for="room-code">{{ t('roomEntry.code.label') }}</label>
                <input
                    id="room-code"
                    v-model="code"
                    required
                    maxlength="8"
                    autocapitalize="characters"
                    autocomplete="off"
                    spellcheck="false"
                    :placeholder="t('roomEntry.code.placeholder')"
                    class="code-input"
                    :disabled="pending || !!initialCode"
                />
            </template>
            <fieldset
                v-if="mode === 'create'"
                class="room-visibility"
                :disabled="pending"
                aria-describedby="room-visibility-help"
            >
                <legend>{{ t('roomEntry.visibility.label') }}</legend>
                <div class="visibility-options">
                    <label :class="{ selected: visibility === 'private' }">
                        <input
                            v-model="visibility"
                            type="radio"
                            name="visibility"
                            value="private"
                        />
                        <span
                            ><strong>{{
                                t('roomEntry.visibility.private')
                            }}</strong
                            ><small>{{
                                t('roomEntry.visibility.private_hint')
                            }}</small></span
                        >
                    </label>
                    <label :class="{ selected: visibility === 'public' }">
                        <input
                            v-model="visibility"
                            type="radio"
                            name="visibility"
                            value="public"
                        />
                        <span
                            ><strong>{{
                                t('roomEntry.visibility.public')
                            }}</strong
                            ><small>{{
                                t('roomEntry.visibility.public_hint')
                            }}</small></span
                        >
                    </label>
                </div>
                <p id="room-visibility-help" class="pin-help">
                    {{
                        visibility === 'public'
                            ? t('roomEntry.visibility.public_help')
                            : t('roomEntry.visibility.private_help')
                    }}
                </p>
            </fieldset>
            <label for="lobby-pin">{{
                mode === 'create'
                    ? t('roomEntry.pin.optional')
                    : pinRequired
                      ? t('roomEntry.pin.required')
                      : t('roomEntry.pin.if_required')
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
                        ? t('roomEntry.pin.create_placeholder')
                        : t('roomEntry.pin.join_placeholder')
                "
                aria-describedby="lobby-pin-help"
                :disabled="pending"
            />
            <p id="lobby-pin-help" class="pin-help">
                {{
                    mode === 'create'
                        ? t('roomEntry.pin.create_help')
                        : t('roomEntry.pin.join_help')
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
                        ><small>{{ t('roomEntry.character.label') }}</small
                        ><strong>{{ selectedCharacterName }}</strong></span
                    >
                    <ChevronRight
                        :size="19"
                        class="entry-setting-arrow"
                        aria-hidden="true"
                    />
                </button>
                <p v-else class="guest-character-note">
                    {{ t('roomEntry.guest.description') }}
                    <a href="/login">{{ t('roomEntry.guest.sign_in') }}</a>
                    {{ t('roomEntry.guest.or') }}
                    <a href="/register">{{ t('roomEntry.guest.register') }}</a>
                    {{ t('roomEntry.guest.pick_own') }}
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
                        ><small>{{ t('roomEntry.mode.label') }}</small
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
                {{
                    t('roomEntry.mode.adjust_error', { setupError: setupError })
                }}
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
                    ><span role="status">{{
                        t('roomEntry.submit.pending')
                    }}</span
                    ><LoaderCircle :size="18" class="spin" /></template
                ><template v-else
                    >{{
                        mode === 'create'
                            ? t('roomEntry.submit.create')
                            : t('roomEntry.label')
                    }}<ArrowRight :size="18"
                /></template>
            </button>
            <p class="entry-note">
                {{
                    signedIn
                        ? t('roomEntry.note.signed_in')
                        : t('roomEntry.note.guest')
                }}
            </p>
        </form>
        <EntryEditorPanel
            v-model="editorOpen"
            :anchor="entryElement"
            :trigger="editorTrigger"
            :title="
                editor === 'characters'
                    ? t('roomEntry.editor.character_title')
                    : t('roomEntry.editor.mode_title')
            "
            :description="
                editor === 'characters'
                    ? t('roomEntry.editor.character_description')
                    : t('roomEntry.editor.mode_description')
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
.entry-browser-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    color: var(--account-green, var(--green));
    font-size: 13px;
    text-underline-offset: 4px;
}
.room-visibility {
    min-width: 0;
    margin: 4px 0 0;
    padding: 0;
    border: 0;
}
.room-visibility legend {
    margin-bottom: 8px;
    font-size: 13px;
}
.visibility-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 8px;
}
.entry-form .visibility-options label {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    padding: 11px 10px;
    border: 1px solid var(--account-line, var(--line));
    border-radius: 4px;
    cursor: pointer;
}
.entry-form .visibility-options label.selected {
    border-color: var(--account-green, var(--green));
    background: #bdcd9c0b;
}
.entry-form .visibility-options input {
    width: 16px;
    height: 16px;
    padding: 0;
    margin: 0;
    flex-shrink: 0;
    accent-color: var(--account-green, var(--green));
}
.visibility-options span {
    display: grid;
    gap: 2px;
}
.visibility-options strong {
    color: var(--account-text, var(--cream));
    font-size: 13px;
    font-weight: 500;
}
.visibility-options small {
    color: var(--account-muted, var(--muted));
    font-size: 11px;
}
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
