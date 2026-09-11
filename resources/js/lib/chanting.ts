import type { ChaosEvent, ModeSetup } from './gameModes';
import type { MatchReward, PublicCustomization } from './progression';
import type { TableChatMessage } from './tableChat';

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
    customization?: PublicCustomization | null;
    oath?: { day: number; target_id: string } | null;
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
    tracked_target_id?: string | null;
    forged_alignment?: string | null;
    forged?: boolean;
    visits_hidden?: boolean;
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
    used_ability?: boolean;
    disrupted?: boolean;
    ritual_blocked?: boolean;
    prevented_steps?: number | null;
    true_alignment?: string | null;
}
export type PrivateNightResult = { day: number; target: string } & (
    | { kind?: 'alignment'; alignment: string }
    | { kind: 'visits'; visited: boolean }
    | { kind: 'tracking'; visited_target: string | null }
    | { kind: 'herbs' }
    | { kind: 'protection' }
    | { kind: 'spirit'; alignment: string }
    | { kind: 'bell'; prevented: number }
    | { kind: 'disruption' }
    | { kind: 'disrupted' }
    | { kind: 'haunting' }
    | { kind: 'forgery'; alignment: string }
    | { kind: 'exorcism' }
    | { kind: 'oath'; kept: boolean }
);

export interface MatchRecapData {
    mode_setup?: ModeSetup;
    roster?: 'classic' | 'illusions';
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
        discussion?: {
            type: 'exorcise' | 'oath';
            player_id: string;
            target_id: string;
        }[];
        night?: {
            chaos_event?: ChaosEvent | null;
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
                oath_kept?: boolean | null;
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
    scene?: {
        kind: 'rings' | 'towers' | 'lanterns';
        difficulty?: 1 | 2 | 3;
        guided?: boolean;
        objects?: { option_id: string; x: number; z: number; height: number }[];
        rings?: { label: string; start: number; options: string[] }[];
    };
}
export interface Curse {
    id: string;
    type: 'puzzle' | 'mist' | 'misdirection';
    level: number;
    day: number;
    stage?: number;
    stages?: number;
    challenge: CurseChallenge | null;
}
export interface RoomState {
    mode_setup?: ModeSetup;
    mode_preview?: {
        possible_roles?: Record<string, string[]>;
        team_counts?: { town: number; cult: number } | null;
        discussion_seconds?: { early: number; middle: number; late: number };
        roles: Record<string, number> | null;
        required_players: number | null;
        error: string | null;
    };
    chaos_event?: ChaosEvent | null;
    roster?: 'classic' | 'illusions';
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
        customization?: PublicCustomization | null;
        account_progression?: boolean;
        match_reward?: MatchReward | null;
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
        ability_used?: boolean;
        haunting?: { day: number; seat_id: string } | null;
        oath_protected?: boolean;
        submitted: boolean;
        curse: Curse | null;
        curse_notice: string | null;
    };
    messages: TableChatMessage[];
    log: string[];
    rules: {
        min_players: number;
        max_players: number;
        seconds: Record<string, number>;
        ritual_goals: { players: number; steps: number }[];
        cultists_by_player_count: Record<number, number>;
        small_gathering_max_players: number;
        town_roles_min_players?: Record<string, number>;
        cult_roles_min_players?: Record<string, number>;
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
    tracker: {
        name: 'The Tracker',
        subtitle: 'Town · follower of midnight footsteps',
        symbol: '⌭',
        description:
            'Each night, follow another living player. At dawn, privately learn the name of the player they targeted, or that no visit was visible. Submitted attempts count even if disrupted. Phantasm concealment and the Eclipse hide tracks. You learn no role or ability.',
    },
    herbalist: {
        name: 'The Herbalist',
        subtitle: 'Town · keeper of protective remedies',
        symbol: '⌭',
        description:
            'Once per match at night, protect every living player against all new curses. No target is needed. This does not remove existing curses or stop haunting, forgery or disruption. The ability is spent even on a quiet night or if disrupted. Keep watch to save it.',
    },
    phantasm: {
        name: 'The Phantasm',
        subtitle: 'Cult · architect of false visions',
        symbol: '◌',
        description:
            'Once per match, haunt another living player instead of chanting. Their outgoing visits are hidden from the Lamplighter and Tracker tonight, and false faces, shadows and distant chanting follow them through discussion. These visions reveal no allegiance. Names, actions and your own role stay truthful. The haunting fades before voting; disruption stops it. Otherwise, chant without a target.',
    },
    counterfeiter: {
        name: 'The Counterfeiter',
        subtitle: 'Cult · author of false evidence',
        symbol: '✎',
        description:
            'Once per match, forgo chanting to choose another living player and how they appear to the Oracle tonight: town or cult. This overrides a veil, but cannot alter the Medium or anyone’s true role. It is spent even if nobody investigates the target or you are disrupted. Otherwise, chant without a target.',
    },
    exorcist: {
        name: 'The Exorcist',
        subtitle: 'Town · clearer of troubled minds',
        symbol: '✣',
        description:
            'Once per match during discussion, cleanse another living player of their active curse and Phantasm haunting. The ability is spent even if they had neither. It does not rewrite earlier Oracle readings or protect against future afflictions. Break your own blocking curse before acting. Keep watch at night.',
    },
    oathkeeper: {
        name: 'The Oathkeeper',
        subtitle: 'Town · bound by a public promise',
        symbol: '⚖',
        description:
            'Each discussion, publicly promise to vote for another living player. The oath cannot be changed. If your actual ballot matches, you gain protection against all new curses next night. Abstaining, missing the vote or a redirected ballot breaks it. Protection does not stop haunting, forgery or disruption. Keep watch at night.',
    },
    veilweaver: {
        name: 'The Veilweaver',
        subtitle: 'Cult · master of misdirection',
        description:
            'Chant for the ritual. You may veil another living player: their alignment appears reversed to the Oracle tonight, and your chosen curse takes hold at dawn. Soul Bind traps them behind rings and towers; Mind Mist clouds their thoughts with lost lanterns. At ritual level 3, Misdirection can redirect their next target unless they untangle its rings first.',
        symbol: '◈',
    },
    acolyte: {
        name: 'The Acolyte',
        subtitle: 'Cult · keeper of the ritual',
        description:
            'Chant each night to complete your shared mission. You may curse another living player at dawn with Soul Bind or Mind Mist. Their seals grow harder as the ritual strengthens. At ritual level 3, Misdirection can redirect their next target unless they untangle its rings first.',
        symbol: '✧',
    },
    oracle: {
        name: 'The Oracle',
        subtitle: 'Town · seeker of secrets',
        description:
            'Once per match, investigate another living player at night to learn their apparent alignment. Keep watch to save your investigation for later. Submitting it spends the ability even if disrupted. Beware: the Veilweaver can reverse a reading, and the Counterfeiter can forge one when that role is in play.',
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
            'Watch another living player tonight. At dawn, privately learn whether anyone else was seen targeting them. Your own watch does not count. You learn no visitor names, roles, or abilities; even a blocked curse counts as a visit. The Phantasm can conceal a visitor when that role is in play.',
        symbol: '☼',
    },
    medium: {
        name: 'The Medium',
        subtitle: 'Town · listener beyond the veil',
        description:
            'Once per match, contact a banished player at night to privately learn their true alignment at dawn. Veils cannot change this result. You may keep watch instead and save your ability. A disrupted attempt still spends it.',
        symbol: '☽',
    },
    dreamweaver: {
        name: 'The Dreamweaver',
        subtitle: 'Cult · trespasser in dreams',
        description:
            'Chant without a target, or once per match give up chanting to disrupt another living player’s night action. Disruptions resolve first: the target’s other ability or chant fails, and they privately learn their submitted action was disrupted. Warden protection does not stop it. Choosing disruption also breaks a mission that requires every cultist to chant.',
        symbol: '≋',
    },
    bellkeeper: {
        name: 'The Bellkeeper',
        subtitle: 'Town · the last warning',
        description:
            'Once per match, ring the bell at night to prevent one ritual step earned that night. It never removes existing progress. Ringing when no steps are earned still spends your ability, as does a disrupted attempt. Keep watch to save it for later.',
        symbol: '♧',
    },
};
