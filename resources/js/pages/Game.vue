<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Check,
    Circle,
    Clock3,
    Copy,
    Eye,
    LoaderCircle,
    Moon,
    Send,
    ShieldCheck,
    Vote,
} from '@lucide/vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import SecretRole from '@/components/chanting/SecretRole.vue';
import RoomBriefing from '@/components/chanting/RoomBriefing.vue';
import CharacterPicker from '@/components/chanting/CharacterPicker.vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import GameGlossary from '@/components/chanting/GameGlossary.vue';
import CursePanel from '@/components/chanting/CursePanel.vue';
import MatchRecap from '@/components/chanting/MatchRecap.vue';
import CardTable from '@/components/chanting/CardTable.vue';
import SoundControl from '@/components/chanting/SoundControl.vue';
import {
    csrfToken,
    RoomError,
    roomRequest,
    roles,
    type RoomState,
    type Character,
    defaultCharacters,
} from '@/lib/chanting';
import '../../css/chanting.css';

const props = withDefaults(
    defineProps<{
        code: string;
        characters?: Character[];
        preferredCharacter?: string | null;
    }>(),
    { characters: () => defaultCharacters },
);
const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);
const state = ref<RoomState>();
const loading = ref(true);
const outsider = ref(false);
const error = ref('');
const curseError = ref('');
const pending = ref(false);
const disconnected = ref(false);
const live = ref(false);
const target = ref<string | null>(null);
const actionSerial = ref(0);
const revealed = ref(false);
const message = ref('');
const copied = ref(false);
const chatList = ref<HTMLElement>();
const now = ref(Date.now());
let offset = 0;
let polling: ReturnType<typeof setInterval> | undefined;
let clock: ReturnType<typeof setInterval> | undefined;
let fetching = false;
let disposed = false;
let echo: Echo<'reverb'> | undefined;

const phaseInfo = {
    lobby: [
        'The usual suspects.',
        'Gather your friends. There’s something strange in the sea tonight.',
    ],
    reveal: [
        'Everyone has a secret.',
        'Read your private role, then let the village know you’re ready.',
    ],
    night: [
        'The village falls quiet.',
        'Make your move before dawn. All night actions are private and final.',
    ],
    discussion: [
        'Someone knows something.',
        'Share what you saw. Question what you heard. Your neighbors are listening.',
    ],
    voting: [
        'Who do you believe?',
        'Choose a player to banish, or abstain. Your vote is final. Ties banish nobody.',
    ],
    finished: [
        'The truth comes ashore.',
        'The secrets are out. Time to find out who was far too convincing.',
    ],
};
const heading = computed(() => phaseInfo[state.value?.phase ?? 'lobby']);
const host = computed(() => state.value?.host_id === state.value?.me.id);
const readyCount = computed(
    () => state.value?.players.filter((p) => p.ready).length ?? 0,
);
const myReady = computed(
    () =>
        state.value?.players.find((p) => p.id === state.value?.me.id)?.ready ??
        false,
);
const canStart = computed(
    () =>
        !!state.value &&
        state.value.players.length >= state.value.rules.min_players &&
        readyCount.value === state.value.players.length,
);
const lobbyRoster = computed(() => {
    if (!state.value) return null;
    const count = state.value.players.length;
    const cultists = state.value.rules.cultists_by_player_count[count];
    const goal = state.value.rules.ritual_goals.find(
        (entry) => entry.players === count,
    );
    if (
        count < state.value.rules.min_players ||
        cultists === undefined ||
        !goal
    )
        return null;
    return {
        cultists,
        townspeople: count - cultists - 1,
        steps: goal.steps,
        small: count <= state.value.rules.small_gathering_max_players,
    };
});
const targets = computed(
    () =>
        state.value?.players.filter(
            (p) => p.alive && p.id !== state.value?.me.id,
        ) ?? [],
);
const canChat = computed(
    () =>
        !!state.value &&
        (['lobby', 'finished'].includes(state.value.phase) ||
            (state.value.me.alive &&
                ['discussion', 'voting'].includes(state.value.phase))),
);
const seconds = computed(() =>
    state.value?.deadline
        ? Math.max(
              0,
              Math.ceil(
                  (Date.parse(state.value.deadline) - now.value - offset) /
                      1000,
              ),
          )
        : null,
);
const timeLabel = computed(() =>
    seconds.value === null
        ? ''
        : `${Math.floor(seconds.value / 60)}:${String(seconds.value % 60).padStart(2, '0')}`,
);
const requiresTarget = computed(
    () => state.value?.phase === 'night' && state.value.me.role === 'oracle',
);
const canChooseNightTarget = computed(
    () =>
        state.value?.me.role === 'oracle' ||
        state.value?.me.role === 'veilweaver' ||
        state.value?.me.role === 'acolyte',
);
const puzzleCursed = computed(
    () =>
        state.value?.phase !== 'finished' &&
        state.value?.me.curse?.type === 'puzzle',
);
const mistCursed = computed(
    () =>
        state.value?.phase !== 'finished' &&
        state.value?.me.curse?.type === 'mist',
);
const canSelectSeat = computed(
    () =>
        !!state.value &&
        state.value.me.alive &&
        !state.value.me.submitted &&
        !pending.value &&
        !puzzleCursed.value &&
        !mistCursed.value &&
        (state.value.phase === 'voting' ||
            (state.value.phase === 'night' &&
                revealed.value &&
                canChooseNightTarget.value)),
);
function selectSeat(id: string) {
    if (canSelectSeat.value && targets.value.some((player) => player.id === id))
        target.value = id;
}
const nightLabel = computed(() =>
    state.value?.me.role === 'oracle'
        ? 'Confirm investigation'
        : state.value?.me.alignment === 'cult'
          ? 'Chant for the ritual'
          : 'Keep watch tonight',
);

function accept(next: RoomState) {
    if (disposed || (state.value && next.revision < state.value.revision))
        return;
    offset = Date.parse(next.server_time) - Date.now();
    state.value = next;
    outsider.value = false;
    disconnected.value = false;
    if (!echo) connect(next.id);
}
async function refresh() {
    if (fetching || pending.value || disposed || outsider.value) return;
    fetching = true;
    try {
        accept(
            await roomRequest<RoomState>(
                `/rooms/${encodeURIComponent(props.code)}/state`,
            ),
        );
    } catch (cause) {
        if (cause instanceof RoomError && cause.status === 403) {
            outsider.value = true;
            state.value = undefined;
            echo?.disconnect();
            echo = undefined;
        } else {
            disconnected.value = true;
            if (!state.value)
                error.value =
                    cause instanceof Error
                        ? cause.message
                        : 'Unable to reach the village.';
        }
    } finally {
        fetching = false;
        loading.value = false;
    }
}
function connect(id: number) {
    if (!import.meta.env.VITE_REVERB_APP_KEY || disposed) return;
    try {
        echo = new Echo({
            broadcaster: 'reverb',
            Pusher,
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost:
                import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
            forceTLS:
                (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: `/rooms/${encodeURIComponent(props.code)}/broadcast-auth`,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            },
        });
        echo.private(`room.${id}`)
            .subscribed(() => {
                live.value = true;
                void refresh();
            })
            .error(() => {
                live.value = false;
            })
            .listen('.room.updated', () => {
                void refresh();
            });
        echo.connector.pusher.connection.bind('connected', () => {
            void refresh();
        });
        echo.connector.pusher.connection.bind('disconnected', () => {
            live.value = false;
        });
        echo.connector.pusher.connection.bind('unavailable', () => {
            live.value = false;
        });
        echo.connector.pusher.connection.bind('error', () => {
            live.value = false;
        });
    } catch {
        live.value = false;
    }
}
async function act(type: string, extra: object = {}) {
    if (!state.value || pending.value) return false;
    pending.value = true;
    error.value = '';
    if (type === 'solve_curse') curseError.value = '';
    try {
        accept(
            await roomRequest<RoomState>(
                `/rooms/${encodeURIComponent(props.code)}/actions`,
                { type, phase_id: state.value.phase_id, ...extra },
            ),
        );
        if (type === 'night' || type === 'vote') actionSerial.value++;
        return true;
    } catch (cause) {
        const actionError =
            cause instanceof Error
                ? cause.message
                : 'Your action could not be submitted.';
        if (type === 'solve_curse') curseError.value = actionError;
        else error.value = actionError;
        return false;
    } finally {
        pending.value = false;
        void refresh();
    }
}
async function sendChat() {
    if (
        message.value.trim() &&
        (await act('chat', { body: message.value.trim() }))
    )
        message.value = '';
}
async function copyInvite() {
    try {
        await navigator.clipboard.writeText(
            `${window.location.origin}/rooms/${props.code}`,
        );
        copied.value = true;
    } catch {
        error.value = `Copy the room code to invite your friends: ${props.code}`;
    }
}
function onResume() {
    if (document.visibilityState === 'visible') void refresh();
}
async function focusRoleToggle() {
    await nextTick();
    document
        .getElementById('role-visibility-toggle')
        ?.focus({ preventScroll: true });
}
async function revealRoleForAction() {
    revealed.value = true;
    await nextTick();
    document.getElementById('private-role')?.focus();
}
watch(
    () => state.value?.me.curse?.id,
    () => {
        curseError.value = '';
    },
);
watch(
    () => state.value?.phase_id,
    () => {
        target.value = null;
        message.value = '';
        if (state.value?.phase === 'lobby' || state.value?.phase === 'reveal')
            revealed.value = false;
    },
);
watch(
    () => state.value?.messages.length,
    async () => {
        const shouldScroll =
            !chatList.value ||
            chatList.value.scrollHeight -
                chatList.value.scrollTop -
                chatList.value.clientHeight <
                90;
        await nextTick();
        if (shouldScroll && chatList.value)
            chatList.value.scrollTop = chatList.value.scrollHeight;
    },
);
onMounted(() => {
    void refresh();
    polling = setInterval(() => {
        void refresh();
    }, 3000);
    clock = setInterval(() => {
        now.value = Date.now();
    }, 500);
    document.addEventListener('visibilitychange', onResume);
    window.addEventListener('online', onResume);
});
onBeforeUnmount(() => {
    disposed = true;
    clearInterval(polling);
    clearInterval(clock);
    echo?.disconnect();
    document.removeEventListener('visibilitychange', onResume);
    window.removeEventListener('online', onResume);
});
</script>

<template>
    <Head :title="`Room ${code}`" />
    <div
        class="chanting game-page"
        :data-phase="state?.phase"
        :data-winner="state?.winner"
    >
        <header class="site-header game-header">
            <a href="/" class="wordmark" aria-label="Who's Chanting? home"
                ><span class="brand-eye"><Eye :size="26" /></span> who’s
                chanting<span class="brand-question">?</span></a
            >
            <div class="room-header-meta">
                <span
                    v-if="state"
                    class="connection"
                    :class="{ 'is-offline': disconnected }"
                    role="status"
                    ><span class="live-dot"></span
                    >{{
                        disconnected
                            ? 'Reconnecting…'
                            : live
                              ? 'Live in the village'
                              : 'Synced every 3 seconds'
                    }}</span
                ><span class="room-chip">{{ code }}</span>
            </div>
        </header>
        <main v-if="loading" class="game-loading" aria-live="polite">
            <LoaderCircle :size="30" class="spin" style="margin: 0 auto 20px" />
            <h1>Through the mist…</h1>
            <p>Finding your place in the village.</p>
        </main>
        <main v-else-if="outsider" class="outsider">
            <p class="eyebrow" style="justify-content: center">
                YOU’VE BEEN INVITED
            </p>
            <h1>A seat is waiting.</h1>
            <p>Choose your name to join room {{ code }}.</p>
            <RoomEntry
                :initial-code="code"
                :characters="characters"
                :preferred-character="preferredCharacter"
            />
        </main>
        <main v-else-if="!state" class="game-loading">
            <h1>Lost in the mist.</h1>
            <p role="alert">{{ error }}</p>
            <button class="button primary" @click="refresh">Try again</button
            ><a href="/" class="button" style="margin-left: 10px"
                >Back to the village</a
            >
        </main>
        <main v-else class="game-main">
            <div class="phase-heading">
                <div>
                    <p class="eyebrow">
                        {{
                            state.phase === 'lobby'
                                ? 'A PRIVATE GATHERING'
                                : state.phase === 'finished'
                                  ? 'THE FINAL CHAPTER'
                                  : `${state.phase === 'night' ? 'NIGHT' : 'DAY'} ${state.day} / ${state.phase.toUpperCase()}`
                        }}
                    </p>
                    <h1>{{ heading[0] }}</h1>
                    <p>{{ heading[1] }}</p>
                </div>
                <div
                    v-if="seconds !== null"
                    class="phase-clock"
                    :class="{ 'is-urgent': seconds !== null && seconds <= 10 }"
                    role="timer"
                    :aria-label="`${seconds} seconds remaining`"
                >
                    <Clock3 :size="23" />
                    <div>
                        <strong>{{ timeLabel }}</strong
                        ><span>{{
                            seconds === 0 ? 'Resolving…' : 'remaining'
                        }}</span>
                    </div>
                </div>
            </div>
            <p v-if="error" class="form-error game-error" role="alert">
                <span>{{ error }}</span
                ><button @click="error = ''" aria-label="Dismiss error">
                    Dismiss
                </button>
            </p>
            <p
                v-if="!state.me.alive && state.phase !== 'finished'"
                class="spectator-banner"
            >
                You’ve been banished. Watch the story unfold—your seat is saved
                for the next match.
            </p>
            <div class="game-tools">
                <GameGlossary /><SoundControl
                    :phase="state.phase"
                    :phase-id="state.phase_id"
                    :submitted="state.me.submitted"
                    :action-serial="actionSerial"
                    :seconds="seconds"
                    :ritual-tokens="state.ritual.tokens"
                    :winner="state.winner"
                    :alive="state.me.alive"
                    :connected="!disconnected"
                />
            </div>
            <RoomBriefing
                :state="state"
                :pending="pending"
                v-model="revealed"
            />
            <div class="play-stage">
                <div
                    class="action-stack"
                    role="region"
                    aria-label="Your next action"
                >
                    <SecretRole
                        v-if="
                            state.me.role &&
                            !['lobby', 'finished'].includes(state.phase)
                        "
                        v-show="revealed"
                        id="private-role"
                        v-model="revealed"
                        :state="state"
                        tabindex="-1"
                        @hide="focusRoleToggle"
                    />
                    <CursePanel
                        v-if="
                            state.me.curse &&
                            state.me.alive &&
                            state.phase !== 'finished'
                        "
                        :curse="state.me.curse"
                        :pending="pending"
                        :error="curseError"
                        @solve="act('solve_curse', $event)"
                    />
                    <p
                        v-if="
                            state.me.curse_notice && state.phase !== 'finished'
                        "
                        class="curse-notice"
                        role="status"
                    >
                        {{ state.me.curse_notice }}
                    </p>
                    <section
                        v-if="state.phase === 'lobby'"
                        class="game-panel action-panel"
                    >
                        <p class="eyebrow">THE CALM BEFORE THE CHANTING</p>
                        <h2>Bring a few familiar faces.</h2>
                        <p>
                            Send your friends an invite. Once everyone is ready,
                            the host can let the secrets begin.
                        </p>
                        <div v-if="lobbyRoster" class="lobby-rules">
                            <p class="eyebrow">
                                {{
                                    lobbyRoster.small
                                        ? 'SMALL GATHERING'
                                        : 'TONIGHT’S GATHERING'
                                }}
                                · {{ state.players.length }} PLAYERS
                            </p>
                            <p>
                                <strong
                                    >{{ lobbyRoster.cultists }}
                                    {{
                                        lobbyRoster.cultists === 1
                                            ? 'cultist'
                                            : 'cultists'
                                    }}, 1 Oracle, {{ lobbyRoster.townspeople }}
                                    {{
                                        lobbyRoster.townspeople === 1
                                            ? 'townsperson'
                                            : 'townspeople'
                                    }}.</strong
                                >
                                The ritual takes {{ lobbyRoster.steps }} steps.
                            </p>
                            <p v-if="lobbyRoster.small">
                                The lone cultist adds one step each night they
                                chant, even if investigated.
                            </p>
                            <p>
                                With just one cultist and one town player left,
                                the cult wins immediately.
                            </p>
                        </div>
                        <div class="invite-line">
                            <code>{{ code }}</code
                            ><button
                                class="button"
                                @click="copyInvite"
                                :aria-label="
                                    copied
                                        ? 'Invite copied'
                                        : 'Copy invite link'
                                "
                            >
                                <Check v-if="copied" :size="18" /><Copy
                                    v-else
                                    :size="18"
                                />{{ copied ? 'Copied' : 'Invite' }}
                            </button>
                        </div>
                        <CharacterPicker
                            v-if="signedIn"
                            :model-value="state.me.character"
                            :characters="characters"
                            :disabled="pending"
                            @update:model-value="
                                act('character', { character: $event })
                            "
                        />
                        <p v-else class="small-help">
                            Your character was chosen at random. Every look can
                            have any secret role.
                        </p>
                        <p class="lobby-readiness" role="status">
                            <strong
                                >{{ readyCount }} of
                                {{ state.players.length }} villagers
                                ready.</strong
                            >
                            {{
                                state.players.length < state.rules.min_players
                                    ? `${state.rules.min_players - state.players.length} more needed to begin.`
                                    : 'Everyone must be ready to begin.'
                            }}
                        </p>
                        <button
                            class="button"
                            :class="{ primary: !myReady }"
                            :disabled="pending"
                            @click="act('ready')"
                        >
                            <Check :size="16" />{{
                                myReady
                                    ? 'Ready — click to unready'
                                    : 'I’m ready for this'
                            }}</button
                        ><button
                            v-if="host"
                            class="button primary"
                            :disabled="pending || !canStart"
                            @click="act('start')"
                        >
                            Let the secrets begin <Moon :size="16" />
                        </button>
                        <p class="small-help">
                            {{
                                host
                                    ? 'Everyone must be ready before you can start.'
                                    : 'The host will start the match when everyone is ready.'
                            }}
                            Keep this browser’s cookies to return to your seat.
                        </p>
                    </section>
                    <template v-if="state.phase === 'reveal'">
                        <section
                            id="current-action"
                            tabindex="-1"
                            class="game-panel action-panel"
                        >
                            <h2>Keep it close.</h2>
                            <p>
                                Your role and mission are always trustworthy.
                                Take a moment to read them before the first
                                night.
                            </p>
                            <div
                                v-if="state.me.submitted"
                                class="state-message"
                            >
                                <Check :size="17" />You’re ready. Waiting for
                                the other villagers.
                            </div>
                            <button
                                v-else
                                class="button primary"
                                :disabled="pending || !revealed"
                                @click="act('ready')"
                            >
                                <ShieldCheck :size="17" />I know who I am
                            </button>
                        </section></template
                    >
                    <section
                        v-if="state.phase === 'night'"
                        class="game-panel action-panel"
                        id="current-action"
                        tabindex="-1"
                    >
                        <p class="eyebrow">
                            <Moon :size="13" /> AFTER THE CANDLES GO OUT
                        </p>
                        <h2>
                            {{
                                !state.me.alive
                                    ? 'A quiet night to watch.'
                                    : !revealed
                                      ? 'Your private night move.'
                                      : state.me.role === 'oracle'
                                        ? 'Look a little closer.'
                                        : state.me.alignment === 'cult'
                                          ? 'Something stirs below.'
                                          : 'Keep a watchful eye.'
                            }}
                        </h2>
                        <p v-if="!state.me.alive">
                            The living are making their moves. Dawn will come
                            soon.
                        </p>
                        <div
                            v-else-if="!revealed && state.me.submitted"
                            class="state-message"
                        >
                            <Check :size="17" />Your action is sealed. Wait for
                            dawn.
                        </div>
                        <template v-else-if="!revealed">
                            <p>
                                Your night action stays hidden with your role.
                                Reveal it when you are ready to make your move.
                            </p>
                            <button
                                class="button primary"
                                @click="revealRoleForAction"
                            >
                                <Eye :size="16" />Reveal my role & action
                            </button>
                        </template>
                        <template v-else
                            ><p>
                                {{
                                    state.me.role === 'oracle'
                                        ? 'Choose a living player to investigate. Your private reading arrives at dawn.'
                                        : state.me.role === 'veilweaver'
                                          ? 'Choose someone to veil and curse, or chant without a target. Their alignment appears reversed tonight; a random curse takes hold at dawn.'
                                          : state.me.role === 'acolyte'
                                            ? 'Choose someone to curse, or chant without a target. A random curse takes hold at dawn. Your chant advances the ritual if your shared mission’s condition is met.'
                                            : 'Stay alert. You have no secret ability, but your voice and your vote matter in the morning.'
                                }}
                            </p>
                            <div
                                v-if="state.me.submitted"
                                class="state-message"
                            >
                                <Check :size="17" />Your action is sealed. Wait
                                for dawn.
                            </div>
                            <template v-else
                                ><div
                                    v-if="canChooseNightTarget"
                                    class="target-list"
                                    role="group"
                                    aria-label="Night target"
                                >
                                    <button
                                        v-for="player in targets"
                                        :key="player.id"
                                        class="target-button"
                                        :class="{
                                            selected: target === player.id,
                                        }"
                                        :aria-pressed="target === player.id"
                                        :disabled="pending || puzzleCursed"
                                        @click="target = player.id"
                                    >
                                        <Check
                                            v-if="target === player.id"
                                            :size="15"
                                        /><Circle v-else :size="15" /><span>{{
                                            player.name
                                        }}</span></button
                                    ><button
                                        v-if="!requiresTarget"
                                        class="target-button"
                                        :class="{ selected: target === null }"
                                        :aria-pressed="target === null"
                                        :disabled="pending"
                                        @click="target = null"
                                    >
                                        <Moon :size="15" /><span
                                            >Chant without a target</span
                                        >
                                    </button>
                                </div>
                                <button
                                    class="button primary"
                                    :disabled="
                                        pending ||
                                        (requiresTarget && !target) ||
                                        (puzzleCursed && !!target)
                                    "
                                    :aria-describedby="
                                        puzzleCursed
                                            ? 'night-curse-help'
                                            : undefined
                                    "
                                    @click="act('night', { target })"
                                >
                                    {{ nightLabel }}<Check :size="16" />
                                </button>
                                <p
                                    v-if="puzzleCursed"
                                    id="night-curse-help"
                                    class="curse-action-help"
                                >
                                    Solve your curse above before confirming a
                                    target.
                                    {{
                                        requiresTarget
                                            ? 'You can let this night pass without investigating.'
                                            : 'You can still act without a target.'
                                    }}
                                </p>
                                <p class="small-help">
                                    One final action per night. Missing the
                                    deadline forfeits your action.
                                </p></template
                            ></template
                        >
                    </section>
                    <section
                        v-if="state.phase === 'discussion'"
                        class="game-panel action-panel"
                        id="current-action"
                        tabindex="-1"
                    >
                        <p class="eyebrow">THE MORNING AFTER</p>
                        <h2>Well, that was suspicious.</h2>
                        <p>
                            Compare stories in the village chat or talk with
                            your friends on a call. Keep an eye on the ritual.
                            Mark yourself ready when you have said your piece.
                            Voting begins when everyone living is ready, or the
                            timer ends.
                        </p>
                        <template v-if="state.me.alive"
                            ><p
                                v-if="state.me.submitted"
                                class="state-message"
                                role="status"
                            >
                                <Check :size="17" />You are ready for voting.
                                The village can see your badge.
                            </p>
                            <button
                                v-else
                                class="button primary"
                                :disabled="pending"
                                @click="act('discussion_ready')"
                            >
                                <Check :size="17" />I’m done discussing
                            </button>
                            <p class="small-help">
                                {{
                                    state.players.filter(
                                        (p) => p.alive && p.discussion_ready,
                                    ).length
                                }}
                                of
                                {{
                                    state.players.filter((p) => p.alive).length
                                }}
                                ready for voting. This choice is final.
                            </p></template
                        >
                    </section>
                    <section
                        v-if="state.phase === 'voting'"
                        class="game-panel action-panel"
                        id="current-action"
                        tabindex="-1"
                    >
                        <p class="eyebrow">
                            <Vote :size="14" /> THE VILLAGE MUST DECIDE
                        </p>
                        <h2>Point a finger.</h2>
                        <p v-if="!state.me.alive">
                            The living are deciding who to banish. You can watch
                            the result when the votes are counted.
                        </p>
                        <template v-else
                            ><p>
                                The player with the most votes is banished.
                                Ties, or abstention winning the vote, mean no
                                banishment.
                            </p>
                            <div
                                v-if="state.me.submitted"
                                class="state-message"
                            >
                                <Check :size="17" />Your vote is sealed. The
                                village will know the result shortly.
                            </div>
                            <template v-else
                                ><div
                                    class="target-list"
                                    role="group"
                                    aria-label="Vote to banish"
                                >
                                    <button
                                        v-for="player in targets"
                                        :key="player.id"
                                        class="target-button"
                                        :class="{
                                            selected: target === player.id,
                                        }"
                                        :aria-pressed="target === player.id"
                                        :disabled="pending || puzzleCursed"
                                        @click="target = player.id"
                                    >
                                        <Check
                                            v-if="target === player.id"
                                            :size="15"
                                        /><Circle v-else :size="15" /><span>{{
                                            player.name
                                        }}</span></button
                                    ><button
                                        class="target-button"
                                        :class="{ selected: target === null }"
                                        :aria-pressed="target === null"
                                        :disabled="pending"
                                        @click="target = null"
                                    >
                                        <Circle :size="15" /><span
                                            >Abstain</span
                                        >
                                    </button>
                                </div>
                                <button
                                    class="button coral"
                                    :disabled="
                                        pending || (puzzleCursed && !!target)
                                    "
                                    :aria-describedby="
                                        puzzleCursed
                                            ? 'vote-curse-help'
                                            : undefined
                                    "
                                    @click="act('vote', { target })"
                                >
                                    {{
                                        target
                                            ? `Vote to banish ${targets.find((p) => p.id === target)?.name}`
                                            : 'Confirm abstention'
                                    }}<Vote :size="16" />
                                </button>
                                <p
                                    v-if="puzzleCursed"
                                    id="vote-curse-help"
                                    class="curse-action-help"
                                >
                                    Solve your curse above before confirming a
                                    target, or choose Abstain.
                                </p>
                                <p class="small-help">
                                    You cannot change a submitted vote. Missing
                                    the deadline counts as abstention.
                                </p></template
                            ></template
                        >
                    </section>
                    <section
                        v-if="state.phase === 'finished'"
                        class="game-panel action-panel victory-panel"
                        :class="{ 'cult-win': state.winner === 'cult' }"
                    >
                        <div class="victory-sigil" aria-hidden="true">
                            {{ state.winner === 'cult' ? '◈' : '✦' }}
                        </div>
                        <p class="eyebrow" style="justify-content: center">
                            {{
                                state.winner === 'cult'
                                    ? 'THE CULT PREVAILS'
                                    : 'THE TOWN PREVAILS'
                            }}
                        </p>
                        <h2>
                            {{
                                state.winner === 'cult'
                                    ? 'The sea answers back.'
                                    : 'A new dawn. For now.'
                            }}
                        </h2>
                        <p>{{ state.win_reason }}</p>
                        <p>
                            Every role is now face-up around the table. Time for
                            a few explanations.
                        </p>
                        <button
                            v-if="host"
                            class="button primary"
                            :disabled="pending"
                            @click="act('rematch')"
                        >
                            One more suspicious evening
                        </button>
                        <p v-else class="small-help">
                            Your host can start a new gathering. Your seat is
                            already saved.
                        </p>
                    </section>
                </div>
                <CardTable
                    :class="{ 'is-mist-cursed': mistCursed }"
                    :players="state.players"
                    :phase="state.phase"
                    :phase-id="state.phase_id"
                    :me-id="state.me.id"
                    :submitted="state.me.submitted"
                    :action-serial="actionSerial"
                    :ritual-tokens="state.ritual.tokens"
                    :ritual-threshold="
                        state.phase === 'lobby'
                            ? (lobbyRoster?.steps ?? 0)
                            : state.ritual.threshold
                    "
                    :winner="state.winner"
                    :selected-target="
                        state.phase === 'night' && !revealed ? null : target
                    "
                    :can-select="canSelectSeat"
                    @select="selectSeat"
                />
            </div>
            <div
                class="match-layout"
                :class="{ 'is-lobby': state.phase === 'lobby' }"
            >
                <section class="game-panel roster-panel">
                    <div class="panel-title">
                        <h2>The villagers</h2>
                        <span
                            >{{ state.players.length }} /
                            {{ state.rules.max_players }}</span
                        >
                    </div>
                    <ul class="player-list">
                        <li
                            v-for="player in state.players"
                            :key="player.id"
                            class="player-row"
                            :class="{
                                'is-me': player.id === state.me.id,
                                'is-dead': !player.alive,
                            }"
                        >
                            <CharacterPortrait
                                :character="player.character"
                                decorative
                            /><span class="player-name"
                                >{{ player.name
                                }}<small
                                    >{{
                                        player.id === state.me.id
                                            ? 'You'
                                            : player.id === state.host_id
                                              ? 'Host'
                                              : !player.alive
                                                ? 'Banished'
                                                : 'Villager'
                                    }}<template
                                        v-if="
                                            state.phase === 'finished' &&
                                            player.role
                                        "
                                    >
                                        ·
                                        {{
                                            roles[player.role]?.name ??
                                            player.role
                                        }}</template
                                    ></small
                                ></span
                            ><span
                                v-if="state.phase === 'lobby'"
                                class="player-status"
                                >{{ player.ready ? 'Ready' : 'Waiting' }}</span
                            >
                            <span
                                v-else-if="
                                    state.phase === 'discussion' &&
                                    player.alive &&
                                    player.discussion_ready
                                "
                                class="player-status discussion-badge"
                                >Ready for voting</span
                            >
                        </li>
                    </ul>
                    <p class="roster-footnote">
                        {{
                            state.phase === 'lobby'
                                ? `${readyCount} ready · ${state.rules.min_players} needed to begin`
                                : `${state.players.filter((p) => p.alive).length} villagers remain`
                        }}
                    </p>
                </section>
                <div class="main-stack">
                    <MatchRecap
                        v-if="state.phase === 'finished' && state.recap"
                        :recap="state.recap"
                        :players="state.players"
                    />
                    <section class="game-panel chat-panel">
                        <div class="panel-title">
                            <h2>Village chat</h2>
                            <span>Everyone can read this</span>
                        </div>
                        <p
                            v-if="mistCursed"
                            class="chat-mist-notice"
                            role="status"
                        >
                            A curse tangles the village’s words. Complete the
                            focus challenge above to read clearly again.
                        </p>
                        <div
                            ref="chatList"
                            class="chat-messages"
                            role="log"
                            aria-label="Village messages"
                            aria-live="polite"
                        >
                            <p v-if="!state.messages.length" class="chat-empty">
                                A suspicious silence.<br />Someone has to speak
                                first.
                            </p>
                            <article
                                v-for="entry in state.messages"
                                :key="entry.id"
                                class="chat-message"
                            >
                                <strong>{{ entry.name }}</strong>
                                <p>{{ entry.body }}</p>
                            </article>
                        </div>
                        <form class="chat-form" @submit.prevent="sendChat">
                            <div class="chat-compose">
                                <label for="chat-message" class="sr-only"
                                    >Message the village</label
                                ><input
                                    id="chat-message"
                                    v-model="message"
                                    maxlength="280"
                                    autocomplete="off"
                                    :disabled="!canChat || pending"
                                    :placeholder="
                                        canChat
                                            ? 'What’s your story?'
                                            : 'The village is quiet for now…'
                                    "
                                /><button
                                    class="button"
                                    aria-label="Send message"
                                    :disabled="
                                        !canChat || pending || !message.trim()
                                    "
                                >
                                    <Send :size="17" />
                                </button>
                            </div>
                            <p class="chat-hint">
                                {{
                                    canChat
                                        ? `${message.length}/280 · Keep your secrets. Or don’t.`
                                        : !state.me.alive
                                          ? 'Banished players can chat again after the match.'
                                          : 'Chat opens in the lobby, discussion, voting, and after the match.'
                                }}
                            </p>
                        </form>
                    </section>
                </div>
                <aside
                    class="side-stack"
                    aria-label="Ritual and private information"
                >
                    <section class="game-panel ritual-panel">
                        <div class="ritual-heading">
                            <h2>
                                {{
                                    state.phase === 'finished'
                                        ? 'Ritual achieved'
                                        : 'The gathering'
                                }}
                            </h2>
                            <span
                                v-if="
                                    state.phase !== 'lobby' &&
                                    state.ritual.threshold > 0
                                "
                                >{{ state.ritual.tokens }}
                                <small
                                    >/ {{ state.ritual.threshold }} steps</small
                                ></span
                            >
                        </div>
                        <p
                            v-if="state.phase === 'lobby'"
                            class="ritual-pregame"
                        >
                            {{
                                lobbyRoster
                                    ? `${lobbyRoster.steps} steps with ${state.players.length} players.`
                                    : `Gather at least ${state.rules.min_players} players to see the ritual goal.`
                            }}
                        </p>
                        <div
                            v-if="
                                state.phase !== 'lobby' &&
                                state.ritual.threshold > 0
                            "
                            class="ritual-tokens"
                            role="progressbar"
                            aria-label="Cult ritual progress in steps"
                            :aria-valuenow="state.ritual.tokens"
                            :aria-valuemin="0"
                            :aria-valuemax="state.ritual.threshold"
                        >
                            <span
                                v-for="token in state.ritual.threshold"
                                :key="token"
                                class="ritual-token"
                                :class="{
                                    filled: token <= state.ritual.tokens,
                                }"
                            ></span>
                        </div>
                        <p>
                            {{
                                state.phase === 'lobby'
                                    ? 'The goal adjusts as friends join. Fill the track and the cult wins.'
                                    : state.phase === 'finished'
                                      ? `${state.ritual.tokens} of ${state.ritual.threshold} ritual steps were completed. ${state.ritual.tokens >= state.ritual.threshold ? 'The ritual reached its goal.' : 'The match ended before the ritual was complete.'}`
                                      : `Each filled mark is one step closer. ${Math.max(0, state.ritual.threshold - state.ritual.tokens)} more steps complete the ritual and the cult wins.`
                            }}
                        </p>
                        <p
                            v-if="!['lobby', 'finished'].includes(state.phase)"
                            class="ritual-curse-level"
                        >
                            <strong
                                >Curse level {{ state.ritual.level }} /
                                3</strong
                            >
                            {{
                                state.ritual.level === 3
                                    ? 'Misdirection can now turn a chosen target against you.'
                                    : 'As the ritual advances, curses become harder. Misdirection awakens at level 3.'
                            }}
                        </p>
                    </section>
                </aside>
            </div>
            <p class="game-bottom-note">
                Your seat stays with this browser. Refresh freely. ·
                <a
                    href="/#how-to-play"
                    target="_blank"
                    rel="noopener"
                    class="quiet-link"
                    >Read the rules</a
                >
            </p>
        </main>
    </div>
</template>
