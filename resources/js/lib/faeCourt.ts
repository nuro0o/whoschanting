export type BargainKind = 'thorn' | 'voice' | 'lantern';
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
