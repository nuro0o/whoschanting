import type { MatchRecapData, Player } from './chanting';

export interface RecapHighlight {
    id: string;
    day: number;
    title: string;
    detail: string;
}

/** Describe recorded outcomes only; never guess a cause or an unrevealed role. */
export function recapHighlights(
    recap: MatchRecapData,
    players: Player[],
): RecapHighlight[] {
    const names = new Map(players.map((player) => [player.id, player.name]));
    const name = (id: string | null) =>
        id ? (names.get(id) ?? 'A former villager') : 'Nobody';
    const candidates: (RecapHighlight & { priority: number })[] = [];
    const rounds = [...recap.rounds].sort((a, b) => a.day - b.day);
    let reachedGoal = false;
    for (const round of rounds) {
        if (round.vote?.banished_id) {
            const id = round.vote.banished_id;
            const count = round.vote.ballots.filter(
                (ballot) => ballot.submitted && ballot.target_id === id,
            ).length;
            candidates.push({
                id: `vote-${round.day}`,
                day: round.day,
                priority: 4,
                title: `${name(id)} was banished`,
                detail: count
                    ? `${count} recorded ${count === 1 ? 'ballot' : 'ballots'} named ${name(id)}.`
                    : 'The village verdict was recorded; no matching ballots are available.',
            });
        }
        if (!round.night) continue;
        if (
            !reachedGoal &&
            recap.ritual_goal > 0 &&
            round.night.tokens >= recap.ritual_goal
        ) {
            reachedGoal = true;
            candidates.push({
                id: `ritual-${round.day}`,
                day: round.day,
                priority: 5,
                title: 'The ritual reached its goal',
                detail: `${round.night.tokens} of ${recap.ritual_goal} ritual steps were recorded after this night.`,
            });
        }
        for (const action of round.night.actions) {
            if (!action.submitted || action.disrupted) continue;
            if (
                action.role === 'bellkeeper' &&
                action.used_ability &&
                (action.prevented_steps ?? 0) > 0
            ) {
                const steps = action.prevented_steps!;
                candidates.push({
                    id: `bell-${round.day}-${action.player_id}`,
                    day: round.day,
                    priority: 3,
                    title: 'The bell held back the ritual',
                    detail: `${name(action.player_id)} prevented ${steps} ritual ${steps === 1 ? 'step' : 'steps'}.`,
                });
            }
            const target = players.find(
                (player) => player.id === action.target_id,
            );
            if (
                action.role === 'oracle' &&
                target?.alignment &&
                ['town', 'cult'].includes(action.apparent_alignment ?? '') &&
                target.alignment !== action.apparent_alignment
            ) {
                candidates.push({
                    id: `reading-${round.day}-${action.player_id}`,
                    day: round.day,
                    priority: 2,
                    title: 'An Oracle reading was misleading',
                    detail: `${name(action.player_id)} read ${name(action.target_id)} as ${action.apparent_alignment}; their revealed alignment was ${target.alignment}.`,
                });
            }
        }
    }
    // Prefer a mix of actual turning points, with the final verdict first among votes.
    const chosen: typeof candidates = [];
    const categories = new Set<string>();
    for (const item of candidates.sort(
        (a, b) => b.priority - a.priority || b.day - a.day,
    )) {
        const category = item.id.split('-')[0];
        if (categories.has(category)) continue;
        chosen.push(item);
        categories.add(category);
        if (chosen.length === 4) break;
    }
    return chosen.map(({ priority: _priority, ...highlight }) => highlight);
}
