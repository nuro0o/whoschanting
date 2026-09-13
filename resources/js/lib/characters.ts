import type { CreatorRecipe } from './creator';

export interface Character {
    creator?: CreatorRecipe | null;
    id: string;
    name: string;
    unlocked?: boolean;
    requirement?: string;
    seasonal?: boolean;
    collection?: 'classics' | 'levelup' | 'seasonal' | 'custom' | 'expansion';
    expansion?: string;
    season_id?: string | null;
    season_name?: string | null;
    hidden?: boolean;
    role_name?: string;
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
    'tidecaller',
    'cartographer',
    'maskmaker',
    'drowned_regent',
    'seasonal_warden',
    'seasonal_cultist',
    'seasonal_oathkeeper',
    'fae_envoy',
    'drowned_diver',
    'relic_broker',
    'hollow_cantor',
    'carnival_ringmaster',
];
export const expansionCharacters: Record<
    string,
    { name: string; expansion: string; expansionName: string }
> = {
    fae_envoy: {
        name: 'The Thorn Envoy',
        expansion: 'fae-court',
        expansionName: 'The Fae Court',
    },
    drowned_diver: {
        name: 'The Deep Diver',
        expansion: 'drowned',
        expansionName: 'The Drowned',
    },
    relic_broker: {
        name: 'The Relic Broker',
        expansion: 'gilded-hand',
        expansionName: 'The Gilded Hand',
    },
    hollow_cantor: {
        name: 'The Hollow Cantor',
        expansion: 'hollow-choir',
        expansionName: 'The Hollow Choir',
    },
    carnival_ringmaster: {
        name: 'The Ringmaster',
        expansion: 'carnival',
        expansionName: 'The Carnival',
    },
};
export const seasonalCharacters: Record<
    string,
    { name: string; role: string }
> = {
    seasonal_warden: { name: 'The Knight', role: 'Warden' },
    seasonal_cultist: { name: 'The Dark Elf', role: 'Cultist' },
    seasonal_oathkeeper: { name: 'The Paladin', role: 'Oathkeeper' },
};
const characterRequirements: Record<string, string> = {
    tidecaller: 'Reach level 2 (250 lifetime XP)',
    cartographer: 'Reach level 3 (750 lifetime XP)',
    maskmaker:
        'Earn Many faces: complete qualifying matches as 5 different roles',
    drowned_regent: 'Earn Village veteran: complete 25 qualifying matches',
};
export const defaultCharacters: Character[] = characterIds.map((id) => ({
    id,
    name: seasonalCharacters[id]
        ? '?'
        : (expansionCharacters[id]?.name ??
          `The ${id
              .split('_')
              .map((word) => word[0].toUpperCase() + word.slice(1))
              .join(' ')}`),
    unlocked:
        !characterRequirements[id] &&
        !seasonalCharacters[id] &&
        !expansionCharacters[id],
    requirement: expansionCharacters[id]
        ? `Included in ${expansionCharacters[id].expansionName}`
        : (seasonalCharacters[id]?.role ?? characterRequirements[id]),
    collection: expansionCharacters[id]
        ? 'expansion'
        : seasonalCharacters[id]
          ? 'seasonal'
          : characterRequirements[id]
            ? 'levelup'
            : 'classics',
    ...(expansionCharacters[id]
        ? { expansion: expansionCharacters[id].expansion }
        : {}),
    ...(seasonalCharacters[id]
        ? {
              seasonal: true,
              season_id: '2026-Q3',
              season_name: 'Season 3 · 2026',
              hidden: true,
              role_name: seasonalCharacters[id].role,
          }
        : {}),
}));
