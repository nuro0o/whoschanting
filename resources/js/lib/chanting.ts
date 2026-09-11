export class RoomError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

export function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

export async function roomRequest<T>(url: string, body?: object): Promise<T> {
    const response = await fetch(url, {
        method: body ? 'POST' : 'GET',
        credentials: 'same-origin',
        signal: AbortSignal.timeout(12_000),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    }).catch(() => {
        throw new RoomError(
            'The village took too long to respond. Check your connection and try again.',
            0,
        );
    });
    const data = await response.json().catch(() => {
        throw new RoomError(
            'The village sent an incomplete response. Please try again.',
            response.status,
        );
    });
    if (!response.ok) {
        const validation = data.errors
            ? Object.values(data.errors).flat()[0]
            : null;
        throw new RoomError(
            response.status === 419
                ? 'Your session expired. Refresh this page and try again.'
                : String(
                      validation ||
                          data.message ||
                          'The village could not be reached. Please try again.',
                  ),
            response.status,
        );
    }
    return data as T;
}

export interface Player {
    id: string;
    name: string;
    alive: boolean;
    ready: boolean;
    character: string;
    discussion_ready: boolean;
    role?: string;
    alignment?: string;
}
export interface RecapNightAction {
    player_id: string;
    role: string;
    target_id: string | null;
    chosen_target_id?: string | null;
    curse_type?: 'puzzle' | 'mist' | 'misdirection' | null;
    submitted: boolean;
    contributed: boolean;
    apparent_alignment: string | null;
    veiled: boolean;
    curse_blocked?: boolean;
    prevented_curse?: boolean;
    visited?: boolean | null;
}
export type PrivateNightResult = { day: number; target: string } & (
    | { kind?: 'alignment'; alignment: string }
    | { kind: 'visits'; visited: boolean }
    | { kind: 'protection' }
);

export interface MatchRecapData {
    rules_version: string;
    player_count: number;
    mission: { id: string; name: string; description: string };
    ritual_goal: number;
    nights: number;
    duration_seconds: number | null;
    complete: boolean;
    missed_actions: { night: number; vote: number };
    rounds: {
        day: number;
        night?: {
            actions: RecapNightAction[];
            gained: number;
            tokens: number;
        };
        vote?: {
            ballots: {
                player_id: string;
                target_id: string | null;
                chosen_target_id?: string | null;
                submitted: boolean;
            }[];
            banished_id: string | null;
        };
    }[];
}
export interface CurseChallenge {
    kind: string;
    title: string;
    instruction: string;
    clues: string[];
    options: { id: string; label: string }[];
    answer_length: number;
}
export interface Curse {
    id: string;
    type: 'puzzle' | 'mist' | 'misdirection';
    level: number;
    day: number;
    challenge: CurseChallenge | null;
}
export interface RoomState {
    id: number;
    code: string;
    phase: 'lobby' | 'reveal' | 'night' | 'discussion' | 'voting' | 'finished';
    phase_id: number;
    revision: number;
    day: number;
    deadline: string | null;
    server_time: string;
    host_id: string;
    ritual: {
        tokens: number;
        threshold: number;
        level: number;
        final_vote: boolean;
    };
    winner: 'town' | 'cult' | null;
    win_reason: string | null;
    recap: MatchRecapData | null;
    players: Player[];
    me: {
        id: string;
        name: string;
        alive: boolean;
        character: string;
        role: string | null;
        alignment: 'cult' | 'town' | null;
        mission: { id: string; name: string; description: string } | null;
        allies: { id: string; name: string; role: string }[];
        results: PrivateNightResult[];
        previous_protection_target?: string | null;
        submitted: boolean;
        curse: Curse | null;
        curse_notice: string | null;
    };
    messages: { id: string; name: string; body: string; day: number }[];
    log: string[];
    rules: {
        min_players: number;
        max_players: number;
        seconds: Record<string, number>;
        ritual_goals: { players: number; steps: number }[];
        cultists_by_player_count: Record<number, number>;
        small_gathering_max_players: number;
        town_roles_min_players?: Record<string, number>;
    };
}

export interface Character {
    id: string;
    name: string;
}
export const characterIds = [
    'mariner',
    'botanist',
    'lamplighter',
    'archivist',
    'baker',
    'astronomer',
    'ferryman',
    'musician',
    'drifter',
    'whisperer',
    'smuggler',
    'lookout',
    'trickster',
    'locksmith',
    'prowler',
    'stranger',
];
export const defaultCharacters: Character[] = characterIds.map((id) => ({
    id,
    name: `The ${id[0].toUpperCase()}${id.slice(1)}`,
}));

export const roles: Record<
    string,
    { name: string; subtitle: string; description: string; symbol: string }
> = {
    veilweaver: {
        name: 'The Veilweaver',
        subtitle: 'Cult · master of misdirection',
        description:
            'Chant for the ritual. You may veil another living player: their alignment appears reversed to the Oracle tonight, and a random eldritch curse takes hold at dawn. Curses grow stronger with the ritual.',
        symbol: '◈',
    },
    acolyte: {
        name: 'The Acolyte',
        subtitle: 'Cult · keeper of the ritual',
        description:
            'Chant each night to complete your shared mission. You may also curse another living player: a random eldritch curse takes hold at dawn and grows stronger with the ritual. Keep your intentions hidden.',
        symbol: '✧',
    },
    oracle: {
        name: 'The Oracle',
        subtitle: 'Town · seeker of secrets',
        description:
            'Each night, investigate another living player’s alignment. Beware: the Veilweaver can reverse one reading for a night.',
        symbol: '☾',
    },
    townsperson: {
        name: 'The Townsperson',
        subtitle: 'Town · a watchful neighbor',
        description:
            'Keep watch at night. Read the room by day, compare stories, and vote to banish every cultist. A full ritual leaves one final discussion and vote to stop the summoning.',
        symbol: '✦',
    },
    warden: {
        name: 'The Warden',
        subtitle: 'Town · guardian against curses',
        description:
            'Protect another living player from all new curses tonight, or skip protection. You cannot protect the same player on consecutive nights. Protection does not stop veils, investigations, or ritual progress, and does not remove an existing curse.',
        symbol: '◇',
    },
    lamplighter: {
        name: 'The Lamplighter',
        subtitle: 'Town · watcher of midnight visitors',
        description:
            'Watch another living player tonight. At dawn, privately learn whether anyone else targeted them. Your own watch does not count. You learn no visitor names, roles, or abilities; even a blocked curse counts as a visit.',
        symbol: '☼',
    },
};
