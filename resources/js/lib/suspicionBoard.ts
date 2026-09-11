import type { PrivateNightResult } from './chanting';

export type SuspicionStance = 'unmarked' | 'suspicious' | 'trusted';
export interface SuspicionEntry {
    stance: SuspicionStance;
    claimedRole: string | null;
    note: string;
    resultIndices: number[];
}
export type SuspicionBoardData = Record<string, SuspicionEntry>;

export const MAX_SUSPICION_NOTE_LENGTH = 1200;
const MAX_PINNED_RESULTS = 100;
const MAX_STORED_LENGTH = 250_000;
const stances: readonly string[] = ['unmarked', 'suspicious', 'trusted'];

export function emptySuspicionEntry(): SuspicionEntry {
    return {
        stance: 'unmarked',
        claimedRole: null,
        note: '',
        resultIndices: [],
    };
}

export function suspicionBoardStorageKey(
    roomId: string | number,
    seatId: string,
    matchId: string | null | undefined,
): string | null {
    if (!matchId) return null;
    return `chanting-suspicions:${JSON.stringify([roomId, seatId, matchId])}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function validIndices(
    value: unknown,
    resultCount = Number.MAX_SAFE_INTEGER,
): number[] {
    if (!Array.isArray(value)) return [];
    return [
        ...new Set(
            value.filter(
                (index): index is number =>
                    typeof index === 'number' &&
                    Number.isSafeInteger(index) &&
                    index >= 0 &&
                    index < resultCount,
            ),
        ),
    ].slice(0, MAX_PINNED_RESULTS);
}

/** Restore only this match's known seats, role labels and existing private results. */
export function decodeSuspicionBoard(
    raw: string | null,
    playerIds: readonly string[],
    roleIds: readonly string[],
    resultCount: number,
): { entries: SuspicionBoardData; invalid: boolean } {
    if (raw === null) return { entries: {}, invalid: false };
    if (raw.length > MAX_STORED_LENGTH) return { entries: {}, invalid: true };
    let parsed: unknown;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return { entries: {}, invalid: true };
    }
    if (
        !isRecord(parsed) ||
        parsed.version !== 1 ||
        !isRecord(parsed.entries)
    ) {
        return { entries: {}, invalid: true };
    }
    const saved = parsed.entries;
    let invalid = false;
    const entries = Object.fromEntries(
        playerIds.flatMap((playerId) => {
            if (!Object.hasOwn(saved, playerId)) return [];
            const value = saved[playerId];
            if (!isRecord(value)) {
                invalid = true;
                return [];
            }
            const stance =
                typeof value.stance === 'string' &&
                stances.includes(value.stance)
                    ? (value.stance as SuspicionStance)
                    : 'unmarked';
            const claimedRole =
                typeof value.claimedRole === 'string' &&
                roleIds.includes(value.claimedRole)
                    ? value.claimedRole
                    : null;
            if (
                stance !== value.stance ||
                claimedRole !== value.claimedRole ||
                typeof value.note !== 'string' ||
                !Array.isArray(value.resultIndices)
            ) {
                invalid = true;
            }
            const entry: SuspicionEntry = {
                stance,
                claimedRole,
                note:
                    typeof value.note === 'string'
                        ? value.note.slice(0, MAX_SUSPICION_NOTE_LENGTH)
                        : '',
                resultIndices: validIndices(
                    value.resultIndices,
                    Math.max(0, resultCount),
                ),
            };
            return [[playerId, entry]];
        }),
    );
    return { entries, invalid };
}

/** An edit never alters another player's entry or the caller's previous state. */
export function updateSuspicionEntry(
    entries: SuspicionBoardData,
    playerId: string,
    patch: Partial<SuspicionEntry>,
): SuspicionBoardData {
    const current = Object.hasOwn(entries, playerId)
        ? entries[playerId]
        : emptySuspicionEntry();
    const value = { ...current, ...patch };
    const entry: SuspicionEntry = {
        stance: stances.includes(value.stance) ? value.stance : 'unmarked',
        claimedRole:
            typeof value.claimedRole === 'string' ? value.claimedRole : null,
        note:
            typeof value.note === 'string'
                ? value.note.slice(0, MAX_SUSPICION_NOTE_LENGTH)
                : '',
        resultIndices: validIndices(value.resultIndices),
    };
    return { ...entries, [playerId]: entry };
}

export function serializeSuspicionBoard(entries: SuspicionBoardData): string {
    return JSON.stringify({ version: 1, entries });
}

/** Use original append-only result indices, never the position in a sorted list. */
export function linkedSuspicionResults(
    entry: SuspicionEntry,
    results: PrivateNightResult[],
) {
    return validIndices(entry.resultIndices, results.length)
        .sort((a, b) => b - a)
        .map((index) => ({ index, result: results[index] }));
}
