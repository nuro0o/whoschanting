export interface RitualSceneInput {
    phase:
        | 'lobby'
        | 'reveal'
        | 'night'
        | 'bargains'
        | 'discussion'
        | 'last_words'
        | 'voting'
        | 'finished';
    tokens: number;
    threshold: number;
    winner: 'town' | 'cult' | null;
}

/** Decorative state derived exclusively from the public ritual and phase. */
export function ritualSceneState(input: RitualSceneInput) {
    const reset = input.phase === 'lobby' || input.phase === 'reveal';
    const valid = Number.isFinite(input.threshold) && input.threshold > 0;
    const progress =
        reset || !valid || !Number.isFinite(input.tokens)
            ? 0
            : Math.max(0, Math.min(1, input.tokens / input.threshold));
    const calmed = input.phase === 'finished' && input.winner === 'town';
    const summoned = input.phase === 'finished' && input.winner === 'cult';
    const runeCount = valid
        ? Math.max(2, Math.min(24, Math.ceil(input.threshold)))
        : 6;
    return {
        progress,
        night:
            !calmed &&
            (['night', 'bargains'].includes(input.phase) ||
                summoned ||
                (progress === 1 &&
                    ['discussion', 'last_words', 'voting'].includes(
                        input.phase,
                    ))),
        cracks: calmed ? 0 : Math.max(0, (progress - 0.35) / 0.65),
        mist: calmed ? 0 : Math.max(0, (progress - 0.45) / 0.55),
        emergence:
            calmed || reset
                ? 0
                : summoned
                  ? 1
                  : Math.max(0, (progress - 0.65) / 0.35) * 0.68,
        runeCount,
        litRunes: calmed ? 0 : Math.floor(progress * runeCount),
        calmed,
    };
}

export type RitualSceneState = ReturnType<typeof ritualSceneState>;
/** Public display copy; the renderer never owns a countdown or game clock. */
export interface RitualTableDisplay {
    phase: string;
    value: string;
    detail: string;
    urgent: boolean;
}
export interface RitualSceneSeat {
    id?: string;
    x: number;
    y: number;
    alive: boolean;
}

export interface RitualCosmeticEvent {
    id: string;
    kind: 'banishment' | 'celebration';
    player_id: string;
    effect: string;
    created_at: string;
}

/** Initial/reconnected snapshots establish a baseline; only live, fresh events play. */
export function createCosmeticEventStream() {
    let match: string | null | undefined;
    const seen = new Set<string>();
    return {
        consume(
            matchId: string | null,
            events: RitualCosmeticEvent[],
            serverTime: string,
        ) {
            const baseline = match !== matchId;
            if (baseline) {
                match = matchId;
                seen.clear();
            }
            const now = Date.parse(serverTime);
            const fresh = events.filter((event) => {
                if (seen.has(event.id)) return false;
                seen.add(event.id);
                const age = now - Date.parse(event.created_at);
                return (
                    !baseline &&
                    Number.isFinite(age) &&
                    age >= 0 &&
                    age < 20_000
                );
            });
            // The server emits in resolution order: final banishment, then victory.
            return fresh;
        },
    };
}
