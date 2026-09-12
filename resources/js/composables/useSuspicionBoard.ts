import { computed, onMounted, shallowRef, watch } from 'vue';
import { roles, type RoomState } from '@/lib/chanting';
import {
    decodeSuspicionBoard,
    emptySuspicionEntry,
    serializeSuspicionBoard,
    suspicionBoardStorageKey,
    updateSuspicionEntry,
    type SuspicionBoardData,
    type SuspicionEntry,
} from '@/lib/suspicionBoard';

type StorageState = 'empty' | 'saved' | 'unavailable' | 'invalid' | 'waiting';
function createBoard() {
    return {
        entries: shallowRef<SuspicionBoardData>({}),
        status: shallowRef<StorageState>('waiting'),
    };
}
// One reactive draft per browser seat and match. Both journal and inspector
// edit this instance, including when localStorage cannot be used.
const boards = new Map<string, ReturnType<typeof createBoard>>();

export function useSuspicionBoard(state: () => RoomState) {
    const mounted = shallowRef(false);
    const board = shallowRef<ReturnType<typeof createBoard> | null>(null);
    const storageKey = computed(() =>
        suspicionBoardStorageKey(state().id, state().me.id, state().match_id),
    );
    const editable = computed(
        () => !!board.value && state().phase !== 'lobby' && mounted.value,
    );
    const storageState = computed(() => board.value?.status.value ?? 'waiting');
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
    function loadBoard() {
        board.value = null;
        const key = storageKey.value;
        if (!mounted.value || !key) return;
        let shared = boards.get(key);
        if (!shared) {
            shared = createBoard();
            boards.set(key, shared);
            try {
                const decoded = decodeSuspicionBoard(
                    localStorage.getItem(key),
                    state()
                        .players.filter((p) => p.id !== state().me.id)
                        .map((p) => p.id),
                    Object.keys(roles),
                    state().me.results.length,
                );
                shared.entries.value = decoded.entries;
                shared.status.value = decoded.invalid
                    ? 'invalid'
                    : Object.keys(decoded.entries).length
                      ? 'saved'
                      : 'empty';
            } catch {
                shared.status.value = 'unavailable';
            }
        }
        board.value = shared;
    }
    function entryFor(id: string | undefined): SuspicionEntry {
        const entries = board.value?.entries.value;
        return id && entries && Object.hasOwn(entries, id)
            ? entries[id]
            : emptySuspicionEntry();
    }
    function updateEntry(id: string, patch: Partial<SuspicionEntry>) {
        const shared = board.value;
        const key = storageKey.value;
        if (
            !editable.value ||
            !shared ||
            !key ||
            id === state().me.id ||
            !state().players.some((p) => p.id === id)
        )
            return;
        shared.entries.value = updateSuspicionEntry(
            shared.entries.value,
            id,
            patch,
        );
        try {
            localStorage.setItem(
                key,
                serializeSuspicionBoard(shared.entries.value),
            );
            shared.status.value = 'saved';
        } catch {
            shared.status.value = 'unavailable';
        }
    }
    watch(storageKey, loadBoard, { flush: 'sync' });
    onMounted(() => {
        mounted.value = true;
        loadBoard();
    });
    return {
        storageKey,
        editable,
        storageState,
        saveMessage,
        entryFor,
        updateEntry,
    };
}
