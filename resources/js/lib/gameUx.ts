import type { RoomState } from './chanting';

export interface ActionChoice {
    target: string | null;
    useAbility: boolean;
    curse: string;
    forgedAlignment: string;
}

/** Describes the submitted choice, never an assumed night outcome. */
export function actionConsequence(
    state: RoomState,
    choice: ActionChoice,
): string {
    const name =
        state.players.find((p) => p.id === choice.target)?.name ?? 'a player';
    if (state.phase === 'voting') {
        const ballot = choice.target
            ? `Vote to banish ${name}. Your vote is final; ties banish nobody.`
            : 'Abstain from this vote. Your abstention is final.';
        return (
            ballot +
            (choice.target && state.me.curse?.type === 'misdirection'
                ? ' Your active Misdirection may redirect this target.'
                : '')
        );
    }
    const role = state.me.role;
    let action =
        state.me.alignment === 'cult'
            ? 'Chant for the ritual, subject to your mission.'
            : 'Keep watch tonight.';
    if (role === 'warden')
        action = choice.target
            ? `Protect ${name} from new curses.`
            : 'Skip protection tonight.';
    if (role === 'lamplighter')
        action = `Observe visits to ${name}. Your result arrives at dawn.`;
    if (role === 'tracker')
        action = `Track ${name}. Your result arrives at dawn.`;
    if (['veilweaver', 'acolyte'].includes(role ?? '') && choice.target) {
        const curse =
            {
                puzzle: 'Soul Bind',
                mist: 'Mind Mist',
                misdirection: 'Misdirection',
            }[choice.curse] ?? choice.curse;
        action = `Chant and attempt to curse ${name} with ${curse}.`;
        if (role === 'veilweaver')
            action +=
                ' Also attempt to reverse their apparent alignment tonight.';
    }
    if (choice.useAbility && !state.me.ability_used) {
        const actions: Record<string, string> = {
            oracle: `Investigate ${name}. Your private reading arrives at dawn.`,
            vigilante: `Shoot ${name}. If they are not a cultist, you also leave the village in guilt.`,
            medium: `Contact ${name} to learn their true alignment at dawn.`,
            dreamweaver: `Disrupt ${name} instead of chanting.`,
            phantasm: `Haunt ${name} and hide their outgoing visits instead of chanting.`,
            counterfeiter: `Make ${name} appear ${choice.forgedAlignment === 'cult' ? 'Cult' : 'Town'} to the Oracle tonight instead of chanting.`,
            herbalist: 'Protect the village from new curses tonight.',
            bellkeeper: 'Ring the bell to prevent one ritual step if possible.',
        };
        if (role && actions[role])
            action = `${actions[role]} Uses your once-per-match ability, even if disrupted.`;
    }
    if (choice.target && state.me.curse?.type === 'misdirection')
        action += ' Your active Misdirection may redirect this target.';
    return `${action} This choice is final.`;
}

/** Handles the server's rolling 100-event log without depending on its length. */
export function newPublicEvents(
    previous: string[],
    current: string[],
): string[] {
    for (
        let overlap = Math.min(previous.length, current.length);
        overlap > 0;
        overlap--
    ) {
        if (
            previous
                .slice(-overlap)
                .every((event, index) => event === current[index])
        )
            return current.slice(overlap);
    }
    return current;
}

export function lobbyBlockers(state: RoomState, unsaved: boolean): string[] {
    const blockers: string[] = [];
    const missing = state.rules.min_players - state.players.length;
    if (missing > 0)
        blockers.push(
            `Invite ${missing} more ${missing === 1 ? 'player' : 'players'} to begin.`,
        );
    if (state.mode_preview?.error) blockers.push(state.mode_preview.error);
    if (
        !state.mode_preview &&
        state.roster === 'illusions' &&
        state.players.length < 5
    )
        blockers.push('Illusions needs at least 5 players.');
    if (unsaved) blockers.push('Apply or cancel your mode changes.');
    const waiting = state.players
        .filter((p) => !p.ready)
        .map((p) => (p.id === state.me.id ? 'you' : p.name));
    if (waiting.length)
        blockers.push(`Waiting for ${waiting.join(', ')} to mark ready.`);
    return blockers;
}
