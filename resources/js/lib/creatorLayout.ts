import { defaultCreator, type CreatorRecipe } from './creator.ts';
import { creatorHeadArt, creatorOutfitArt } from './creatorPoseArt.ts';

export type CreatorLayerField = 'face' | 'outfit' | 'hair' | 'hat' | 'detail';
type AccessoryField = 'hair' | 'hat' | 'detail';
export type CreatorLayer = {
    field: CreatorLayerField;
    value: string;
    file: string;
    atlas: { width: number; height: number };
    crop: Rect;
    destination: Rect;
    mirrored: boolean;
    sourceClip?: string;
    rotation?: number;
};
export type Rect = { x: number; y: number; width: number; height: number };
type Part = {
    rotation?: number;
    crop: [number, number, number, number];
    x: number;
    y: number;
    width: number;
};
export const CREATOR_ARTBOARD = { width: 400, height: 500 } as const;
// All positions are measured on one artboard. Height always comes from the
// original artwork, never from the aspect ratio of the enclosing portrait.
export const creatorAtlases: Record<
    AccessoryField,
    { file: string; width: number; height: number; parts: Record<string, Part> }
> = {
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
            hood: { crop: [540, 510, 465, 508], x: 70, y: 65, width: 260 },
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
// Angled accessories are separately painted; only their placement is shared.
export const creatorPoseAccessories: Record<
    string,
    Partial<typeof creatorAtlases>
> = {
    three_quarter: {
        hair: {
            file: 'hair-three_quarter-v2',
            width: 1536,
            height: 1024,
            parts: {
                cropped: {
                    crop: [49, 23, 410, 382],
                    x: 126,
                    y: 45,
                    width: 151,
                },
                waves: { crop: [522, 14, 483, 487], x: 110, y: 44, width: 177 },
                curls: {
                    crop: [1045, 15, 452, 401],
                    x: 118,
                    y: 42,
                    width: 165,
                },
                braid: { crop: [59, 479, 445, 545], x: 119, y: 48, width: 164 },
                swept: {
                    crop: [550, 505, 440, 394],
                    x: 124,
                    y: 46,
                    width: 155,
                },
            },
        },
        hat: {
            file: 'hats-three_quarter-v2',
            width: 1536,
            height: 1024,
            parts: {
                watchcap: {
                    crop: [548, 41, 411, 355],
                    x: 124,
                    y: 28,
                    width: 151,
                },
                widebrim: {
                    crop: [975, 83, 561, 320],
                    x: 90,
                    y: 15,
                    width: 221,
                },
                tricorn: { crop: [9, 510, 544, 304], x: 91, y: 20, width: 215 },
                hood: { crop: [529, 450, 505, 550], x: 45, y: 25, width: 310 },
                antlers: {
                    crop: [1040, 445, 485, 517],
                    x: 100,
                    y: 1,
                    width: 200,
                },
            },
        },
    },
    defiant: {
        hair: {
            file: 'hair-defiant-v2',
            width: 1536,
            height: 1024,
            parts: {
                cropped: {
                    crop: [57, 17, 408, 422],
                    x: 125,
                    y: 42,
                    width: 150,
                },
                waves: { crop: [520, 5, 491, 496], x: 109, y: 41, width: 177 },
                curls: { crop: [1035, 7, 476, 460], x: 116, y: 35, width: 171 },
                braid: { crop: [53, 454, 426, 570], x: 121, y: 44, width: 158 },
                swept: {
                    crop: [526, 486, 474, 445],
                    x: 121,
                    y: 39,
                    width: 160,
                },
            },
        },
        hat: {
            file: 'hats-defiant-v2',
            width: 1536,
            height: 1024,
            parts: {
                watchcap: {
                    crop: [511, 21, 423, 377],
                    rotation: 17,
                    x: 125,
                    y: 13,
                    width: 151,
                },
                widebrim: {
                    crop: [953, 46, 583, 390],
                    x: 88,
                    y: 6,
                    width: 223,
                },
                tricorn: {
                    crop: [11, 491, 534, 297],
                    x: 91,
                    y: 26,
                    width: 214,
                },
                hood: { crop: [526, 441, 493, 556], x: 45, y: 25, width: 310 },
                antlers: {
                    crop: [1031, 440, 493, 550],
                    x: 101,
                    y: 0,
                    width: 198,
                },
            },
        },
    },
};
export const creatorViews = {
    mirror: { x: 0, y: 0, ...CREATOR_ARTBOARD },
    portrait: { x: 79, y: 43, width: 242, height: 282 },
} as const;
export type CreatorView = keyof typeof creatorViews;
type PaintedPart = {
    crop: number[];
    neck: number[];
    eyes?: number[];
    chin?: number[];
};
type PaintedAtlas = {
    file: string;
    width: number;
    height: number;
    mirrored?: boolean;
    parts: Record<string, PaintedPart>;
};
export function creatorLayers(input?: Partial<CreatorRecipe> | null) {
    const recipe = { ...defaultCreator, ...input };
    const key = `${recipe.body_type}/${recipe.pose}`;
    const heads = creatorHeadArt as Record<string, PaintedAtlas>;
    const outfits = creatorOutfitArt as Record<string, PaintedAtlas>;
    const headAtlas = heads[key] ?? heads['type1/front'];
    const outfitAtlas = outfits[key] ?? outfits['type1/front'];
    if (!headAtlas.parts[recipe.face]) recipe.face = defaultCreator.face;
    if (!outfitAtlas.parts[recipe.outfit])
        recipe.outfit = defaultCreator.outfit;
    const head = headAtlas.parts[recipe.face];
    const headScale = 150 / (head.chin![1] - head.crop[1]);
    const headX = 200 - (head.neck[0] - head.crop[0]) * headScale;
    const headY = 80;
    return (
        ['face', 'outfit', 'hair', 'hat', 'detail'] as const
    ).flatMap<CreatorLayer>((field) => {
        if (field === 'face' || field === 'outfit') {
            const sheet = field === 'face' ? headAtlas : outfitAtlas;
            const painted = sheet.parts[recipe[field]];
            const [x, y, width, height] = painted.crop;
            const scale =
                field === 'face'
                    ? headScale
                    : (recipe.body_type === 'type2' ? 340 : 360) / width;
            return [
                {
                    field,
                    value: recipe[field],
                    file: sheet.file.replace(/\.png$/, ''),
                    atlas: { width: sheet.width, height: sheet.height },
                    crop: { x, y, width, height },
                    destination: {
                        x:
                            field === 'face'
                                ? headX
                                : 200 - (painted.neck[0] - x) * scale,
                        y:
                            field === 'face'
                                ? headY
                                : 250 - (painted.neck[1] - y) * scale,
                        width: width * scale,
                        height: height * scale,
                    },
                    mirrored: field === 'outfit' && !!sheet.mirrored,
                },
            ];
        }
        if (
            field === 'detail' &&
            recipe.detail === 'spectacles' &&
            recipe.pose !== 'front'
        ) {
            const right = recipe.pose === 'three_quarter';
            const [x, y, width, height] = right
                ? [43, 180, 1188, 370]
                : [52, 712, 1160, 393];
            const scale = 110 / width;
            const eyeX = headX + (head.eyes![0] - head.crop[0]) * headScale;
            const eyeY = headY + (head.eyes![1] - head.crop[1]) * headScale;
            return [
                {
                    field,
                    value: 'spectacles',
                    file: 'spectacles-poses-v2',
                    atlas: { width: 1254, height: 1254 },
                    crop: { x, y, width, height },
                    destination: {
                        x: eyeX - ((right ? 794 : 507) - x) * scale,
                        y: eyeY - ((right ? 368 : 922) - y) * scale,
                        width: 110,
                        height: height * scale,
                    },
                    mirrored: false,
                },
            ];
        }
        const atlas =
            creatorPoseAccessories[recipe.pose]?.[field] ??
            creatorAtlases[field];
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
                    x:
                        field === 'detail' && recipe.detail === 'earring'
                            ? recipe.pose === 'three_quarter'
                                ? 136
                                : recipe.pose === 'defiant'
                                  ? 260
                                  : 255
                            : field === 'detail' && recipe.detail === 'brooch'
                              ? recipe.pose === 'three_quarter'
                                  ? 228
                                  : recipe.pose === 'defiant'
                                    ? 167
                                    : 250
                              : part.x,
                    y:
                        field === 'detail' && recipe.detail === 'spectacles'
                            ? headY +
                              (head.eyes![1] - head.crop[1]) * headScale -
                              (part.width * height) / width / 2
                            : part.y -
                              (recipe.pose === 'front'
                                  ? field === 'hair'
                                      ? 35
                                      : field === 'hat'
                                        ? 25
                                        : 0
                                  : 0),
                    width: part.width,
                    height: (part.width * height) / width,
                },
                mirrored: false,
                rotation: part.rotation,
                sourceClip:
                    field === 'hat' &&
                    recipe.hat === 'hood' &&
                    recipe.pose !== 'front'
                        ? recipe.pose === 'three_quarter'
                            ? 'M560 450H1040V1000H529V820L560 780Z'
                            : 'M560 441H1019V997H526V800L560 780Z'
                        : field === 'hat' &&
                            recipe.hat === 'tricorn' &&
                            recipe.pose === 'three_quarter'
                          ? 'M0 500H560V770L525 820H0Z'
                          : undefined,
            },
        ];
    });
}
