import type { Player, PrivateNightResult, RoomState } from './chanting';

export function privateResultText(result: PrivateNightResult): string {
    if (result.kind === 'visits')
        return `${result.target} ${result.visited ? 'was targeted by at least one other player' : 'was not targeted by any other player'}.`;
    if (result.kind === 'protection')
        return `You protected ${result.target} from new curses.`;
    if (result.kind === 'spirit')
        return `${result.target}'s true alignment was ${result.alignment}.`;
    if (result.kind === 'bell')
        return result.prevented
            ? 'Your bell prevented 1 ritual step.'
            : 'You rang the bell, but no ritual step could be prevented. Your ability is spent.';
    if (result.kind === 'disruption')
        return `You sent a disruption to ${result.target} instead of chanting.`;
    if (result.kind === 'disrupted')
        return 'Your night action was disrupted. No ability or chant took effect. Any once-per-match ability you committed is spent.';
    return `${result.target} appeared ${result.alignment}.`;
}

export function hasLimitedAbility(role: string | null): boolean {
    return ['medium', 'dreamweaver', 'bellkeeper'].includes(role ?? '');
}

export function eligibleTargets(
    state: RoomState,
    useAbility = false,
): Player[] {
    const night = state.phase === 'night';
    if (
        night &&
        hasLimitedAbility(state.me.role) &&
        (!useAbility || state.me.ability_used || state.me.role === 'bellkeeper')
    )
        return [];
    const deadTarget = night && state.me.role === 'medium' && useAbility;
    return state.players.filter(
        (player) =>
            player.alive !== deadTarget &&
            player.id !== state.me.id &&
            !(
                state.phase === 'night' &&
                state.me.role === 'warden' &&
                player.id === state.me.previous_protection_target
            ),
    );
}

export function nightActionLabel(
    state: RoomState,
    useAbility: boolean,
    target: string | null,
): string {
    if (useAbility && hasLimitedAbility(state.me.role))
        return state.me.role === 'medium'
            ? 'Contact the banished player'
            : state.me.role === 'dreamweaver'
              ? 'Disrupt instead of chanting'
              : 'Ring the bell';
    if (state.me.role === 'oracle') return 'Confirm investigation';
    if (state.me.role === 'warden')
        return target ? 'Confirm protection' : 'Confirm skipped protection';
    if (state.me.role === 'lamplighter') return 'Confirm observation';
    return state.me.alignment === 'cult'
        ? 'Chant for the ritual'
        : 'Keep watch tonight';
}
