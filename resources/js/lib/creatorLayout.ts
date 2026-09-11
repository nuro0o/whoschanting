import { defaultCreator, type CreatorRecipe } from './creator.ts';

export type CreatorLayerField = 'face' | 'outfit' | 'hair' | 'hat' | 'detail';
export type Rect = { x: number; y: number; width: number; height: number };
type Part = {
    crop: [number, number, number, number];
    x: number;
    y: number;
    width: number;
};
export const CREATOR_ARTBOARD = { width: 400, height: 500 } as const;
// All positions are measured on one artboard. Height always comes from the
// original artwork, never from the aspect ratio of the enclosing portrait.
export const creatorAtlases: Record<
    CreatorLayerField,
    { file: string; width: number; height: number; parts: Record<string, Part> }
> = {
    face: {
        file: 'faces',
        width: 1122,
        height: 1402,
        parts: {
            harbor: { crop: [124, 130, 343, 512], x: 138, y: 92, width: 124 },
            weathered: {
                crop: [654, 130, 346, 513],
                x: 138,
                y: 92,
                width: 124,
            },
            keen: { crop: [126, 791, 338, 507], x: 138, y: 92, width: 124 },
            round: { crop: [644, 791, 366, 509], x: 134, y: 92, width: 132 },
        },
    },
    outfit: {
        file: 'outfits',
        width: 1122,
        height: 1402,
        parts: {
            mariner: { crop: [17, 356, 547, 438], x: 10, y: 239, width: 380 },
            scholar: { crop: [558, 352, 545, 442], x: 10, y: 235, width: 380 },
            waistcoat: { crop: [14, 890, 550, 481], x: 10, y: 243, width: 380 },
            ritual: { crop: [558, 844, 563, 527], x: 10, y: 234, width: 380 },
        },
    },
    hair: {
        file: 'hair',
        width: 1536,
        height: 1024,
        parts: {
            cropped: { crop: [75, 37, 371, 327], x: 131, y: 82, width: 138 },
            waves: { crop: [515, 37, 488, 475], x: 112, y: 80, width: 176 },
            curls: { crop: [1060, 32, 435, 399], x: 121, y: 76, width: 158 },
            braid: { crop: [91, 515, 367, 509], x: 130, y: 80, width: 140 },
            swept: { crop: [549, 528, 414, 347], x: 124, y: 76, width: 152 },
        },
    },
    hat: {
        file: 'hats',
        width: 1536,
        height: 1024,
        parts: {
            watchcap: { crop: [549, 94, 411, 307], x: 129, y: 60, width: 142 },
            widebrim: { crop: [984, 144, 544, 274], x: 91, y: 41, width: 218 },
            tricorn: { crop: [4, 567, 538, 249], x: 97, y: 54, width: 206 },
            hood: { crop: [540, 510, 465, 508], x: 105, y: 69, width: 190 },
            antlers: { crop: [1058, 528, 453, 406], x: 106, y: 7, width: 188 },
        },
    },
    detail: {
        file: 'details',
        width: 1254,
        height: 1254,
        parts: {
            spectacles: {
                crop: [658, 200, 573, 203],
                x: 151,
                y: 151,
                width: 98,
            },
            earring: { crop: [160, 753, 307, 355], x: 255, y: 192, width: 12 },
            brooch: { crop: [745, 665, 389, 500], x: 250, y: 336, width: 24 },
        },
    },
};
export const creatorViews = {
    mirror: { x: 0, y: 0, ...CREATOR_ARTBOARD },
    portrait: { x: 79, y: 43, width: 242, height: 282 },
} as const;
export type CreatorView = keyof typeof creatorViews;
export function creatorLayers(input?: CreatorRecipe | null) {
    const recipe = { ...defaultCreator, ...input };
    for (const field of ['face', 'outfit'] as const) {
        if (!creatorAtlases[field].parts[recipe[field]])
            recipe[field] = defaultCreator[field];
    }
    return (['face', 'outfit', 'hair', 'hat', 'detail'] as const).flatMap(
        (field) => {
            const atlas = creatorAtlases[field];
            const part = atlas.parts[recipe[field]];
            if (!part || (field === 'hair' && recipe.hat === 'hood')) return [];
            const [x, y, width, height] = part.crop;
            return [
                {
                    field,
                    value: recipe[field],
                    file: atlas.file,
                    atlas: { width: atlas.width, height: atlas.height },
                    crop: { x, y, width, height },
                    destination: {
                        x: part.x,
                        y: part.y,
                        width: part.width,
                        height: (part.width * height) / width,
                    },
                },
            ];
        },
    );
}
export function creatorChest(input?: CreatorRecipe | null) {
    const recipe = { ...defaultCreator, ...input };
    const face =
        creatorAtlases.face.parts[recipe.face] ??
        creatorAtlases.face.parts.harbor;
    // A small patch of the painted neck supplies the skin visible inside open
    // collars. It is enlarged uniformly and cropped; no face features are redrawn.
    const patches: Record<string, [number, number, number, number]> = {
        harbor: [254, 562, 80, 64],
        weathered: [784, 564, 80, 64],
        keen: [257, 1220, 80, 64],
        round: [786, 1222, 80, 64],
    };
    return {
        crop: patches[recipe.face] ?? patches.harbor,
        destination: { x: 162, y: 244, width: 76, height: 108 },
        face,
    };
}
