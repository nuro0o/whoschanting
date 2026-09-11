import type { Player, PrivateNightResult, RoomState } from './chanting';

export function discussionActionTypes(
    state: RoomState,
    revealed: boolean,
): ('exorcise' | 'oath')[] {
    if (!state.me.alive || state.phase !== 'discussion') return [];
    const actions: ('exorcise' | 'oath')[] = [];
    if (revealed && state.me.role === 'exorcist') actions.push('exorcise');
    if (
        state.mode_setup?.mode === 'paranoia' ||
        (revealed && state.me.role === 'oathkeeper')
    )
        actions.push('oath');
    return actions;
}

export function privateResultText(result: PrivateNightResult): string {
    if (result.kind === 'tracking')
        return result.visited_target
            ? `${result.target} was seen targeting ${result.visited_target}.`
            : `No outgoing visit was visible for ${result.target}.`;
    if (result.kind === 'herbs')
        return 'You protected the village from all new curses tonight. Your ability is spent.';
    if (result.kind === 'haunting')
        return `You haunted ${result.target} instead of chanting and concealed their outgoing visits.`;
    if (result.kind === 'forgery')
        return `You made ${result.target} appear ${result.alignment} to the Oracle tonight instead of chanting.`;
    if (result.kind === 'exorcism')
        return `You cleansed ${result.target}. Your exorcism is spent.`;
    if (result.kind === 'oath')
        return result.kept
            ? `You kept your oath to vote for ${result.target}. You are protected from new curses next night.`
            : `Your ballot did not keep your oath to vote for ${result.target}. No protection was earned.`;
    if (result.kind === 'visits')
        return result.visited
            ? `At least one other player was seen targeting ${result.target}.`
            : `No other player was seen targeting ${result.target}.`;
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
    return [
        'oracle',
        'medium',
        'dreamweaver',
        'bellkeeper',
        'herbalist',
        'phantasm',
        'counterfeiter',
        'exorcist',
    ].includes(role ?? '');
}

export function eligibleTargets(
    state: RoomState,
    useAbility = false,
): Player[] {
    const night = state.phase === 'night';
    if (
        night &&
        hasLimitedAbility(state.me.role) &&
        (!useAbility ||
            state.me.ability_used ||
            ['bellkeeper', 'herbalist'].includes(state.me.role ?? ''))
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
    if (useAbility && state.me.role === 'oracle')
        return 'Confirm investigation';
    if (useAbility && state.me.role === 'herbalist')
        return 'Protect the village';
    if (state.me.role === 'tracker') return 'Confirm tracking';
    if (useAbility && state.me.role === 'phantasm')
        return 'Haunt instead of chanting';
    if (useAbility && state.me.role === 'counterfeiter')
        return 'Forge instead of chanting';
    if (useAbility && hasLimitedAbility(state.me.role))
        return state.me.role === 'medium'
            ? 'Contact the banished player'
            : state.me.role === 'dreamweaver'
              ? 'Disrupt instead of chanting'
              : 'Ring the bell';
    if (state.me.role === 'warden')
        return target ? 'Confirm protection' : 'Confirm skipped protection';
    if (state.me.role === 'lamplighter') return 'Confirm observation';
    return state.me.alignment === 'cult'
        ? target && ['veilweaver', 'acolyte'].includes(state.me.role ?? '')
            ? 'Chant and curse'
            : 'Chant for the ritual'
        : 'Keep watch tonight';
}
