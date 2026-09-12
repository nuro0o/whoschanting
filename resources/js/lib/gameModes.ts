import { t } from '../i18n/index.ts';
export interface ModeSetup {
    mode: 'classic' | 'hard' | 'chaos' | 'paranoia' | 'custom';
    classic_variant: 'classic' | 'illusions';
    chaos_variant: 'wildcards' | 'maelstrom';
    roles: Record<string, number>;
}

export const townRoleIds = [
    'vigilante',
    'townsperson',
    'oracle',
    'warden',
    'lamplighter',
    'medium',
    'bellkeeper',
    'exorcist',
    'oathkeeper',
    'tracker',
    'herbalist',
];
export const cultRoleIds = [
    'acolyte',
    'veilweaver',
    'dreamweaver',
    'phantasm',
    'counterfeiter',
];
export function defaultModeSetup(): ModeSetup {
    return {
        mode: 'classic',
        classic_variant: 'classic',
        chaos_variant: 'wildcards',
        roles: { oracle: 1, veilweaver: 1, townsperson: 1 },
    };
}
export function copyModeSetup(
    setup?: ModeSetup,
    roster?: 'classic' | 'illusions',
): ModeSetup {
    const value = setup ?? {
        ...defaultModeSetup(),
        classic_variant: roster ?? 'classic',
    };
    return { ...value, roles: { ...value.roles } };
}
export function modeSubmission(setup: ModeSetup): ModeSetup {
    return {
        ...setup,
        roles: setup.mode === 'custom' ? { ...setup.roles } : {},
    };
}
export function rosterTotals(roles: Record<string, number>) {
    const total = (ids: string[]) =>
        ids.reduce((sum, id) => sum + (roles[id] ?? 0), 0);
    const town = total(townRoleIds);
    const cult = total(cultRoleIds);
    return { town, cult, total: town + cult };
}
export function customModeError(
    setup: ModeSetup,
    min = 3,
    max = 15,
): string | null {
    if (setup.mode !== 'custom') return null;
    const counts = Object.values(setup.roles);
    if (
        counts.some(
            (count) => !Number.isInteger(count) || count < 1 || count > max,
        )
    )
        return t('gameModes.errors.role_count', { max });
    const totals = rosterTotals(setup.roles);
    if (!totals.town || !totals.cult) return t('gameModes.errors.factions');
    if (totals.total < min || totals.total > max)
        return t('gameModes.errors.total', { min, max, total: totals.total });
    return null;
}
export function modeName(
    setup?: ModeSetup,
    roster?: 'classic' | 'illusions',
): string {
    const current = copyModeSetup(setup, roster);
    if (current.mode === 'classic')
        return current.classic_variant === 'illusions'
            ? t('gameModes.names.illusions')
            : t('gameModes.names.classic');
    if (current.mode === 'chaos')
        return current.chaos_variant === 'maelstrom'
            ? t('gameModes.names.maelstrom')
            : t('gameModes.names.wildcards');
    if (current.mode === 'paranoia') return t('gameModes.names.paranoia');
    return current.mode === 'hard'
        ? t('gameModes.names.hard')
        : t('gameModes.names.custom');
}
export const chaosEvents = {
    mirrors: {
        name: 'Hall of mirrors',
        description:
            'Base Oracle readings are reversed tonight. Veils then reverse that reading; a Counterfeiter’s forgery overrides both.',
    },
    sanctuary: {
        name: 'Sanctuary',
        description:
            'No new curses can take hold tonight. Haunting, forgery, disruption and chanting still work.',
    },
    eclipse: {
        name: 'Eclipse',
        description:
            'All visits and tracks are hidden tonight. Lamplighters and Trackers see no visible visits.',
    },
};
export type ChaosEvent = keyof typeof chaosEvents;
