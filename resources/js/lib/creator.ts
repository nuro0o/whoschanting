import type { Cosmetic } from './progression';

export interface CreatorRecipe {
    version: 1;
    face: string;
    hair: string;
    hat: string;
    outfit: string;
    detail: string;
    skin: string;
    hair_color: string;
    outfit_color: string;
}
export type CreatorField = Exclude<keyof CreatorRecipe, 'version'>;
export interface CreatorCatalog {
    default: CreatorRecipe;
    options: Record<CreatorField, Cosmetic[]>;
}
export const defaultCreator: CreatorRecipe = {
    version: 1,
    face: 'harbor',
    hair: 'cropped',
    hat: 'none',
    outfit: 'mariner',
    detail: 'none',
    skin: 'warm',
    hair_color: 'ink',
    outfit_color: 'sea',
};
export const creatorPalettes: Record<string, Record<string, string>> = {
    skin: {
        porcelain: '#e4c5b0',
        warm: '#ba8763',
        umber: '#8a5740',
        deep: '#55382e',
    },
    hair_color: {
        ink: '#282b29',
        ash: '#a5a39b',
        copper: '#aa5c36',
        flax: '#d9bf83',
    },
    outfit_color: {
        sea: '#49756e',
        ink: '#363c47',
        wine: '#874b54',
        ochre: '#b08a48',
    },
};
export function creatorLockedOptions(
    recipe: CreatorRecipe | null | undefined,
    catalog: CreatorCatalog | undefined,
): Cosmetic[] {
    if (!recipe || !catalog) return [];
    return (Object.keys(catalog.options) as CreatorField[]).flatMap((field) => {
        const option = catalog.options[field].find(
            (item) => item.id === recipe[field],
        );
        return option && !option.unlocked ? [option] : [];
    });
}
