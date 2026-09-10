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
    ritual: { tokens: number; threshold: number };
    winner: 'town' | 'cult' | null;
    win_reason: string | null;
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
        results: { day: number; target: string; alignment: string }[];
        submitted: boolean;
    };
    messages: { id: string; name: string; body: string; day: number }[];
    log: string[];
    rules: {
        min_players: number;
        max_players: number;
        seconds: Record<string, number>;
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
            'Chant for the ritual. Each night, you may veil another living player: their alignment appears reversed to the Oracle for that night.',
        symbol: '◈',
    },
    acolyte: {
        name: 'The Acolyte',
        subtitle: 'Cult · keeper of the ritual',
        description:
            'Chant each night to complete your shared mission. Keep your fellow cultists alive and your true intentions hidden.',
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
            'Keep watch at night. Read the room by day, compare stories, and vote to banish every cultist before the ritual is complete.',
        symbol: '✦',
    },
};
