import type { Player, RoomState } from './chanting';

export type Alignment =
    | 'town'
    | 'cult'
    | 'fae'
    | 'drowned'
    | 'gilded'
    | 'choir'
    | 'carnival';
export interface ExpansionMetadata {
    id: string;
    name: string;
    alignment: Alignment;
    description: string;
    instructions: string;
    min_players: number;
    roles: string[];
    goal: number;
}
export interface ExpansionAvailability extends ExpansionMetadata {
    active: boolean;
    available: boolean;
}
export interface ExpansionChoice {
    id: string;
    label: string;
    description: string;
    target: 'none' | 'living' | 'other';
    secondary?: boolean;
    relic?: 'any' | 'owned';
}
export interface ExpansionState extends ExpansionMetadata {
    progress: number;
    secured: boolean;
    tide_day?: number;
    marks?: string[];
    inventory?: string[];
    court_inventory?: { relic_id: string; holder_id: string }[];
    acts?: string[];
    plan?: { action: string; target_id: string | null } | null;
    night_choices: ExpansionChoice[];
    events?: { day: number; text: string }[];
}
export const factionNames: Record<string, string> = {
    town: 'Town',
    cult: 'Cult',
    fae: 'Fae Court',
    drowned: 'The Drowned',
    gilded: 'The Gilded Hand',
    choir: 'The Hollow Choir',
    carnival: 'The Carnival',
};
export const factionStyle: Record<
    string,
    { symbol: string; color: string; motto: string }
> = {
    'fae-court': {
        symbol: '❧',
        color: '#bdcfae',
        motto: 'Every gift has a promise.',
    },
    drowned: {
        symbol: '≋',
        color: '#9bd2df',
        motto: 'The tide remembers every name.',
    },
    'gilded-hand': {
        symbol: '◇',
        color: '#ebc87c',
        motto: 'Every hand hides a treasure.',
    },
    'hollow-choir': {
        symbol: '♫',
        color: '#cbb9ed',
        motto: 'Another voice beneath the ritual.',
    },
    carnival: {
        symbol: '✦',
        color: '#f0b39b',
        motto: 'Make a scene. Steal the finale.',
    },
};
export const relicNames: Record<string, string> = {
    silver_key: 'Silver Key',
    glass_eye: 'Glass Eye',
    sun_coin: 'Sun Coin',
};
export function victoryTitle(
    winners?: string[],
    winner?: string | null,
): string {
    const names = (winners?.length ? winners : winner ? [winner] : []).map(
        (id) => factionNames[id] ?? id,
    );
    return names.length > 1
        ? `${names.join(' and ')} share victory`
        : `${names[0] ?? 'The village'} prevails`;
}
export function isExpansionRole(role?: string | null): boolean {
    return /^(drowned_|gilded_|choir_|carnival_)/.test(role ?? '');
}
export function expansionTargets(
    state: RoomState,
    choice: ExpansionChoice,
): Player[] {
    if (choice.target === 'none') return [];
    return state.players.filter(
        (p) =>
            p.alive &&
            (choice.target !== 'other' || p.id !== state.me.id) &&
            (choice.id !== 'mark' ||
                (p.id !== state.me.id &&
                    !state.me.allies.some((ally) => ally.id === p.id) &&
                    !state.expansion?.marks?.includes(p.id))) &&
            (choice.id !== 'ferry' || state.expansion?.marks?.includes(p.id)),
    );
}
export const expansionRoles = {
    drowned_tidecaller: {
        name: 'The Tidecaller',
        subtitle: 'Drowned · keeper of the rising tide',
        symbol: '≋',
        description:
            'Secretly mark another living player each night. Three living marked outsiders at a tide secure your shared victory. The tide rises every third day after voting. Marks never change a role or kill their holder; sounding discovers them and cleansing washes them away.',
    },
    drowned_ferryman: {
        name: 'The Ferryman',
        subtitle: 'Drowned · bearer of the waterline',
        symbol: '⚓',
        description:
            'Move an existing mark from one living player to another living, unmarked outsider. Protect the spread from cleansing and banishment. Three living marked outsiders at a tide secure the Drowned a shared victory.',
    },
    gilded_lifter: {
        name: 'The Lifter',
        subtitle: 'Gilded Hand · fingers in every pocket',
        symbol: '◇',
        description:
            'Choose another living player and a relic to steal. The theft succeeds only if they hold that relic. Your living teammates must hold the Silver Key, Glass Eye and Sun Coin when the match ends to share victory. Relic transfers leave visits; conflicting moves of the same relic fail.',
    },
    gilded_appraiser: {
        name: 'The Appraiser',
        subtitle: 'Gilded Hand · an eye for hidden treasures',
        symbol: '⌕',
        description:
            'Choose a relic and privately learn its holder at dawn. Trade that knowledge with your Lifter. Any relic holder can hand a relic to another living player instead of using their usual night ability. All three must be in living Gilded hands at match end.',
    },
    choir_cantor: {
        name: 'The Cantor',
        subtitle: 'Hollow Choir · a voice below the chant',
        symbol: '♫',
        description:
            'Siphon one newly earned Cult ritual step into an echo, but only when the Cult earns at least two new steps. The Cult always keeps at least one. Three echoes secure a shared victory; you need the Cult alive and chanting to make progress.',
    },
    choir_resonant: {
        name: 'The Resonant',
        subtitle: 'Hollow Choir · listener in the dark',
        symbol: '♬',
        description:
            'Listen to another living player to learn whether they chanted, without revealing allegiance. Once per match, resonate instead: add one echo to an effective Cantor siphon when at least three new ritual steps are available. Resonance is spent even if disrupted or unsuccessful.',
    },
    carnival_harlequin: {
        name: 'The Harlequin',
        subtitle: 'Carnival · trouble takes a bow',
        symbol: '✦',
        description:
            'Choose an act at night. Taunt: receive another player’s accusation, submit a ballot and survive that day’s vote. Tie: vote for one of at least two named candidates tied at the top, with nobody banished. Each different act counts only once for the Carnival.',
    },
    carnival_augur: {
        name: 'The Augur',
        subtitle: 'Carnival · the last word before the curtain',
        symbol: '✧',
        description:
            'At night, predict another living player’s banishment in that day’s vote. A correct prediction earns the Foretell act once. Complete Taunt, Tie and Foretell across at least two days to secure the Carnival’s shared finale. Your prediction never forces a ballot.',
    },
};
