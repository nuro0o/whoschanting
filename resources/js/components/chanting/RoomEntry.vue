<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronDown,
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
const name = ref((props.initialName ?? '').slice(0, 24));
const selectedCharacter = computed(() =>
    props.characters.find((item) => item.id === character.value),
);
const code = ref(props.initialCode ?? '');
const pending = ref(false);
const error = ref('');
async function enter() {
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
                    : {}),
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
            <details
                v-if="signedIn && compactCharacters"
                class="entry-character-disclosure"
            >
                <summary>
                    <CharacterPortrait :character="character" decorative /><span
                        ><small>Your character</small
                        ><strong>{{
                            selectedCharacter?.name ?? 'Choose a villager'
                        }}</strong></span
                    ><span class="entry-character-change"
                        >Change <ChevronDown :size="14"
                    /></span>
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
            <p v-if="error" class="form-error" role="alert">{{ error }}</p>
            <button
                class="button primary entry-submit"
                :disabled="
                    pending || !name.trim() || (mode === 'join' && !code.trim())
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
