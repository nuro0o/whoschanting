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
            name: 'Made in the looking glass',
            earned: false,
            characters: [],
        },
        { id: 'classics', name: 'Classics', earned: false, characters: [] },
        { id: 'levelup', name: 'Level Up', earned: true, characters: [] },
    ];

    for (const character of characters) {
        const legacyIndex = legacyIds.indexOf(character.id);
        let id: string;
        let name = 'More characters';
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
                'Seasonal characters';
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
