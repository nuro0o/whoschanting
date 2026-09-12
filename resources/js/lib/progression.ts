import type { CreatorCatalog, CreatorRecipe } from './creator';
import type { Character } from './chanting';

export interface EquippedCosmetics {
    title: string;
    frame: string;
    accent: string;
    background: string;
    character: string | null;
    creator?: CreatorRecipe | null;
}
export interface PublicCustomization {
    creator?: CreatorRecipe | null;
    level: number;
    title: string;
    title_name: string;
    frame: string;
    accent: string;
    background?: string;
}
export interface MatchReward {
    match_id: string;
    season_id: string;
    xp: number;
    coins?: number;
    won: boolean;
    earned_at: string;
    achievements: string[];
    characters?: string[];
    level_before: number;
    level_after: number;
}
export interface Cosmetic {
    id: string;
    name: string;
    requirement: string;
    unlocked: boolean;
}
export interface StoreItem {
    id: string;
    category: 'titles' | 'accents' | 'backgrounds';
    cosmetic_id: string;
    name: string;
    description: string;
    price: number;
    owned: boolean;
    affordable: boolean;
}
export interface StoreData {
    currency: 'Crowns';
    balance: number;
    lifetime_earned: number;
    rewards: {
        per_player: number;
        small_game_max_players: number;
        small_game_win: number;
        large_game_win: number;
    };
    items: StoreItem[];
    recent_transactions: {
        id: number;
        kind: 'match_reward' | 'purchase';
        amount: number;
        balance_after: number;
        item_id: string | null;
        created_at: string;
    }[];
}
export interface ProgressionData {
    store: StoreData;
    seasonal_achievements?: {
        season_id: string;
        ends_at: string;
        achievements: {
            id: string;
            name: string;
            role: string;
            role_name: string;
            earned_at: string | null;
            character: { id: string; name: string } | null;
        }[];
    };
    creator?: CreatorCatalog;
    characters?: Character[];
    profile: {
        xp: number;
        level: number;
        level_xp: number;
        next_level_xp: number;
        matches: number;
        wins: number;
        town_wins: number;
        cult_wins: number;
        roles_played: string[];
        equipped: EquippedCosmetics;
    };
    season: {
        id: string;
        name: string;
        starts_at: string;
        ends_at: string;
        xp: number;
        matches: number;
        wins: number;
        tier: { id: string; name: string };
        next_tier_xp: number | null;
        tiers: { id: string; name: string; xp: number; unlocked: boolean }[];
        history: {
            id: string;
            xp: number;
            matches: number;
            wins: number;
            tier: string;
        }[];
    };
    achievements: {
        id: string;
        name: string;
        description: string;
        current: number;
        target: number;
        earned_at: string | null;
    }[];
    cosmetics: {
        titles: Cosmetic[];
        frames: Cosmetic[];
        accents: Cosmetic[];
        backgrounds: Cosmetic[];
    };
    recent_rewards: MatchReward[];
}
export const cosmeticAccents: Record<string, string> = {
    sea: '#a8bd9d',
    storm: '#7eabb9',
    clay: '#c69b88',
    moon: '#b7b6d0',
    ember: '#c77d61',
    gold: '#d2b46c',
    amethyst: '#a48bcc',
    patina: '#6faeaa',
};
export const cosmeticBackgrounds: Record<string, string> = {
    plain: '#263831',
    harbor: 'linear-gradient(145deg, #c0cebf, #526f73 52%, #213c42)',
    dusk: 'linear-gradient(145deg, #aa859b, #55516b 52%, #252b40)',
    candlelight: 'linear-gradient(145deg, #edd29a, #ac7947 52%, #593a2b)',
    moonlit: 'linear-gradient(145deg, #c9d8e5, #637e9c 52%, #243750)',
    wildwood: 'linear-gradient(145deg, #c1b485, #627c54 52%, #203d32)',
};
export function progressPercent(current: number, target: number): number {
    return Math.max(
        0,
        Math.min(100, target > 0 ? (current / target) * 100 : 100),
    );
}
export function recordDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              timeZone: 'UTC',
          }).format(date);
}
