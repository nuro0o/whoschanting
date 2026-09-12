import type { MatchRecapData, Player } from './chanting';

export interface RecapStoryStep {
    id: string;
    label: string;
    title: string;
    body: string;
    details: string[];
}

export function buildRecapStory(
    recap: MatchRecapData,
    players: Player[],
): RecapStoryStep[] {
    const names = new Map(players.map((player) => [player.id, player.name]));
    const revealedRoles = new Map(
        players
            .filter((player) => player.role)
            .map((player) => [player.id, player.role!]),
    );
    const name = (id: string | null) =>
        id ? (names.get(id) ?? 'A former villager') : 'nobody';
    const roleName = (role: string) =>
        role.charAt(0).toUpperCase() + role.slice(1);
    const steps: RecapStoryStep[] = [];
    const rounds = [...recap.rounds].sort((a, b) => a.day - b.day);
    const days = [
        ...new Set([
            ...rounds.map((round) => round.day),
            ...(recap.claims ?? []).map((claim) => claim.day),
        ]),
    ].sort((a, b) => a - b);
    const finalVoteDay = rounds.filter((round) => round.vote).at(-1)?.day;
    for (const day of days) {
        const round = rounds.find((entry) => entry.day === day);
        if (round?.night) {
            const night = round.night;
            const details: string[] = [];
            for (const action of night.actions) {
                if (
                    action.role === 'oracle' &&
                    action.submitted &&
                    !action.disrupted &&
                    action.forged
                ) {
                    details.push(
                        `${name(action.player_id)} read ${name(action.target_id)} as ${action.apparent_alignment === 'cult' ? 'Cult' : action.apparent_alignment === 'town' ? 'Town' : 'an unrecorded alignment'}. A Counterfeiter forged that reading, overriding any veil.`,
                    );
                }
                if (
                    action.role === 'bellkeeper' &&
                    action.submitted &&
                    !action.disrupted &&
                    action.used_ability &&
                    (action.prevented_steps ?? 0) > 0
                ) {
                    details.push(
                        `${name(action.player_id)} rang the bell and prevented ${action.prevented_steps} ritual ${action.prevented_steps === 1 ? 'step' : 'steps'}. Existing progress stayed intact.`,
                    );
                }
                if (action.submitted && action.disrupted) {
                    details.push(
                        `${name(action.player_id)} was disrupted. Their action and any chant did not take effect.`,
                    );
                }
            }
            steps.push({
                id: `night-${day}`,
                label: `Night ${day}`,
                title: night.gained
                    ? 'The ritual moved forward'
                    : 'The ritual held still',
                body: `The cult gained ${night.gained} ritual ${night.gained === 1 ? 'step' : 'steps'}. The night ended at ${night.tokens} of ${recap.ritual_goal}.`,
                details,
            });
        }
        const claims = (recap.claims ?? []).filter(
            (claim) => claim.day === day,
        );
        if (claims.length) {
            const mismatches = claims.filter(
                (claim) =>
                    revealedRoles.has(claim.player_id) &&
                    revealedRoles.get(claim.player_id) !== claim.role,
            );
            steps.push({
                id: `claims-${day}`,
                label: `Day ${day} · The stories`,
                title: mismatches.length
                    ? `${mismatches.length} role ${mismatches.length === 1 ? 'claim did' : 'claims did'} not match`
                    : 'The claims, in the light',
                body: 'Compare the public claims with the revealed roles. A mismatch establishes what was said, not why it was said.',
                details: claims.map((claim) => {
                    const actual = revealedRoles.get(claim.player_id);
                    const comparison = actual
                        ? `Revealed role: ${roleName(actual)}${actual === claim.role ? ' (matches)' : ' (mismatch)'}.`
                        : 'Their revealed role is unavailable.';
                    return `${name(claim.player_id)} claimed ${roleName(claim.role)}. ${comparison} “${claim.body}”`;
                }),
            });
        }
        if (round?.last_words?.accused_ids.length) {
            const record = round.last_words;
            steps.push({
                id: `last-words-${day}`,
                label: `Day ${day} · Last Words`,
                title: 'The accused had the floor',
                body: `${record.accused_ids.map(name).join(', ')} received the most accusations. The village heard their defenses before voting.`,
                details: record.accused_ids.map(
                    (id) =>
                        `${name(id)}: ${record.defenses.find((entry) => entry.player_id === id)?.body ?? 'No defense was submitted.'}`,
                ),
            });
        }
        if (round?.vote) {
            const vote = round.vote;
            const final = day === finalVoteDay;
            steps.push({
                id: `vote-${day}`,
                label: `Day ${day} · ${final ? 'Last recorded vote' : 'The vote'}`,
                title: vote.banished_id
                    ? `${name(vote.banished_id)} was banished`
                    : 'Nobody was banished',
                body: final
                    ? 'These were the final ballots recorded for this match.'
                    : 'The village put its suspicions to a vote.',
                details: vote.ballots.map((ballot) => {
                    let text = !ballot.submitted
                        ? `${name(ballot.player_id)} missed the deadline; their ballot counted as abstention.`
                        : ballot.target_id
                          ? `${name(ballot.player_id)} voted for ${name(ballot.target_id)}.`
                          : `${name(ballot.player_id)} abstained.`;
                    if (
                        ballot.chosen_target_id &&
                        ballot.chosen_target_id !== ballot.target_id
                    )
                        text += ` Misdirection changed their choice from ${name(ballot.chosen_target_id)}.`;
                    return text;
                }),
            });
        }
    }
    if (recap.winner)
        steps.push({
            id: 'ending',
            label: 'The ending',
            title:
                recap.winner === 'town'
                    ? 'The village prevailed'
                    : 'The cult prevailed',
            body:
                recap.win_reason || 'The match ended in victory for this team.',
            details: [],
        });
    if (!steps.length)
        steps.push({
            id: 'unrecorded',
            label: 'The record',
            title: 'Some secrets were not recorded',
            body: 'There are no recorded rounds to retell for this match. Any available match details remain below.',
            details: [],
        });
    return steps;
}
