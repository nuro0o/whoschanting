import { t } from '../i18n/index.ts';
import type { Character } from './chanting';

export interface CharacterCollection {
    id: string;
    name: string;
    earned: boolean;
    characters: Character[];
}

export function characterCollections(
    characters: Character[],
    legacyIds: readonly string[],
): CharacterCollection[] {
    const collections: CharacterCollection[] = [
        {
            id: 'custom',
            name: t('characterCollections.custom'),
            earned: false,
            characters: [],
        },
        {
            id: 'classics',
            name: t('characterCollections.classics'),
            earned: false,
            characters: [],
        },
        {
            id: 'levelup',
            name: t('characterCollections.levelup'),
            earned: true,
            characters: [],
        },
    ];

    for (const character of characters) {
        const legacyIndex = legacyIds.indexOf(character.id);
        let id: string;
        let name = t('characterCollections.other');
        let earned = false;
        if (character.id === 'custom' || character.collection === 'custom') {
            id = 'custom';
        } else if (
            character.season_id ||
            character.seasonal ||
            character.collection === 'seasonal'
        ) {
            id = `season:${character.season_id || 'legacy'}`;
            name =
                character.season_name ||
                character.season_id ||
                t('characterCollections.seasonal');
            earned = true;
        } else if (
            character.collection === 'classics' ||
            (!character.collection && legacyIndex >= 0 && legacyIndex < 16)
        ) {
            id = 'classics';
        } else if (
            character.collection === 'levelup' ||
            (!character.collection && legacyIndex >= 16)
        ) {
            id = 'levelup';
        } else {
            id = 'other';
        }
        let collection = collections.find((item) => item.id === id);
        if (!collection) {
            collection = { id, name, earned, characters: [] };
            collections.push(collection);
        }
        collection.characters.push(character);
    }

    return collections;
}
