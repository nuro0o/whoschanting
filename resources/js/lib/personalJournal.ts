import type { PrivateNightResult } from './chanting';

export const MAX_JOURNAL_NOTE_LENGTH = 6000;

export function journalStorageKey(
    roomId: string | number,
    playerId: string,
    matchId: string | null | undefined,
): string | null {
    if (!matchId) return null;
    return `chanting-journal:${JSON.stringify([roomId, playerId, matchId])}`;
}

export function groupJournalResults(results: PrivateNightResult[]) {
    const rounds = new Map<
        number,
        { result: PrivateNightResult; index: number }[]
    >();
    results.forEach((result, index) => {
        const entries = rounds.get(result.day) ?? [];
        entries.unshift({ result, index });
        rounds.set(result.day, entries);
    });
    return [...rounds.entries()]
        .sort(([a], [b]) => b - a)
        .map(([day, entries]) => ({ day, entries }));
}

export function journalResultLabel(result: PrivateNightResult): string {
    return {
        alignment: 'Oracle reading',
        visits: 'Lamplighter watch',
        tracking: 'Tracking',
        herbs: 'Village protection',
        shot: 'Vigilante shot',
        protection: 'Warden protection',
        spirit: 'Spirit reading',
        bell: 'Bell rung',
        disruption: 'Disruption sent',
        disrupted: 'Action disrupted',
        haunting: 'Haunting',
        forgery: 'Forged reading',
        exorcism: 'Exorcism',
        oath: 'Voting oath',
    }[result.kind ?? 'alignment'];
}
