<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Eye,
    Globe,
    KeyRound,
    LoaderCircle,
    Plus,
    RefreshCw,
    Users,
} from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { roomRequest } from '@/lib/chanting';
import { modeName, type ModeSetup } from '@/lib/gameModes';
import '../../css/chanting.css';

interface PublicRoom {
    code: string;
    host_name: string;
    player_count: number;
    capacity: number;
    pin_required: boolean;
    mode_setup: ModeSetup;
}
interface RoomListing {
    rooms: PublicRoom[];
    page: number;
    has_more: boolean;
}
const rooms = ref<PublicRoom[]>([]);
const page = ref(1);
const hasMore = ref(false);
const pending = ref(true);
const loaded = ref(false);
const error = ref('');
let requestId = 0;

async function loadRooms(nextPage = page.value) {
    const currentRequest = ++requestId;
    pending.value = true;
    error.value = '';
    try {
        const listing = await roomRequest<RoomListing>(
            `/rooms/public?page=${nextPage}`,
        );
        if (currentRequest !== requestId) return;
        rooms.value = listing.rooms;
        page.value = listing.page;
        hasMore.value = listing.has_more;
        loaded.value = true;
    } catch (cause) {
        if (currentRequest !== requestId) return;
        error.value =
            cause instanceof Error
                ? cause.message
                : 'The room browser could not be loaded. Try again.';
    } finally {
        if (currentRequest === requestId) pending.value = false;
    }
}
onMounted(() => void loadRooms());
onUnmounted(() => {
    requestId++;
});
</script>

<template>
    <Head title="Room browser" />
    <div class="chanting browser-page">
        <header class="site-header">
            <a href="/" class="wordmark" aria-label="Who's Chanting? home">
                <span class="brand-eye"><Eye :size="26" /></span>
                who’s chanting<span class="brand-question">?</span>
            </a>
            <nav aria-label="Main navigation">
                <a href="/tutorial" class="quiet-link">Practice solo</a>
                <a
                    v-if="$page.props.auth.user"
                    href="/dashboard"
                    class="quiet-link"
                    >My account</a
                >
                <a v-else href="/login" class="quiet-link">Sign in</a>
            </nav>
        </header>
        <main class="browser-main">
            <header class="browser-heading">
                <div>
                    <p class="eyebrow">
                        <Globe :size="15" aria-hidden="true" /> THE VILLAGE
                        NOTICEBOARD
                    </p>
                    <h1>Find your next<br /><em>gathering.</em></h1>
                    <p class="browser-intro">
                        A few empty seats. Plenty of suspects.<br />Join a
                        public room and meet the village.
                    </p>
                </div>
                <aside class="browser-host-note" aria-label="Host a room">
                    <p class="eyebrow">YOUR TABLE, YOUR COMPANY</p>
                    <h2>Bring your own secrets.</h2>
                    <p>
                        Welcome new faces with a public room, or keep your
                        gathering private.
                    </p>
                    <a
                        :href="$page.props.auth.user ? '/dashboard' : '/'"
                        class="button primary"
                        ><Plus :size="17" aria-hidden="true" /> Create a room</a
                    >
                </aside>
            </header>

            <section
                class="browser-board"
                aria-labelledby="gatherings-title"
                :aria-busy="pending"
            >
                <div class="browser-toolbar">
                    <div>
                        <h2 id="gatherings-title">Public rooms</h2>
                        <p>Waiting for the game to begin.</p>
                    </div>
                    <button
                        type="button"
                        class="button browser-refresh"
                        :disabled="pending"
                        @click="loadRooms()"
                    >
                        <RefreshCw
                            :size="16"
                            :class="{ spin: pending }"
                            aria-hidden="true"
                        />
                        {{ pending ? 'Refreshing…' : 'Refresh' }}
                    </button>
                </div>
                <p class="browser-access-note">
                    <KeyRound :size="15" aria-hidden="true" /> Rooms marked “PIN
                    required” are public. Ask the host for the PIN to join.
                </p>
                <div v-if="error" class="browser-error" role="alert">
                    <p>{{ error }}</p>
                    <button
                        type="button"
                        class="button"
                        :disabled="pending"
                        @click="loadRooms()"
                    >
                        Try again
                    </button>
                </div>
                <div
                    v-if="pending && !loaded"
                    class="browser-empty"
                    role="status"
                >
                    <LoaderCircle :size="27" class="spin" aria-hidden="true" />
                    <h3>Looking for a gathering…</h3>
                    <p>Checking the village noticeboard.</p>
                </div>
                <div
                    v-else-if="loaded && rooms.length === 0"
                    class="browser-empty"
                    role="status"
                >
                    <Users :size="30" :stroke-width="1.3" aria-hidden="true" />
                    <h3>
                        {{
                            page > 1
                                ? 'No more gatherings here.'
                                : 'The village is quiet. For now.'
                        }}
                    </h3>
                    <p>
                        {{
                            page > 1
                                ? 'Go back a page or refresh to find a room.'
                                : 'No public rooms are waiting. Create a public room and give the next suspect a place to sit.'
                        }}
                    </p>
                    <a
                        v-if="page === 1"
                        :href="$page.props.auth.user ? '/dashboard' : '/'"
                        class="quiet-link"
                        >Start a gathering
                        <ArrowRight :size="16" aria-hidden="true"
                    /></a>
                </div>
                <ul v-else-if="rooms.length" class="browser-room-list">
                    <li
                        v-for="room in rooms"
                        :key="room.code"
                        class="browser-room"
                    >
                        <div class="browser-room-heading">
                            <span class="browser-room-code">{{
                                room.code
                            }}</span>
                            <h3>{{ room.host_name }}’s room</h3>
                            <p>{{ modeName(room.mode_setup) }}</p>
                        </div>
                        <span
                            class="browser-room-access"
                            :class="{ 'has-pin': room.pin_required }"
                        >
                            <KeyRound
                                v-if="room.pin_required"
                                :size="15"
                                aria-hidden="true"
                            />
                            <Globe v-else :size="15" aria-hidden="true" />
                            {{ room.pin_required ? 'PIN required' : 'No PIN' }}
                        </span>
                        <span class="browser-occupancy"
                            ><Users :size="17" aria-hidden="true" /><strong
                                >{{ room.player_count }} /
                                {{ room.capacity }}</strong
                            ><span>players</span></span
                        >
                        <span
                            v-if="room.player_count >= room.capacity"
                            class="browser-full"
                            >Full</span
                        >
                        <a
                            v-else
                            :href="`/rooms/${encodeURIComponent(room.code)}`"
                            class="button browser-join"
                            :aria-label="`Join ${room.host_name}’s room ${room.code}${room.pin_required ? ', PIN required' : ''}`"
                            >Join room
                            <ArrowRight :size="16" aria-hidden="true"
                        /></a>
                    </li>
                </ul>
                <nav
                    v-if="loaded && (page > 1 || hasMore)"
                    class="browser-pagination"
                    aria-label="Room browser pages"
                >
                    <button
                        type="button"
                        class="button"
                        :disabled="pending || page === 1"
                        @click="loadRooms(page - 1)"
                    >
                        <ArrowLeft :size="16" aria-hidden="true" /> Previous
                    </button>
                    <span role="status">Page {{ page }}</span>
                    <button
                        type="button"
                        class="button"
                        :disabled="pending || !hasMore"
                        @click="loadRooms(page + 1)"
                    >
                        Next <ArrowRight :size="16" aria-hidden="true" />
                    </button>
                </nav>
            </section>
            <footer class="browser-footer">
                <p>
                    Private rooms never appear here. Have an invite? Join with
                    your room code.
                </p>
                <a
                    :href="$page.props.auth.user ? '/dashboard' : '/'"
                    class="quiet-link"
                    >Join friends <ArrowRight :size="15" aria-hidden="true"
                /></a>
            </footer>
        </main>
    </div>
</template>

<style scoped>
.browser-main {
    width: min(1120px, 88%);
    margin: 0 auto;
    padding: 64px 0 48px;
}
.browser-heading {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    align-items: center;
    gap: 70px;
    margin-bottom: 55px;
}
.browser-heading h1 {
    margin: 14px 0 20px;
    font-size: clamp(44px, 5.7vw, 72px);
    line-height: 1.04;
    letter-spacing: -2px;
}
.browser-intro {
    color: var(--muted);
    font-size: 16px;
}
.browser-host-note {
    padding: 27px 0 27px 30px;
    border-left: 1px solid var(--line);
}
.browser-host-note h2 {
    margin: 12px 0 10px;
    font-size: 27px;
}
.browser-host-note > p:not(.eyebrow) {
    max-width: 290px;
    color: var(--muted);
}
.browser-host-note .button {
    margin-top: 22px;
}
.browser-board {
    border: 1px solid var(--line);
    border-top: 3px solid var(--green);
    background: var(--panel);
}
.browser-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 24px 28px 18px;
}
.browser-toolbar h2 {
    font-size: 28px;
}
.browser-toolbar p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 4px;
}
.browser-refresh {
    min-width: 125px;
}
.browser-access-note {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 28px 22px;
    color: var(--muted);
    font-size: 12px;
}
.browser-access-note svg {
    flex-shrink: 0;
    color: var(--green);
}
.browser-room-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.browser-room {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 120px 115px 128px;
    gap: 24px;
    align-items: center;
    padding: 23px 28px;
    border-top: 1px solid var(--line);
    transition: background 0.15s;
}
.browser-room:hover {
    background: #bdcd9c06;
}
.browser-room-code {
    font-size: 10px;
    color: var(--green);
    letter-spacing: 2px;
}
.browser-room-heading h3 {
    margin: 3px 0;
    font-size: 23px;
    overflow-wrap: anywhere;
}
.browser-room-heading p {
    color: var(--muted);
    font-size: 12px;
}
.browser-room-access {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: var(--muted);
}
.browser-room-access.has-pin {
    color: var(--coral);
}
.browser-occupancy {
    display: grid;
    grid-template-columns: 17px auto;
    gap: 0 9px;
    align-items: center;
    color: var(--muted);
    font-size: 11px;
}
.browser-occupancy strong {
    color: var(--cream);
    font-size: 15px;
    font-weight: 500;
}
.browser-occupancy > span {
    grid-column: 2;
}
.browser-join {
    padding-inline: 14px;
    color: var(--green);
    border-color: #bdcd9c70;
    white-space: nowrap;
}
.browser-join:hover {
    background: #bdcd9c12;
}
.browser-full {
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    padding: 12px;
    border: 1px dashed var(--line);
    border-radius: 4px;
}
.browser-empty {
    display: grid;
    justify-items: center;
    gap: 12px;
    padding: 58px 24px 64px;
    border-top: 1px solid var(--line);
    text-align: center;
}
.browser-empty > svg {
    color: var(--green);
}
.browser-empty h3 {
    font-size: 28px;
}
.browser-empty p {
    max-width: 390px;
    color: var(--muted);
}
.browser-empty a {
    margin-top: 6px;
    color: var(--green);
}
.browser-error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 28px;
    border-top: 1px solid var(--line);
    color: var(--coral);
}
.browser-error button {
    flex-shrink: 0;
}
.browser-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    border-top: 1px solid var(--line);
    padding: 20px 28px;
}
.browser-pagination > span {
    color: var(--muted);
    font-size: 12px;
}
.browser-footer {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 0;
    color: var(--muted);
    font-size: 12px;
}
.browser-footer a {
    flex-shrink: 0;
    color: var(--green);
}
@media (max-width: 900px) {
    .browser-heading {
        gap: 32px;
    }
    .browser-room {
        grid-template-columns: minmax(0, 1fr) 110px 128px;
        gap: 15px;
    }
    .browser-room-heading {
        grid-row: span 2;
    }
    .browser-room-access {
        grid-column: 2;
        grid-row: 2;
    }
    .browser-occupancy {
        grid-column: 2;
        grid-row: 1;
    }
    .browser-join,
    .browser-full {
        grid-column: 3;
        grid-row: span 2;
    }
}
@media (max-width: 600px) {
    .browser-page .site-header {
        height: auto;
        min-height: 100px;
        flex-wrap: wrap;
        gap: 14px;
        padding-block: 18px;
    }
    .browser-page .site-header nav {
        gap: 22px;
    }
    .browser-main {
        padding-top: 36px;
    }
    .browser-heading {
        grid-template-columns: 1fr;
        gap: 26px;
        margin-bottom: 32px;
    }
    .browser-heading h1 {
        font-size: 50px;
    }
    .browser-host-note {
        padding: 6px 0 6px 20px;
    }
    .browser-host-note h2 {
        font-size: 24px;
    }
    .browser-host-note .button {
        margin-top: 16px;
    }
    .browser-toolbar {
        padding: 20px 18px 15px;
    }
    .browser-toolbar h2 {
        font-size: 25px;
    }
    .browser-refresh {
        min-width: auto;
        padding-inline: 12px;
    }
    .browser-access-note {
        padding: 0 18px 20px;
        align-items: flex-start;
    }
    .browser-access-note svg {
        margin-top: 2px;
    }
    .browser-room {
        grid-template-columns: minmax(0, 1fr) auto;
        padding: 20px 18px;
        gap: 16px 12px;
    }
    .browser-room-heading {
        grid-column: 1;
        grid-row: 1;
    }
    .browser-room-heading h3 {
        font-size: 21px;
    }
    .browser-occupancy {
        grid-column: 2;
        grid-row: 1;
    }
    .browser-room-access {
        grid-column: 1;
        grid-row: 2;
    }
    .browser-join,
    .browser-full {
        grid-column: 2;
        grid-row: 2;
    }
    .browser-pagination {
        padding: 18px 12px;
        gap: 6px;
    }
    .browser-pagination .button {
        padding-inline: 10px;
    }
    .browser-footer {
        flex-direction: column;
        gap: 10px;
    }
    .browser-error {
        padding-inline: 18px;
        flex-wrap: wrap;
    }
}
@media (prefers-reduced-motion: reduce) {
    .browser-room {
        transition: none;
    }
    .spin {
        animation: none;
    }
}
</style>
