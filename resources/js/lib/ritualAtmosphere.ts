/** Six night scenes span the public ritual's 0%, 20%, …, 100% milestones. */
export function ritualNightStage(tokens: number, threshold: number): number {
    if (
        !Number.isFinite(tokens) ||
        !Number.isFinite(threshold) ||
        threshold <= 0
    ) {
        return 1;
    }

    const progress = Math.min(1, Math.max(0, tokens / threshold));
    return 1 + Math.floor(progress * 5);
}

/** A completed summoning keeps its night atmosphere through the final vote. */
export function usesNightAtmosphere(
    phase: string,
    finalVote: boolean,
): boolean {
    return (
        phase === 'night' ||
        (finalVote && ['discussion', 'last_words', 'voting'].includes(phase))
    );
}
