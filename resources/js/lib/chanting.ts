import { t } from '../i18n/index.ts';
import type { FaeState } from './faeCourt';
import {
    expansionRoles,
    type Alignment,
    type ExpansionAvailability,
    type ExpansionState,
} from './expansions.ts';
import type { ChaosEvent, ModeSetup } from './gameModes';
import type { MatchReward, PublicCustomization } from './progression';
import type { TableChatMessage } from './tableChat';
import type { RitualCosmeticEvent } from './ritualSceneState';

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
        throw new RoomError(t('roomErrors.timeout'), 0);
    });
    const data = await response.json().catch(() => {
        throw new RoomError(t('roomErrors.incomplete'), response.status);
    });
    if (!response.ok) {
        const validation = data.errors
            ? Object.values(data.errors).flat()[0]
            : null;
        throw new RoomError(
            response.status === 419
                ? t('roomErrors.session')
                : String(
                      validation || data.message || t('roomErrors.unreachable'),
                  ),
            response.status,
        );
    }
    return data as T;
}

export function eliminationLabel(reason?: 'shot' | 'guilt' | null): string {
    return reason === 'guilt'
        ? 'Left in guilt'
        : reason === 'shot'
          ? 'Shot'
          : 'Banished';
}

export interface Player {
    connected?: boolean;
    afk?: boolean;
    in_room?: boolean;
    elimination_reason?: 'shot' | 'guilt' | null;
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
    expansion_action?: string | null;
    secondary_target_id?: string | null;
    relic_id?: string | null;
    shot_fired?: boolean;
    guilty?: boolean;
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
    | { kind: 'expansion'; text: string }
    | { kind?: 'alignment'; alignment: string }
    | { kind: 'visits'; visited: boolean }
    | { kind: 'ballot'; submitted: boolean; voted_for: string | null }
    | { kind: 'passage' }
    | { kind: 'tracking'; visited_target: string | null }
    | { kind: 'herbs' }
    | { kind: 'shot'; guilty: boolean }
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

export interface PublicClaim {
    id: string;
    player_id: string;
    day: number;
    role: string;
    body: string;
    target_id: string | null;
}
export interface DiscussionResponse {
    id: string;
    player_id: string;
    day: number;
    prompt_id: string;
    question?: string;
    body: string;
    target_id: string | null;
}
export interface Prediction {
    cultist_ids: string[];
    winner: 'town' | 'cult';
    day: number;
}
export interface PredictionResult extends Prediction {
    player_id: string;
    correct_picks: number;
    total_cultists: number;
    exact: boolean;
    winner_correct: boolean;
    informed: boolean;
}
export interface MatchFeedbackResponse {
    engagement: 'engaged' | 'mixed' | 'waiting';
    body: string;
}
export interface LastWordsRecord {
    accusations: { player_id: string; target_id: string }[];
    accused_ids: string[];
    defenses: { player_id: string; body: string }[];
}
export interface MatchRecapData {
    expansion?: ExpansionState | null;
    winners?: string[];
    fae?: FaeState | null;
    claims?: PublicClaim[];
    responses?: DiscussionResponse[];
    predictions?: PredictionResult[];
    winner?: 'town' | 'cult' | null;
    win_reason?: string | null;
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
        last_words?: LastWordsRecord;
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
    set_name?: string;
    challenge: CurseChallenge | null;
}
export interface RoomState {
    expansion_catalog?: ExpansionAvailability[];
    expansion?: ExpansionState | null;
    expansions?: {
        fae_court: {
            active?: boolean;
            available: boolean;
            min_players: number;
        };
    };
    fae?: FaeState | null;
    winners?: string[];
    cosmetics?: { table: string; events: RitualCosmeticEvent[] };
    visibility: 'private' | 'public';
    pin_required?: boolean;
    table?: {
        last_words?: LastWordsRecord | null;
        claims: PublicClaim[];
        prompt: { id: string; question: string } | null;
        responses: DiscussionResponse[];
        extension: { voter_ids: string[]; used: boolean; seconds: number };
        predictions: PredictionResult[];
    };
    match_id: string | null;
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
    phase:
        | 'lobby'
        | 'reveal'
        | 'night'
        | 'bargains'
        | 'discussion'
        | 'last_words'
        | 'voting'
        | 'finished';
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
        afk?: boolean;
        afk_prompt_deadline?: string | null;
        prediction?: Prediction | null;
        feedback?: MatchFeedbackResponse | null;
        elimination_reason?: 'shot' | 'guilt' | null;
        customization?: PublicCustomization | null;
        account_progression?: boolean;
        match_reward?: MatchReward | null;
        id: string;
        name: string;
        alive: boolean;
        character: string;
        role: string | null;
        alignment: Alignment | null;
        mission: { id: string; name: string; description: string } | null;
        allies: { id: string; name: string; role: string }[];
        results: PrivateNightResult[];
        previous_protection_target?: string | null;
        ability_used?: boolean;
        haunting?: { day: number; seat_id: string } | null;
        oath_protected?: boolean;
        fae_protected?: boolean;
        fae_passage?: boolean;
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

export {
    characterIds,
    defaultCharacters,
    seasonalCharacters,
    expansionCharacters,
    type Character,
} from './characters.ts';

export const roles: Record<
    string,
    { name: string; subtitle: string; description: string; symbol: string }
> = {
    ...expansionRoles,
    fae_collector: {
        name: 'The Fae Collector',
        subtitle: 'Fae Court - keeper of second chances',
        symbol: '◇',
        description:
            'Once per match, anonymously renew a declined, expired or broken bargain from an earlier night with a different living player outside the Court. Keep its original gift and promise. Your renewal is spent when submitted, even if disrupted or voided. Share seals and private bargain history with the Broker; a partner can earn only one seal for the Court. Break Misdirection before renewing, or keep watch.',
    },
    fae_broker: {
        name: 'The Fae Broker',
        subtitle: 'Fae Court · keeper of secret bargains',
        symbol: '❧',
        description:
            'Blend in by day. Each night, anonymously offer one living player a gift for a voting promise. They accept or decline after night actions resolve. Earn seals from different fulfilled partners to share Town or Cult victory. An ordinary Oracle reading identifies you as Fae; a veil makes you appear Cult and a forgery can make you appear Town or Cult. Break Misdirection before offering, or keep watch.',
    },
    vigilante: {
        name: t('roles.vigilante.name'),
        subtitle: t('roles.vigilante.subtitle'),
        symbol: '⌖',
        description: t('roles.vigilante.description'),
    },
    tracker: {
        name: t('roles.tracker.name'),
        subtitle: t('roles.tracker.subtitle'),
        symbol: '⌭',
        description: t('roles.tracker.description'),
    },
    herbalist: {
        name: t('roles.herbalist.name'),
        subtitle: t('roles.herbalist.subtitle'),
        symbol: '⌭',
        description: t('roles.herbalist.description'),
    },
    phantasm: {
        name: t('roles.phantasm.name'),
        subtitle: t('roles.phantasm.subtitle'),
        symbol: '◌',
        description: t('roles.phantasm.description'),
    },
    counterfeiter: {
        name: t('roles.counterfeiter.name'),
        subtitle: t('roles.counterfeiter.subtitle'),
        symbol: '✎',
        description: t('roles.counterfeiter.description'),
    },
    exorcist: {
        name: t('roles.exorcist.name'),
        subtitle: t('roles.exorcist.subtitle'),
        symbol: '✣',
        description: t('roles.exorcist.description'),
    },
    oathkeeper: {
        name: t('roles.oathkeeper.name'),
        subtitle: t('roles.oathkeeper.subtitle'),
        symbol: '⚖',
        description: t('roles.oathkeeper.description'),
    },
    veilweaver: {
        name: t('roles.veilweaver.name'),
        subtitle: t('roles.veilweaver.subtitle'),
        description: t('roles.veilweaver.description'),
        symbol: '◈',
    },
    acolyte: {
        name: t('roles.acolyte.name'),
        subtitle: t('roles.acolyte.subtitle'),
        description: t('roles.acolyte.description'),
        symbol: '✧',
    },
    oracle: {
        name: t('roles.oracle.name'),
        subtitle: t('roles.oracle.subtitle'),
        description: t('roles.oracle.description'),
        symbol: '☾',
    },
    townsperson: {
        name: t('roles.townsperson.name'),
        subtitle: t('roles.townsperson.subtitle'),
        description: t('roles.townsperson.description'),
        symbol: '✦',
    },
    warden: {
        name: t('roles.warden.name'),
        subtitle: t('roles.warden.subtitle'),
        description: t('roles.warden.description'),
        symbol: '◇',
    },
    lamplighter: {
        name: t('roles.lamplighter.name'),
        subtitle: t('roles.lamplighter.subtitle'),
        description: t('roles.lamplighter.description'),
        symbol: '☼',
    },
    medium: {
        name: t('roles.medium.name'),
        subtitle: t('roles.medium.subtitle'),
        description: t('roles.medium.description'),
        symbol: '☽',
    },
    dreamweaver: {
        name: t('roles.dreamweaver.name'),
        subtitle: t('roles.dreamweaver.subtitle'),
        description: t('roles.dreamweaver.description'),
        symbol: '≋',
    },
    bellkeeper: {
        name: t('roles.bellkeeper.name'),
        subtitle: t('roles.bellkeeper.subtitle'),
        description: t('roles.bellkeeper.description'),
        symbol: '♧',
    },
};
