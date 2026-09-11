export interface EquippedCosmetics {
    title: string;
    frame: string;
    accent: string;
    character: string | null;
}
export interface PublicCustomization {
    level: number;
    title: string;
    title_name: string;
    frame: string;
    accent: string;
}
export interface MatchReward {
    match_id: string;
    season_id: string;
    xp: number;
    won: boolean;
    earned_at: string;
    achievements: string[];
    level_before: number;
    level_after: number;
}
export interface Cosmetic {
    id: string;
    name: string;
    requirement: string;
    unlocked: boolean;
}
export interface ProgressionData {
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
    cosmetics: { titles: Cosmetic[]; frames: Cosmetic[]; accents: Cosmetic[] };
    recent_rewards: MatchReward[];
}
export const cosmeticAccents: Record<string, string> = {
    sea: '#a8bd9d',
    moon: '#b7b6d0',
    ember: '#c77d61',
    gold: '#d2b46c',
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
