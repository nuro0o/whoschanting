import type { RoomState } from './chanting';

export function faeRecipients(
    state: RoomState,
    renewalId: string | null = null,
) {
    const collector = state.me.role === 'fae_collector';
    const original = state.fae?.bargains.find(
        (b) => b.id === renewalId && state.fae?.renewable_ids?.includes(b.id),
    );
    if (collector && (!original || state.fae?.collector_used)) return [];
    return state.players.filter(
        (p) =>
            p.alive &&
            p.id !== state.me.id &&
            !state.me.allies.some((ally) => ally.id === p.id) &&
            !state.fae?.bargains.some(
                (b) => b.recipient_id === p.id && b.status === 'fulfilled',
            ) &&
            (!collector ||
                (p.id !== original?.recipient_id &&
                    p.id !== original?.promise_target &&
                    p.id !== original?.gift_target)),
    );
}

export type BargainKind = 'thorn' | 'voice' | 'passage' | 'lantern';
export const offerableBargains: BargainKind[] = ['thorn', 'voice', 'passage'];
export interface FaeBargain {
    id: string;
    day: number;
    recipient_id: string;
    sender_id?: string;
    kind: BargainKind;
    promise_target: string | null;
    gift_target?: string | null;
    status:
        | 'offered'
        | 'accepted'
        | 'declined'
        | 'expired'
        | 'void'
        | 'fulfilled'
        | 'broken';
}
export interface FaeState {
    collector_used?: boolean;
    renewable_ids?: string[];
    seals: number;
    goal: number;
    minimum_rounds: number;
    bargains: FaeBargain[];
}
export const bargains: Record<BargainKind, { name: string; gift: string }> = {
    thorn: {
        name: 'Thorn’s Protection',
        gift: 'Protection from all new curses next night, even if you break your promise. It does not stop other abilities and has no effect if the match ends first.',
    },
    voice: {
        name: 'A Borrowed Voice',
        gift: 'After today’s voting closes, privately learn the chosen player’s actual ballot, including abstention or a missed vote. This reveals no role. You still receive the report if you break your promise; there is no report if the match ends before voting.',
    },
    lantern: {
        name: 'Lantern Secret',
        gift: 'A private clue about whether someone other than you visibly visited the promised accusation target tonight. Hidden visits stay hidden.',
    },
    passage: {
        name: 'Moonlit Passage',
        gift: 'Keep your promised vote today to hide your outgoing visit from the Lamplighter and Tracker next night. A different vote, abstention or missed ballot earns no passage. This does not stop role readings, curses, attacks or disruption. No benefit if the match ends before next night.',
    },
};
export function bargainGift(bargain: FaeBargain, targetName: string): string {
    if (bargain.kind === 'voice') {
        if (!bargain.gift_target)
            return 'Your current curse and haunting are cleansed immediately upon acceptance, even if you break your promise.';
        return `Ballot to reveal: ${targetName}. ${bargains.voice.gift}`;
    }
    return bargains[bargain.kind].gift;
}
export function bargainPromise(kind: BargainKind, name: string): string {
    if (kind === 'voice')
        return 'Explicitly abstain in today’s vote. Missing the vote does not count.';
    if (kind === 'lantern')
        return `Publicly accuse ${name} during today’s discussion.`;
    return `Vote to banish ${name} today. Your actual ballot counts, including any Misdirection.`;
}
