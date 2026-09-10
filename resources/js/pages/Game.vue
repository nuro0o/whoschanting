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
    HelpCircle,
    MessageCircle,
    Play,
    LockKeyhole,
    EyeOff,
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
import RitualWarning from '@/components/chanting/RitualWarning.vue';
import RoomEvents from '@/components/chanting/RoomEvents.vue';
import RoomHelp from '@/components/chanting/RoomHelp.vue';
import CharacterPicker from '@/components/chanting/CharacterPicker.vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import CursePanel from '@/components/chanting/CursePanel.vue';
import MatchRecap from '@/components/chanting/MatchRecap.vue';
import CardTable from '@/components/chanting/CardTable.vue';
import GameAtmosphere from '@/components/chanting/GameAtmosphere.vue';
import SoundControl from '@/components/chanting/SoundControl.vue';
import type { MusicLibrary } from '@/lib/phaseMusic';
import { prefersReducedMotion } from '@/composables/usePanelMotion';
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
        music?: MusicLibrary;
    }>(),
    {
        characters: () => defaultCharacters,
        music: () => ({ day: [], night: [] }),
    },
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
type RoomView = 'play' | 'role' | 'chat' | 'help';
const activeView = ref<RoomView>('play');
const workspace = ref<HTMLElement>();
let viewAnimation: Animation | undefined;
let navigationTimer: ReturnType<typeof setTimeout> | undefined;
function finishNavigation() {
    clearTimeout(navigationTimer);
    window.removeEventListener('scrollend', finishNavigation);
    workspace.value?.style.removeProperty('min-height');
}
const roleRead = ref(false);
const hintDismissed = ref(false);
const readMessageIds = ref<Set<string>>(new Set());
const readResultCount = ref(0);
const secretRole = ref<InstanceType<typeof SecretRole>>();
const chatVisible = ref(false);
let chatObserver: IntersectionObserver | undefined;
const unreadChat = computed(
    () =>
        state.value?.messages.filter(
            (entry) =>
                entry.name !== state.value?.me.name &&
                !readMessageIds.value.has(entry.id),
        ).length ?? 0,
);
const newResults = computed(() =>
    revealed.value
        ? Math.max(
              0,
              (state.value?.me.results.length ?? 0) - readResultCount.value,
          )
        : 0,
);
const phaseLabel = computed(() =>
    state.value?.ritual.final_vote
        ? state.value.phase === 'discussion'
            ? 'Final discussion'
            : 'Final vote'
        : {
              lobby: 'Lobby',
              reveal: 'Read your role',
              night: 'Night actions',
              discussion: 'Discussion',
              voting: 'Voting',
              finished: 'Match complete',
          }[state.value?.phase ?? 'lobby'],
);
const showTargetHint = computed(
    () => !hintDismissed.value && canSelectSeat.value,
);
function rememberHint() {
    hintDismissed.value = true;
    try {
        localStorage.setItem('chanting-target-hint', 'seen');
    } catch {
        /* Storage may be unavailable. */
    }
}
function readResults() {
    readResultCount.value = state.value?.me.results.length ?? 0;
}
async function openResults() {
    await navigate('role');
    await secretRole.value?.showResults();
}
function markChatRead() {
    if (
        !chatVisible.value ||
        document.visibilityState !== 'visible' ||
        mistCursed.value ||
        puzzleCursed.value ||
        !chatList.value ||
        chatList.value.scrollHeight -
            chatList.value.scrollTop -
            chatList.value.clientHeight >
            90
    )
        return;
    readMessageIds.value = new Set(
        state.value?.messages.map((entry) => entry.id) ?? [],
    );
}
async function navigate(view: RoomView, focus = true) {
    if (activeView.value === view) return;
    // A shorter view must not clamp the scroll position before we can scroll.
    const previousHeight = workspace.value?.getBoundingClientRect().height;
    finishNavigation();
    if (workspace.value && previousHeight) {
        workspace.value.style.minHeight = `${previousHeight}px`;
    }
    activeView.value = view;
    await nextTick();
    if (activeView.value !== view) return;
    const destination = document.getElementById(`room-${view}`);
    viewAnimation?.cancel();
    if (destination && !prefersReducedMotion()) {
        viewAnimation = destination.animate(
            [
                { opacity: 0, translate: '0 6px' },
                { opacity: 1, translate: '0 0' },
            ],
            { duration: 220, easing: 'ease-out' },
        );
    }
    if (focus) {
        destination?.focus({ preventScroll: true });
        const top = destination?.getBoundingClientRect().top;
        // Keep the page still when the destination is already within reach.
        if (top !== undefined && (top < 55 || top > window.innerHeight - 160)) {
            window.addEventListener('scrollend', finishNavigation, {
                once: true,
            });
            navigationTimer = setTimeout(finishNavigation, 1000);
            destination?.scrollIntoView({
                behavior: prefersReducedMotion() ? 'instant' : 'smooth',
                block: 'start',
            });
            return;
        }
    }
    finishNavigation();
}
async function showRole() {
    revealed.value = true;
    await navigate('role');
}
async function showChat() {
    await navigate('chat');
    document.getElementById('chat-message')?.focus({ preventScroll: true });
}

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
    if (document.visibilityState === 'visible') {
        void refresh();
        markChatRead();
    }
}
async function revealRoleForAction() {
    revealed.value = true;
    await nextTick();
    document.getElementById('current-action')?.focus({ preventScroll: true });
}
watch([revealed, activeView], () => {
    if (revealed.value && activeView.value === 'role') roleRead.value = true;
});
watch(chatList, (element) => {
    chatObserver?.disconnect();
    if (!element) return;
    chatObserver = new IntersectionObserver(
        ([entry]) => {
            chatVisible.value = entry.isIntersecting;
            markChatRead();
        },
        { threshold: 0.6 },
    );
    chatObserver.observe(element);
});
watch([mistCursed, puzzleCursed], () => markChatRead());
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
        if (state.value?.phase === 'lobby' || state.value?.phase === 'reveal') {
            revealed.value = false;
            roleRead.value = false;
            readResultCount.value = 0;
        }
    },
);
watch(
    () => state.value?.messages.at(-1)?.id,
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
        markChatRead();
    },
);
onMounted(() => {
    try {
        hintDismissed.value =
            localStorage.getItem('chanting-target-hint') === 'seen';
    } catch {
        /* Optional preference. */
    }
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
    chatObserver?.disconnect();
    viewAnimation?.cancel();
    document.removeEventListener('visibilitychange', onResume);
    finishNavigation();
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
        <GameAtmosphere
            v-if="!loading"
            :phase="state?.phase ?? 'lobby'"
            :ritual-tokens="state?.ritual.tokens ?? 0"
            :ritual-threshold="state?.ritual.threshold ?? 0"
            :final-vote="state?.ritual.final_vote ?? false"
        />
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
                                ? 'LOBBY'
                                : state.phase === 'finished'
                                  ? 'MATCH COMPLETE'
                                  : `${phaseLabel} · ${state.phase === 'night' ? 'Night' : 'Day'} ${state.day}`
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
            <Transition name="room-notice">
                <p v-if="error" class="form-error game-error" role="alert">
                    <span>{{ error }}</span
                    ><button @click="error = ''" aria-label="Dismiss error">
                        Dismiss
                    </button>
                </p>
            </Transition>
            <p
                v-if="!state.me.alive && state.phase !== 'finished'"
                class="spectator-banner"
            >
                You’ve been banished. Watch the story unfold—your seat is saved
                for the next match.
            </p>
            <div class="game-tools">
                <SoundControl
                    :music="music"
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
            <nav class="room-navigation" aria-label="Room sections">
                <button
                    id="nav-play"
                    :class="{ active: activeView === 'play' }"
                    :aria-current="activeView === 'play' ? 'page' : undefined"
                    aria-controls="room-play"
                    @click="navigate('play')"
                >
                    <Play :size="18" /><span>Play</span
                    ><span
                        v-if="
                            state.me.alive &&
                            !state.me.submitted &&
                            ['night', 'voting'].includes(state.phase)
                        "
                        class="nav-action-dot"
                        aria-label="Action needed"
                    ></span>
                </button>
                <button
                    id="nav-role"
                    :class="{ active: activeView === 'role' }"
                    :aria-current="activeView === 'role' ? 'page' : undefined"
                    aria-controls="room-role"
                    @click="navigate('role')"
                >
                    <LockKeyhole :size="18" /><span>My role</span
                    ><span v-if="newResults" class="room-badge"
                        >{{ newResults }} new</span
                    >
                </button>
                <button
                    id="nav-chat"
                    :class="{ active: activeView === 'chat' }"
                    :aria-current="activeView === 'chat' ? 'page' : undefined"
                    aria-controls="room-chat"
                    @click="navigate('chat')"
                >
                    <MessageCircle :size="18" /><span>Chat</span
                    ><span
                        v-if="unreadChat"
                        class="room-badge"
                        :aria-label="`${unreadChat} unread messages`"
                        >{{ unreadChat > 99 ? '99+' : unreadChat }}</span
                    >
                </button>
                <button
                    id="nav-help"
                    :class="{ active: activeView === 'help' }"
                    :aria-current="activeView === 'help' ? 'page' : undefined"
                    aria-controls="room-help"
                    @click="navigate('help')"
                >
                    <HelpCircle :size="18" /><span>Help</span>
                </button>
            </nav>
            <CursePanel
                v-if="
                    state.me.curse &&
                    state.me.alive &&
                    state.phase !== 'finished'
                "
                :curse="state.me.curse"
                :time-label="timeLabel"
                :phase-label="phaseLabel"
                :final-vote="state.ritual.final_vote"
                :disconnected="disconnected"
                :pending="pending"
                :error="curseError"
                @solve="act('solve_curse', $event)"
            />
            <p
                v-if="state.me.curse_notice && state.phase !== 'finished'"
                class="curse-notice"
                role="status"
            >
                {{ state.me.curse_notice }}
            </p>

            <div ref="workspace" class="room-workspace" :data-view="activeView">
                <div class="room-phase-context" aria-label="Current phase">
                    <span
                        >{{ phaseLabel
                        }}<template
                            v-if="!['lobby', 'finished'].includes(state.phase)"
                        >
                            · Day {{ state.day }}</template
                        ></span
                    >
                    <span v-if="timeLabel"
                        ><Clock3 :size="14" />{{
                            seconds === 0 ? 'Resolving…' : `${timeLabel} left`
                        }}</span
                    >
                </div>
                <RitualWarning v-if="state.ritual.final_vote" />
                <div
                    id="room-play"
                    class="room-play"
                    role="region"
                    tabindex="-1"
                    aria-label="Play"
                    v-show="activeView === 'play'"
                >
                    <div
                        v-if="
                            state.me.role &&
                            !['lobby', 'finished'].includes(state.phase)
                        "
                        class="room-private-shortcut"
                    >
                        <span
                            ><LockKeyhole :size="15" />{{
                                revealed
                                    ? (roles[state.me.role]?.name ??
                                      'Your role')
                                    : 'Your secrets are hidden'
                            }}</span
                        >
                        <button
                            id="role-visibility-toggle"
                            class="quiet-link"
                            @click="revealed ? (revealed = false) : showRole()"
                        >
                            <EyeOff v-if="revealed" :size="15" /><Eye
                                v-else
                                :size="15"
                            />{{ revealed ? 'Hide secrets' : 'Reveal my role' }}
                        </button>
                        <button
                            v-if="newResults"
                            class="button new-result"
                            @click="openResults"
                        >
                            {{ newResults }} new result{{
                                newResults === 1 ? '' : 's'
                            }}
                        </button>
                    </div>
                    <RoomBriefing
                        :state="state"
                        :pending="pending"
                        :time-label="timeLabel"
                    >
                        <div
                            v-if="showTargetHint"
                            class="target-hint"
                            role="note"
                        >
                            <p>
                                <strong
                                    >Select first. Confirm when ready.</strong
                                >
                                Tap a player here or at the table. Change your
                                selection any time before confirming.
                            </p>
                            <button class="quiet-link" @click="rememberHint">
                                Got it
                            </button>
                        </div>
                        <section
                            v-if="state.phase === 'lobby'"
                            class="game-panel action-panel"
                        >
                            <p class="eyebrow">THE CALM BEFORE THE CHANTING</p>

                            <p>
                                Send your friends an invite. Once everyone is
                                ready, the host can let the secrets begin.
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
                                        }}, 1 Oracle,
                                        {{ lobbyRoster.townspeople }}
                                        {{
                                            lobbyRoster.townspeople === 1
                                                ? 'townsperson'
                                                : 'townspeople'
                                        }}.</strong
                                    >
                                    The ritual takes
                                    {{ lobbyRoster.steps }} steps.
                                </p>
                                <p v-if="lobbyRoster.small">
                                    The lone cultist adds one step each night
                                    they chant, even if investigated.
                                </p>
                                <p>
                                    With just one cultist and one town player
                                    left, the cult wins immediately.
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
                                    />{{ copied ? 'Copied' : 'Invite friends' }}
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
                                Your character was chosen at random. Every look
                                can have any secret role.
                            </p>
                            <p class="lobby-readiness" role="status">
                                <strong
                                    >{{ readyCount }} of
                                    {{ state.players.length }} villagers
                                    ready.</strong
                                >
                                {{
                                    state.players.length <
                                    state.rules.min_players
                                        ? `${state.rules.min_players - state.players.length} more needed to begin.`
                                        : 'Everyone must be ready to begin.'
                                }}
                            </p>
                            <button
                                class="button"
                                :class="{ primary: !myReady }"
                                :aria-pressed="myReady"
                                :disabled="pending"
                                @click="act('ready')"
                            >
                                <Check :size="16" />{{
                                    myReady ? 'Ready · undo' : 'Ready'
                                }}</button
                            ><button
                                v-if="host"
                                class="button primary"
                                :disabled="pending || !canStart"
                                @click="act('start')"
                            >
                                Start game <Moon :size="16" />
                            </button>
                            <p class="small-help">
                                {{
                                    host
                                        ? 'Everyone must be ready before you can start.'
                                        : 'The host will start the match when everyone is ready.'
                                }}
                                Keep this browser’s cookies to return to your
                                seat.
                            </p>
                        </section>
                        <template v-if="state.phase === 'reveal'">
                            <section
                                id="current-action"
                                tabindex="-1"
                                class="game-panel action-panel"
                            >
                                <p>
                                    Your private card explains your objective,
                                    ability and team. Keep it away from curious
                                    neighbors.
                                </p>
                                <button
                                    v-if="!state.me.submitted"
                                    class="button"
                                    @click="showRole"
                                >
                                    <Eye :size="16" />{{
                                        roleRead
                                            ? 'Read my role again'
                                            : 'Reveal my role'
                                    }}
                                </button>
                                <div
                                    v-if="state.me.submitted"
                                    class="state-message"
                                >
                                    <Check :size="17" />You’re ready. Waiting
                                    for the other villagers.
                                </div>
                                <button
                                    v-else
                                    class="button primary"
                                    :disabled="pending || !roleRead"
                                    @click="act('ready')"
                                >
                                    <ShieldCheck :size="17" />Ready for night
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
                                The living are making their moves. Dawn will
                                come soon.
                            </p>
                            <div
                                v-else-if="!revealed && state.me.submitted"
                                class="state-message"
                            >
                                <Check :size="17" />Your action is sealed. Wait
                                for dawn.
                            </div>
                            <template v-else-if="!revealed">
                                <p>
                                    Your night action stays hidden with your
                                    role. Reveal it when you are ready to make
                                    your move.
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
                                    <Check :size="17" />Your action is sealed.
                                    Wait for dawn.
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
                                            :disabled="
                                                pending ||
                                                puzzleCursed ||
                                                mistCursed ||
                                                disconnected
                                            "
                                            @click="target = player.id"
                                        >
                                            <Check
                                                v-if="target === player.id"
                                                :size="15"
                                            /><Circle
                                                v-else
                                                :size="15"
                                            /><span>{{
                                                player.name
                                            }}</span></button
                                        ><button
                                            v-if="!requiresTarget"
                                            class="target-button"
                                            :class="{
                                                selected: target === null,
                                            }"
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
                                            mistCursed ||
                                            puzzleCursed ||
                                            disconnected ||
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
                                        Complete the curse challenge to return
                                        to your action. The phase timer keeps
                                        running.
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

                            <p>
                                Compare stories in the village chat or talk with
                                your friends on a call. Keep an eye on the
                                ritual. Mark yourself ready when you have said
                                your piece. Voting begins when everyone living
                                is ready, or the timer ends.
                            </p>
                            <button class="button" @click="showChat">
                                <MessageCircle :size="17" />Open village
                                chat<span
                                    v-if="unreadChat"
                                    class="room-badge"
                                    >{{ unreadChat }}</span
                                >
                            </button>
                            <template v-if="state.me.alive"
                                ><p
                                    v-if="state.me.submitted"
                                    class="state-message"
                                    role="status"
                                >
                                    <Check :size="17" />You are ready for
                                    voting. The village can see your badge.
                                </p>
                                <button
                                    v-else
                                    class="button primary"
                                    :disabled="pending"
                                    @click="act('discussion_ready')"
                                >
                                    <Check :size="17" />Ready for voting
                                </button>
                                <p class="small-help">
                                    {{
                                        state.players.filter(
                                            (p) =>
                                                p.alive && p.discussion_ready,
                                        ).length
                                    }}
                                    of
                                    {{
                                        state.players.filter((p) => p.alive)
                                            .length
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

                            <p v-if="!state.me.alive">
                                The living are deciding who to banish. You can
                                watch the result when the votes are counted.
                            </p>
                            <template v-else>
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
                                            :disabled="
                                                pending ||
                                                puzzleCursed ||
                                                mistCursed ||
                                                disconnected
                                            "
                                            @click="target = player.id"
                                        >
                                            <Check
                                                v-if="target === player.id"
                                                :size="15"
                                            /><Circle
                                                v-else
                                                :size="15"
                                            /><span>{{
                                                player.name
                                            }}</span></button
                                        ><button
                                            class="target-button"
                                            :class="{
                                                selected: target === null,
                                            }"
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
                                            pending ||
                                            puzzleCursed ||
                                            mistCursed ||
                                            disconnected
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
                                                ? `Confirm vote: ${targets.find((p) => p.id === target)?.name}`
                                                : 'Confirm abstention'
                                        }}<Vote :size="16" />
                                    </button>
                                    <p
                                        v-if="puzzleCursed"
                                        id="vote-curse-help"
                                        class="curse-action-help"
                                    >
                                        Complete the curse challenge to return
                                        to your vote.
                                    </p>
                                    <p class="small-help">
                                        You cannot change a submitted vote.
                                        Missing the deadline counts as
                                        abstention. Most votes banishes a
                                        player; ties or abstention winning
                                        banish nobody.
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
                                Every role is now face-up around the table. Time
                                for a few explanations.
                            </p>
                            <button
                                v-if="host"
                                class="button primary"
                                :disabled="pending"
                                @click="act('rematch')"
                            >
                                Play again
                            </button>
                            <p v-else class="small-help">
                                Your host can start a new gathering. Your seat
                                is already saved.
                            </p>
                        </section>
                    </RoomBriefing>
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

                    <RoomEvents :log="state.log" />
                    <MatchRecap
                        v-if="state.phase === 'finished' && state.recap"
                        :recap="state.recap"
                        :players="state.players"
                    />

                    <div class="room-village-details">
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
                                                    : player.id ===
                                                        state.host_id
                                                      ? 'Host'
                                                      : !player.alive
                                                        ? 'Banished'
                                                        : 'Villager'
                                            }}<template
                                                v-if="
                                                    state.phase ===
                                                        'finished' &&
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
                                        >{{
                                            player.ready ? 'Ready' : 'Waiting'
                                        }}</span
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
                        <section class="game-panel ritual-panel">
                            <div class="ritual-heading">
                                <h2>
                                    {{
                                        state.phase === 'finished'
                                            ? 'Final ritual progress'
                                            : 'Ritual progress'
                                    }}
                                </h2>
                                <span
                                    v-if="
                                        state.phase !== 'lobby' &&
                                        state.ritual.threshold > 0
                                    "
                                    >{{ state.ritual.tokens }}
                                    <small
                                        >/
                                        {{ state.ritual.threshold }}
                                        steps</small
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
                                :aria-valuenow="
                                    Math.min(
                                        state.ritual.tokens,
                                        state.ritual.threshold,
                                    )
                                "
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
                                          : state.ritual.final_vote
                                            ? 'The ritual is full. Banish every remaining cultist in this final vote to stop the summoning.'
                                            : `${Math.max(0, state.ritual.threshold - state.ritual.tokens)} more steps fill the ritual. The village then gets one final discussion and vote before the summoning.`
                                }}
                            </p>
                            <p
                                v-if="
                                    !['lobby', 'finished'].includes(state.phase)
                                "
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
                    </div>
                </div>
                <aside
                    id="room-chat"
                    class="room-chat"
                    tabindex="-1"
                    aria-label="Village chat"
                >
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
                            focus challenge to read clearly again.
                        </p>
                        <div
                            ref="chatList"
                            @scroll.passive="markChatRead"
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
                                    :disabled="!canChat"
                                    :readonly="pending"
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
                </aside>
                <section
                    id="room-role"
                    class="room-role-view"
                    v-show="activeView === 'role'"
                    tabindex="-1"
                    aria-label="My role"
                >
                    <SecretRole
                        v-if="
                            state.me.role &&
                            !['lobby', 'finished'].includes(state.phase)
                        "
                        id="private-role"
                        ref="secretRole"
                        v-model="revealed"
                        :state="state"
                        :active="
                            activeView === 'role' &&
                            !puzzleCursed &&
                            !mistCursed
                        "
                        :new-results="newResults"
                        @results-read="readResults"
                    />
                    <div v-else class="game-panel room-empty">
                        <h2>
                            {{
                                state.phase === 'finished'
                                    ? 'The secrets are out'
                                    : 'Your role is waiting'
                            }}
                        </h2>
                        <p>
                            {{
                                state.phase === 'finished'
                                    ? 'Every role is now revealed in Play. Review the table and match recap.'
                                    : 'When the host starts the game, your private role will appear here.'
                            }}
                        </p>
                    </div>
                    <button
                        class="button primary return-to-play"
                        @click="navigate('play')"
                    >
                        {{
                            state.phase === 'reveal' && !state.me.submitted
                                ? 'Back to Play to get ready'
                                : 'Back to Play'
                        }}<Play :size="16" />
                    </button>
                </section>
                <section
                    id="room-help"
                    class="room-help-view"
                    v-show="activeView === 'help'"
                    tabindex="-1"
                    aria-label="Room help"
                >
                    <RoomHelp
                        :state="state"
                        @play="navigate('play')"
                        @reopen-hint="
                            hintDismissed = false;
                            navigate('play');
                        "
                    />
                </section>
            </div>
            <p class="game-bottom-note">
                Your seat stays with this browser. Refresh freely. ·
                <button class="quiet-link" @click="navigate('help')">
                    Rules & help
                </button>
            </p>
        </main>
    </div>
</template>
