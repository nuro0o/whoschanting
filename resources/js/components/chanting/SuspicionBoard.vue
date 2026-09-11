<script lang="ts">
// Preserve drafts if a room is revisited while browser storage is unavailable.
const unsavedBoards = new Map<string, SuspicionBoardData>();
</script>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Link, LockKeyhole, X } from '@lucide/vue';
import CharacterPortrait from './CharacterPortrait.vue';
import { roles, type RoomState } from '@/lib/chanting';
import { journalResultLabel } from '@/lib/personalJournal';
import { privateResultText } from '@/lib/roleActions';
import {
    decodeSuspicionBoard,
    emptySuspicionEntry,
    linkedSuspicionResults,
    MAX_SUSPICION_NOTE_LENGTH,
    serializeSuspicionBoard,
    suspicionBoardStorageKey,
    updateSuspicionEntry,
    type SuspicionBoardData,
    type SuspicionEntry,
    type SuspicionStance,
} from '@/lib/suspicionBoard';

const props = defineProps<{ state: RoomState; visible: boolean }>();
const entries = ref<SuspicionBoardData>({});
const selectedId = ref('');
const chosenResult = ref('');
const mounted = ref(false);
const storageState = ref<
    'empty' | 'saved' | 'unavailable' | 'invalid' | 'waiting'
>('waiting');
const roleIds = Object.keys(roles);
const stances: { id: SuspicionStance; label: string }[] = [
    { id: 'unmarked', label: 'Unmarked' },
    { id: 'suspicious', label: 'Suspicious' },
    { id: 'trusted', label: 'Trusted' },
];
const players = computed(() =>
    props.state.players.filter((player) => player.id !== props.state.me.id),
);
const storageKey = computed(() =>
    suspicionBoardStorageKey(
        props.state.id,
        props.state.me.id,
        props.state.match_id,
    ),
);
const editable = computed(
    () => !!storageKey.value && props.state.phase !== 'lobby' && mounted.value,
);
const selectedPlayer = computed(
    () =>
        players.value.find((player) => player.id === selectedId.value) ??
        players.value[0],
);
const selectedEntry = computed(() => entryFor(selectedPlayer.value?.id));
const linkedResults = computed(() =>
    linkedSuspicionResults(selectedEntry.value, props.state.me.results),
);
const availableResults = computed(() =>
    props.state.me.results
        .map((result, index) => ({ result, index }))
        .filter(
            ({ index }) => !selectedEntry.value.resultIndices.includes(index),
        )
        .reverse(),
);
const saveMessage = computed(
    () =>
        ({
            empty: 'Autosaves on this browser, for this match.',
            saved: 'Saved on this browser, for this match.',
            unavailable:
                'Saving unavailable. Changes stay in memory until this page closes or reloads.',
            invalid:
                'Saved board could not be read. Editing starts a new saved board.',
            waiting: 'Your suspicion board opens when a match starts.',
        })[storageState.value],
);

function entryFor(id: string | undefined): SuspicionEntry {
    return (id && entries.value[id]) || emptySuspicionEntry();
}
function seatNumber(id: string) {
    return props.state.players.findIndex((player) => player.id === id) + 1;
}
function loadBoard() {
    entries.value = {};
    selectedId.value = '';
    chosenResult.value = '';
    storageState.value = 'waiting';
    if (!mounted.value || !storageKey.value) return;
    const draft = unsavedBoards.get(storageKey.value);
    if (draft) {
        entries.value = draft;
        storageState.value = 'unavailable';
        return;
    }
    try {
        const decoded = decodeSuspicionBoard(
            localStorage.getItem(storageKey.value),
            players.value.map((player) => player.id),
            roleIds,
            props.state.me.results.length,
        );
        entries.value = decoded.entries;
        storageState.value = decoded.invalid
            ? 'invalid'
            : Object.keys(decoded.entries).length
              ? 'saved'
              : 'empty';
    } catch {
        storageState.value = 'unavailable';
    }
}
function update(patch: Partial<SuspicionEntry>) {
    const player = selectedPlayer.value;
    if (!editable.value || !storageKey.value || !player) return;
    entries.value = updateSuspicionEntry(entries.value, player.id, patch);
    try {
        localStorage.setItem(
            storageKey.value,
            serializeSuspicionBoard(entries.value),
        );
        unsavedBoards.delete(storageKey.value);
        storageState.value = 'saved';
    } catch {
        unsavedBoards.set(storageKey.value, entries.value);
        storageState.value = 'unavailable';
    }
}
function changeClaim(event: Event) {
    const role = (event.target as HTMLSelectElement).value;
    update({ claimedRole: roleIds.includes(role) ? role : null });
}
function changeNote(event: Event) {
    update({
        note: (event.target as HTMLTextAreaElement).value.slice(
            0,
            MAX_SUSPICION_NOTE_LENGTH,
        ),
    });
}
function attachResult() {
    if (chosenResult.value === '') return;
    const index = Number(chosenResult.value);
    if (!availableResults.value.some((entry) => entry.index === index)) return;
    update({ resultIndices: [...selectedEntry.value.resultIndices, index] });
    chosenResult.value = '';
}
function removeResult(index: number) {
    update({
        resultIndices: selectedEntry.value.resultIndices.filter(
            (linked) => linked !== index,
        ),
    });
}
watch(storageKey, loadBoard, { flush: 'sync' });
watch(
    () => selectedPlayer.value?.id,
    () => {
        chosenResult.value = '';
    },
);
onMounted(() => {
    mounted.value = true;
    loadBoard();
});
</script>

<template>
    <div v-if="visible" id="journal-suspicions" class="suspicion-board">
        <header class="board-heading">
            <h3>Your suspicion board</h3>
            <p>
                Your judgments, not confirmed roles. Select a player to collect
                your thoughts.
            </p>
        </header>
        <p
            class="board-storage"
            :class="{
                'board-warning':
                    storageState === 'unavailable' ||
                    storageState === 'invalid',
            }"
            role="status"
        >
            <LockKeyhole :size="13" aria-hidden="true" /> {{ saveMessage }}
        </p>
        <div v-if="editable && players.length" class="board-layout">
            <nav
                class="board-roster"
                aria-label="Players on your suspicion board"
            >
                <button
                    v-for="player in players"
                    :key="player.id"
                    type="button"
                    class="board-player"
                    :aria-pressed="selectedPlayer?.id === player.id"
                    aria-controls="suspicion-detail"
                    @click="selectedId = player.id"
                >
                    <CharacterPortrait
                        :character="player.character"
                        :frame="player.customization?.frame"
                        :accent="player.customization?.accent"
                        :background="player.customization?.background"
                        decorative
                    />
                    <span class="board-player-copy">
                        <strong>{{ player.name }}</strong>
                        <span class="board-seat"
                            >Seat {{ seatNumber(player.id) }} ·
                            {{ player.alive ? 'Alive' : 'Banished' }}</span
                        >
                        <span
                            class="board-mark"
                            :class="`mark-${entryFor(player.id).stance}`"
                            >{{
                                stances.find(
                                    (stance) =>
                                        stance.id ===
                                        entryFor(player.id).stance,
                                )?.label
                            }}</span
                        >
                        <span
                            v-if="entryFor(player.id).claimedRole"
                            class="board-claim"
                            >Claims
                            {{
                                roles[entryFor(player.id).claimedRole!]?.name
                            }}</span
                        >
                    </span>
                </button>
            </nav>
            <section
                v-if="selectedPlayer"
                id="suspicion-detail"
                class="board-detail"
                aria-labelledby="suspicion-player-name"
            >
                <header class="board-detail-heading">
                    <p class="board-kicker">
                        YOUR PRIVATE READ · SEAT
                        {{ seatNumber(selectedPlayer.id) }}
                    </p>
                    <h4 id="suspicion-player-name">
                        {{ selectedPlayer.name }}
                    </h4>
                    <p>
                        {{
                            selectedPlayer.alive
                                ? 'Still at the table'
                                : 'Banished from the table'
                        }}
                    </p>
                </header>
                <fieldset class="board-stance">
                    <legend>Your judgment</legend>
                    <div>
                        <button
                            v-for="stance in stances"
                            :key="stance.id"
                            type="button"
                            :class="`mark-${stance.id}`"
                            :aria-pressed="selectedEntry.stance === stance.id"
                            @click="update({ stance: stance.id })"
                        >
                            {{ stance.label }}
                        </button>
                    </div>
                </fieldset>
                <label class="board-label" for="suspicion-claim"
                    >Claimed role</label
                >
                <select
                    id="suspicion-claim"
                    :value="selectedEntry.claimedRole ?? ''"
                    aria-describedby="suspicion-claim-help"
                    @change="changeClaim"
                >
                    <option value="">No claim</option>
                    <option v-for="role in roleIds" :key="role" :value="role">
                        {{ roles[role].name }}
                    </option>
                </select>
                <p id="suspicion-claim-help" class="board-help">
                    A claim you record, never a revealed role.
                </p>
                <label class="board-label" for="suspicion-note"
                    >Notes about {{ selectedPlayer.name }}</label
                >
                <textarea
                    id="suspicion-note"
                    :value="selectedEntry.note"
                    :maxlength="MAX_SUSPICION_NOTE_LENGTH"
                    rows="4"
                    placeholder="What did they claim? Does their story add up?"
                    aria-describedby="suspicion-note-help"
                    @input="changeNote"
                ></textarea>
                <p id="suspicion-note-help" class="board-note-count">
                    Private to this browser
                    <span
                        >{{ selectedEntry.note.length }}/{{
                            MAX_SUSPICION_NOTE_LENGTH
                        }}</span
                    >
                </p>
                <div class="board-evidence-heading">
                    <h5>Linked clues</h5>
                    <span>{{ linkedResults.length }}</span>
                </div>
                <p class="board-help">
                    You choose the connections. A linked result does not prove
                    allegiance.
                </p>
                <template v-if="state.me.results.length">
                    <form
                        v-if="availableResults.length"
                        class="board-attach"
                        @submit.prevent="attachResult"
                    >
                        <label class="board-label" for="suspicion-result"
                            >Attach one of your results</label
                        >
                        <select id="suspicion-result" v-model="chosenResult">
                            <option value="">Choose a clue…</option>
                            <option
                                v-for="entry in availableResults"
                                :key="entry.index"
                                :value="String(entry.index)"
                            >
                                Round {{ entry.result.day }} ·
                                {{ journalResultLabel(entry.result)
                                }}{{
                                    entry.result.target
                                        ? ` · ${entry.result.target}`
                                        : ''
                                }}
                            </option>
                        </select>
                        <p
                            v-if="
                                chosenResult !== '' &&
                                state.me.results[Number(chosenResult)]
                            "
                            class="board-result-preview"
                        >
                            {{
                                privateResultText(
                                    state.me.results[Number(chosenResult)],
                                )
                            }}
                        </p>
                        <button
                            class="board-attach-button"
                            type="submit"
                            :disabled="chosenResult === ''"
                        >
                            <Link :size="14" aria-hidden="true" /> Attach clue
                        </button>
                    </form>
                    <ol class="board-evidence">
                        <li v-for="entry in linkedResults" :key="entry.index">
                            <div class="board-evidence-top">
                                <span
                                    >ROUND {{ entry.result.day }} ·
                                    {{ journalResultLabel(entry.result) }}</span
                                ><button
                                    type="button"
                                    :aria-label="`Remove linked ${journalResultLabel(entry.result)} from round ${entry.result.day}${entry.result.target ? ` about ${entry.result.target}` : ''}`"
                                    @click="removeResult(entry.index)"
                                >
                                    <X :size="16" aria-hidden="true" />
                                </button>
                            </div>
                            <h6 v-if="entry.result.target">
                                {{ entry.result.target }}
                            </h6>
                            <p>{{ privateResultText(entry.result) }}</p>
                        </li>
                    </ol>
                    <p
                        v-if="
                            state.me.results.some(
                                (result) =>
                                    !result.kind || result.kind === 'alignment',
                            )
                        "
                        class="board-caveat"
                    >
                        Oracle readings can be veiled. An apparent alignment is
                        not proof of a player's true alignment.
                    </p>
                </template>
                <p v-else class="board-help board-no-results">
                    Your ability results will be available here.
                </p>
            </section>
        </div>
        <p v-else-if="editable" class="board-help">
            Other players will appear here when they join the table.
        </p>
    </div>
</template>

<style scoped>
.suspicion-board {
    color: #eee8da;
    min-width: 0;
    container-type: inline-size;
}
.board-heading h3 {
    margin: 0;
    font:
        500 24px/1.3 'Fraunces',
        Georgia,
        serif;
}
.board-heading p,
.board-help {
    color: #becbc2;
    font-size: 13px;
    line-height: 1.65;
    margin: 9px 0 14px;
}
.board-storage {
    display: flex;
    align-items: baseline;
    gap: 6px;
    color: #c2ceb8;
    font-size: 11px;
    line-height: 1.6;
    margin: 0 0 20px;
}
.board-storage svg {
    flex: 0 0 auto;
    align-self: center;
}
.board-warning {
    color: #f0c49c;
}
.board-layout {
    display: grid;
    grid-template-columns: minmax(170px, 0.8fr) minmax(0, 1.6fr);
    gap: 24px;
    align-items: start;
}
.board-roster {
    display: grid;
    gap: 5px;
    min-width: 0;
}
.board-player {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 88px;
    padding: 12px 9px;
    border: 1px solid transparent;
    border-left: 2px solid transparent;
    border-radius: 2px;
    background: transparent;
    color: #ede9de;
    text-align: left;
    cursor: pointer;
}
.board-player:hover {
    background: #ddc3980b;
}
.board-player[aria-pressed='true'] {
    background: #ddc39810;
    border-color: #ddc39842;
    border-left-color: #ddc398;
}
.board-player .character-portrait {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
}
.board-player-copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    overflow-wrap: anywhere;
}
.board-player-copy strong {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
}
.board-seat,
.board-claim {
    font-size: 11px;
    color: #b7c5be;
    line-height: 1.5;
}
.board-mark {
    font-size: 11px;
    line-height: 1.4;
    font-weight: 600;
}
.mark-unmarked {
    color: #c0c9c2;
}
.mark-suspicious {
    color: #f0b09b;
}
.mark-trusted {
    color: #b8d4b0;
}
.board-detail {
    min-width: 0;
    padding-left: 24px;
    border-left: 1px solid #ddc39835;
}
.board-detail-heading {
    border-bottom: 1px solid #c6cec526;
    padding-bottom: 16px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
}
.board-kicker {
    color: #ddc398;
    font-size: 10px;
    letter-spacing: 0.11em;
    margin: 0 0 9px;
}
.board-detail-heading h4 {
    font:
        500 28px/1.25 'Fraunces',
        Georgia,
        serif;
    margin: 0 0 7px;
}
.board-detail-heading > p:last-child {
    color: #b7c5be;
    font-size: 12px;
    margin: 0;
}
.board-stance {
    padding: 0;
    border: 0;
    margin: 0 0 20px;
    min-width: 0;
}
.board-label,
.board-stance legend {
    display: block;
    color: #eee7d9;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 9px;
}
.board-stance > div {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.board-stance button {
    min-height: 44px;
    flex: 1 1 auto;
    padding: 9px 10px;
    background: #112023;
    border: 1px solid #52615a;
    border-radius: 3px;
    font-size: 12px;
    cursor: pointer;
}
.board-stance button[aria-pressed='true'] {
    border-color: currentColor;
    background: #ddc39817;
    box-shadow: inset 0 -2px currentColor;
}
.suspicion-board select,
.suspicion-board textarea {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    min-height: 44px;
    background: #112023;
    color: #f3eddf;
    border: 1px solid #6f7665;
    border-radius: 3px;
    padding: 10px 12px;
    font: inherit;
    font-size: 16px;
    color-scheme: dark;
}
.suspicion-board textarea {
    line-height: 1.7;
    resize: vertical;
    min-height: 120px;
    max-height: 45dvh;
}
.suspicion-board textarea::placeholder {
    color: #9eafa7;
}
.board-note-count {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #b7c5be;
    font-size: 10px;
    line-height: 1.6;
    margin: 8px 0 24px;
}
.board-note-count span {
    white-space: nowrap;
}
.board-evidence-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}
.board-evidence-heading h5 {
    margin: 0;
    font:
        500 20px/1.3 'Fraunces',
        Georgia,
        serif;
}
.board-evidence-heading > span {
    color: #b7c5be;
    font-size: 12px;
}
.board-attach {
    margin: 16px 0 20px;
}
.board-attach .board-label {
    font-size: 12px;
}
.board-attach-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 44px;
    margin-top: 10px;
    padding: 10px 14px;
    background: #ddc398;
    color: #182627;
    border: 1px solid #ddc398;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.board-attach-button:disabled {
    opacity: 0.5;
    cursor: default;
}
.board-attach-button:not(:disabled):hover {
    background: #efdab9;
}
.board-result-preview {
    font-size: 13px;
    line-height: 1.7;
    color: #dce0d6;
    margin: 12px 0 0;
    overflow-wrap: anywhere;
}
.board-evidence {
    list-style: none;
    padding: 0;
    margin: 0;
}
.board-evidence li {
    border-left: 2px solid #aa937056;
    padding: 0 0 16px 12px;
    margin: 0 0 16px;
    border-bottom: 1px solid #c6cec526;
    overflow-wrap: anywhere;
}
.board-evidence-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.board-evidence-top span {
    color: #ddc398;
    font-size: 10px;
    line-height: 1.6;
    letter-spacing: 0.03em;
}
.board-evidence-top button {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    background: transparent;
    border: 0;
    color: #c6cec5;
    cursor: pointer;
}
.board-evidence-top button:hover {
    color: #f0b09b;
    background: #ddc3980b;
}
.board-evidence h6 {
    font:
        500 19px/1.3 'Fraunces',
        Georgia,
        serif;
    margin: 2px 0 8px;
}
.board-evidence li > p {
    color: #e5e5d9;
    font-size: 13px;
    line-height: 1.75;
    margin: 0;
}
.board-caveat {
    border-left: 2px solid #ddc398;
    padding-left: 12px;
    color: #c7d0c5;
    font-size: 12px;
    line-height: 1.7;
    margin: 20px 0 0;
}
.board-no-results {
    padding: 14px 0;
    border-top: 1px solid #c6cec526;
}
.suspicion-board button:focus-visible,
.suspicion-board select:focus-visible,
.suspicion-board textarea:focus-visible {
    outline: 2px solid #f0d4a4;
    outline-offset: 3px;
}
@container (max-width: 540px) {
    .board-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 22px;
    }
    .board-roster {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        max-height: 280px;
        overflow-y: auto;
        padding: 4px;
        margin: -4px;
        scrollbar-width: thin;
    }
    .board-player {
        padding: 10px 6px;
        gap: 9px;
    }
    .board-player .character-portrait {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
    }
    .board-player-copy strong {
        font-size: 13px;
    }
    .board-detail {
        padding: 22px 0 0;
        border-left: 0;
        border-top: 1px solid #ddc39835;
    }
}
@container (max-width: 310px) {
    .board-player {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
