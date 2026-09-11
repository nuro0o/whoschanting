<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    type ComponentPublicInstance,
} from 'vue';
import {
    BookOpen,
    Eye,
    EyeOff,
    LockKeyhole,
    PencilLine,
    Users,
} from '@lucide/vue';
import SuspicionBoard from './SuspicionBoard.vue';
import type { RoomState } from '@/lib/chanting';
import { privateResultText } from '@/lib/roleActions';
import {
    groupJournalResults,
    journalResultLabel,
    journalStorageKey,
    MAX_JOURNAL_NOTE_LENGTH,
} from '@/lib/personalJournal';

const props = defineProps<{
    state: RoomState;
    active: boolean;
    blocked: boolean;
    newResults: number;
}>();
const revealed = defineModel<boolean>({ default: false });
const emit = defineEmits<{ 'results-read': []; play: [] }>();
const tab = ref<'results' | 'notes' | 'suspicions'>('results');
const notes = ref('');
const storageState = ref<'saved' | 'empty' | 'unavailable' | 'waiting'>(
    'empty',
);
const revealButton = ref<HTMLButtonElement>();
const resultsHeading = ref<HTMLElement>();
const newestOutcome = ref<HTMLElement>();
const resultsVisible = ref(false);
const groups = computed(() => groupJournalResults(props.state.me.results));
const storageKey = computed(() =>
    journalStorageKey(props.state.id, props.state.me.id, props.state.match_id),
);
let observer: IntersectionObserver | undefined;

function loadNotes() {
    notes.value = '';
    if (!storageKey.value) {
        storageState.value = 'waiting';
        return;
    }
    try {
        notes.value = (localStorage.getItem(storageKey.value) ?? '').slice(
            0,
            MAX_JOURNAL_NOTE_LENGTH,
        );
        storageState.value = notes.value ? 'saved' : 'empty';
    } catch {
        storageState.value = 'unavailable';
    }
}
watch(storageKey, loadNotes, { immediate: true, flush: 'sync' });
function saveNotes(event: Event) {
    notes.value = (event.target as HTMLTextAreaElement).value.slice(
        0,
        MAX_JOURNAL_NOTE_LENGTH,
    );
    if (!storageKey.value) return;
    try {
        localStorage.setItem(storageKey.value, notes.value);
        storageState.value = 'saved';
    } catch {
        storageState.value = 'unavailable';
    }
}
const saveMessage = computed(
    () =>
        ({
            saved: 'Saved on this browser',
            empty: 'Autosaves on this browser',
            unavailable:
                'Saving unavailable. Keep this page open; changes may be lost.',
            waiting: 'Notes are available once a match starts.',
        })[storageState.value],
);

function acknowledgeResults() {
    if (
        resultsVisible.value &&
        props.active &&
        !props.blocked &&
        revealed.value &&
        tab.value === 'results' &&
        document.visibilityState === 'visible'
    )
        emit('results-read');
}
function trackNewestOutcome(element: Element | ComponentPublicInstance | null) {
    newestOutcome.value = element instanceof HTMLElement ? element : undefined;
}
watch(newestOutcome, (element) => {
    observer?.disconnect();
    resultsVisible.value = false;
    if (!element) return;
    observer = new IntersectionObserver(
        ([entry]) => {
            resultsVisible.value =
                entry.isIntersecting && entry.intersectionRatio >= 0.5;
            acknowledgeResults();
        },
        { threshold: 0.5 },
    );
    observer.observe(element);
});
watch(
    [() => props.active, () => props.blocked, revealed, tab],
    acknowledgeResults,
    { flush: 'post' },
);
async function showResults() {
    tab.value = 'results';
    await nextTick();
    resultsHeading.value?.focus({ preventScroll: true });
}
async function hideSecrets() {
    revealed.value = false;
    await nextTick();
    revealButton.value?.focus({ preventScroll: true });
}
defineExpose({ showResults });
onMounted(() =>
    document.addEventListener('visibilitychange', acknowledgeResults),
);
onBeforeUnmount(() => {
    observer?.disconnect();
    document.removeEventListener('visibilitychange', acknowledgeResults);
});
</script>

<template>
    <section
        class="game-panel personal-journal"
        aria-label="Your private journal"
    >
        <header class="journal-heading">
            <p class="eyebrow"><LockKeyhole :size="12" /> YOUR EYES ONLY</p>
            <div class="journal-title-row">
                <h2>Table journal</h2>
                <button
                    v-if="revealed && !blocked"
                    class="quiet-link"
                    @click="hideSecrets"
                >
                    <EyeOff :size="14" /> Hide secrets
                </button>
            </div>
            <p>Your discoveries. Your suspicions.</p>
        </header>
        <div v-if="blocked" class="journal-empty">
            <LockKeyhole :size="24" />
            <h3>Your journal is out of reach</h3>
            <p>Clear your curse to return to your results and notes.</p>
            <button class="button" @click="emit('play')">Back to Play</button>
        </div>
        <div v-else-if="!revealed" class="journal-empty">
            <BookOpen :size="28" />
            <h3>A little privacy first.</h3>
            <p>
                Ability results and your notes stay hidden until you reveal your
                secrets.
            </p>
            <button ref="revealButton" class="button" @click="revealed = true">
                <Eye :size="15" /> Reveal my journal
            </button>
        </div>
        <template v-else>
            <div class="journal-tabs" role="group" aria-label="Journal pages">
                <button
                    :aria-pressed="tab === 'results'"
                    :aria-controls="
                        tab === 'results' ? 'journal-results' : undefined
                    "
                    @click="tab = 'results'"
                >
                    <BookOpen :size="16" /> Results
                    <span
                        v-if="newResults"
                        class="room-badge"
                        :aria-label="`${newResults} new results`"
                        >{{ newResults }}</span
                    >
                </button>
                <button
                    :aria-pressed="tab === 'notes'"
                    :aria-controls="
                        tab === 'notes' ? 'journal-notes' : undefined
                    "
                    @click="tab = 'notes'"
                >
                    <PencilLine :size="16" /> My notes
                </button>
                <button
                    :aria-pressed="tab === 'suspicions'"
                    :aria-controls="
                        tab === 'suspicions' && active
                            ? 'journal-suspicions'
                            : undefined
                    "
                    @click="tab = 'suspicions'"
                >
                    <Users :size="16" /> Suspicions
                </button>
            </div>
            <div
                v-if="tab === 'results'"
                id="journal-results"
                class="journal-results"
            >
                <div
                    ref="resultsHeading"
                    class="journal-section-heading"
                    tabindex="-1"
                >
                    <h3>Ability results</h3>
                    <span>{{ state.me.results.length }} recorded</span>
                </div>
                <div v-if="!groups.length" class="journal-empty">
                    <BookOpen :size="25" />
                    <h3>No discoveries yet.</h3>
                    <p>
                        When an ability gives you a private result, it appears
                        here. Return any time to compare rounds.
                    </p>
                </div>
                <section
                    v-for="group in groups"
                    :key="group.day"
                    class="journal-round"
                    :aria-label="`Round ${group.day}`"
                >
                    <h4>
                        <span>ROUND {{ group.day }}</span
                        ><span class="journal-round-line"></span>
                    </h4>
                    <ol>
                        <li
                            v-for="entry in group.entries"
                            :key="entry.index"
                            class="journal-entry"
                        >
                            <p class="journal-result-type">
                                {{ journalResultLabel(entry.result) }}
                            </p>
                            <h5 v-if="entry.result.target">
                                {{ entry.result.target }}
                            </h5>
                            <p
                                :ref="
                                    entry.index === state.me.results.length - 1
                                        ? trackNewestOutcome
                                        : undefined
                                "
                                class="journal-outcome"
                            >
                                {{ privateResultText(entry.result) }}
                            </p>
                        </li>
                    </ol>
                </section>
                <p
                    v-if="
                        state.me.results.some(
                            (result) =>
                                !result.kind || result.kind === 'alignment',
                        )
                    "
                    class="journal-caveat"
                >
                    Oracle readings can be veiled. An apparent alignment is not
                    proof of a player's true alignment.
                </p>
                <p
                    v-if="
                        state.me.results.some(
                            (result) => result.kind === 'visits',
                        )
                    "
                    class="journal-caveat"
                >
                    Visits can be friendly or hostile. Veils do not change a
                    Lamplighter's observations, and your own watch is excluded.
                </p>
                <p
                    v-if="
                        state.me.results.some(
                            (result) => result.kind === 'tracking',
                        )
                    "
                    class="journal-caveat"
                >
                    Tracking records visible outgoing visits. A hidden visit may
                    not appear.
                </p>
                <p
                    v-if="
                        state.me.results.some(
                            (result) => result.kind === 'protection',
                        )
                    "
                    class="journal-caveat"
                >
                    Protection records whom you guarded, not whether a curse was
                    attempted. Veils still affect Oracle readings.
                </p>
            </div>
            <div
                v-else-if="tab === 'notes'"
                id="journal-notes"
                class="journal-notes"
            >
                <label for="journal-notes-input">Your private notes</label>
                <p id="journal-notes-help">
                    Keep track of claims, checks, and who you trust. Only stored
                    on this browser, for this match.
                </p>
                <textarea
                    id="journal-notes-input"
                    :value="notes"
                    :maxlength="MAX_JOURNAL_NOTE_LENGTH"
                    :disabled="!storageKey"
                    rows="12"
                    aria-describedby="journal-notes-help journal-notes-status"
                    placeholder="Round 1 — Who did I check?&#10;Mara claims Warden. Ask who she protected.&#10;Compare stories before the next vote…"
                    spellcheck="true"
                    @input="saveNotes"
                ></textarea>
                <div class="journal-save-row">
                    <span
                        id="journal-notes-status"
                        role="status"
                        :class="{
                            'save-unavailable': storageState === 'unavailable',
                        }"
                        >{{ saveMessage }}</span
                    >
                    <span
                        >{{ notes.length }}/{{ MAX_JOURNAL_NOTE_LENGTH }}</span
                    >
                </div>
            </div>
        </template>
        <SuspicionBoard
            :state="state"
            :visible="active && revealed && !blocked && tab === 'suspicions'"
        />
        <footer class="journal-footer">
            <LockKeyhole :size="12" /> Private. Nothing here is sent to chat.
        </footer>
    </section>
</template>

<style scoped>
.personal-journal {
    --journal-brass: #ddc398;
    padding: 24px;
    background: #172729;
    border-top: 2px solid var(--journal-brass);
}
.journal-heading .eyebrow {
    color: var(--journal-brass);
    display: flex;
    align-items: center;
    gap: 6px;
}
.journal-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin: 10px 0;
}
.journal-title-row h2 {
    margin: 0;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 30px;
}
.journal-title-row button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 44px;
    background: transparent;
    color: #f2dfb9;
    border: 0;
    font-size: 12px;
}
.journal-heading > p:last-child {
    color: #b6c3bd;
    font-size: 14px;
    margin: 0;
}
.journal-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border-block: 1px solid #53605a;
    margin: 22px 0;
    gap: 6px;
}
.journal-tabs button {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    background: transparent;
    color: #c6cec5;
    border: 0;
    border-bottom: 2px solid transparent;
    padding: 14px 5px;
    font-size: 14px;
    font-weight: 600;
}
.journal-tabs button[aria-pressed='true'] {
    color: #f2dfb9;
    border-bottom-color: var(--journal-brass);
    background: #ddc3980b;
}
.journal-tabs button:hover {
    background: #ddc39812;
}
.journal-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
}
.journal-section-heading h3 {
    margin: 0;
    font-size: 17px;
}
.journal-section-heading > span {
    color: #b6c3bd;
    font-size: 12px;
    white-space: nowrap;
}
.journal-round {
    margin-top: 24px;
}
.journal-round h4 {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--journal-brass);
    font-size: 11px;
    letter-spacing: 0.14em;
    margin: 0 0 14px;
}
.journal-round-line {
    height: 1px;
    flex: 1;
    background: #ddc39835;
}
.journal-round ol {
    list-style: none;
    padding: 0;
    margin: 0;
}
.journal-entry {
    padding: 0 0 18px 16px;
    margin: 0 0 18px;
    border-left: 2px solid #aa937056;
    border-bottom: 1px solid #c6cec51c;
    overflow-wrap: anywhere;
}
.journal-entry:last-child {
    margin-bottom: 0;
}
.journal-result-type {
    color: #c7d3cb;
    font-size: 12px;
    margin: 0 0 7px;
}
.journal-entry h5 {
    color: #f5ebd9;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 22px;
    line-height: 1.25;
    margin: 0 0 9px;
    font-weight: 500;
}
.journal-outcome {
    color: #ebe7dc;
    font-size: 15px;
    line-height: 1.75;
    margin: 0;
}
.journal-caveat {
    border-left: 2px solid var(--journal-brass);
    padding-left: 12px;
    margin: 20px 0 0;
    color: #c7d0c5;
    font-size: 13px;
    line-height: 1.65;
}
.journal-empty {
    padding: 30px 0;
    color: #c7d0c5;
}
.journal-empty > svg {
    color: var(--journal-brass);
}
.journal-empty h3 {
    color: #f0e7d6;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 22px;
    margin: 14px 0 10px;
}
.journal-empty p {
    font-size: 15px;
    line-height: 1.75;
    margin: 0 0 18px;
}
.journal-notes label {
    display: block;
    font-size: 17px;
    color: #f0e7d6;
    font-weight: 600;
}
.journal-notes > p {
    font-size: 13px;
    line-height: 1.65;
    color: #c0ccc3;
    margin: 9px 0 18px;
}
.journal-notes textarea {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-height: 270px;
    max-height: 55dvh;
    resize: vertical;
    border: 1px solid #6f7665;
    border-radius: 3px;
    padding: 16px;
    background: #112023;
    color: #f3eddf;
    font: inherit;
    font-size: 16px;
    line-height: 1.8;
    scrollbar-width: thin;
}
.journal-notes textarea::placeholder {
    color: #99aaa4;
}
.journal-notes textarea:disabled {
    opacity: 0.6;
}
.journal-save-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 14px;
    margin-top: 12px;
    color: #c2ceb8;
    font-size: 11px;
    line-height: 1.6;
}
.journal-save-row > span:last-child {
    white-space: nowrap;
    color: #acb9af;
}
.save-unavailable {
    color: #f0c49c;
}
.journal-footer {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-top: 24px;
    padding-top: 15px;
    border-top: 1px solid #c6cec526;
    color: #b6c3bd;
    font-size: 11px;
}
.personal-journal button:focus-visible,
.personal-journal textarea:focus-visible {
    outline: 2px solid #f0d4a4;
    outline-offset: 3px;
}
@media (max-width: 480px) {
    .personal-journal {
        padding: 18px;
    }
    .journal-title-row h2 {
        font-size: 27px;
    }
    .journal-tabs {
        gap: 0;
    }
    .journal-tabs button {
        flex-wrap: wrap;
        gap: 5px;
        font-size: 12px;
    }
}
</style>
