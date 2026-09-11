import type { Player, PrivateNightResult, RoomState } from './chanting';

export function privateResultText(result: PrivateNightResult): string {
    if (result.kind === 'visits')
        return `${result.target} ${result.visited ? 'was targeted by at least one other player' : 'was not targeted by any other player'}.`;
    if (result.kind === 'protection')
        return `You protected ${result.target} from new curses.`;
    return `${result.target} appeared ${result.alignment}.`;
}

export function eligibleTargets(state: RoomState): Player[] {
    return state.players.filter(
        (player) =>
            player.alive &&
            player.id !== state.me.id &&
            !(
                state.phase === 'night' &&
                state.me.role === 'warden' &&
                player.id === state.me.previous_protection_target
            ),
    );
}
