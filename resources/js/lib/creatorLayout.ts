import { defaultCreator, type CreatorRecipe } from './creator.ts';
import { creatorHeadArt, creatorOutfitArt } from './creatorPoseArt.ts';
import {
    headAttachments,
    hatHairEnclosures,
    collarAttachments,
    accessoryAttachments,
    fitAttachment,
    rigPoint,
    type Point,
    type Similarity,
} from './creatorRig.ts';

export type CreatorLayerField = 'face' | 'outfit' | 'hair' | 'hat' | 'detail';
type AccessoryField = 'hair' | 'hat' | 'detail';
export type CreatorLayer = {
    field: CreatorLayerField;
    value: string;
    file: string;
    atlas: { width: number; height: number };
    crop: Rect;
    destination: Rect;
    sourceClip?: string;
    frontClip?: string;
    transform?: string;
    attachment?: {
        source: readonly Point[];
        target: readonly Point[];
        fit: Similarity;
    };
};
export type Rect = { x: number; y: number; width: number; height: number };
type Part = { crop: [number, number, number, number] };
type AccessoryAtlas = {
    file: string;
    width: number;
    height: number;
    parts: Record<string, Part>;
};
export const CREATOR_ARTBOARD = { width: 400, height: 500 } as const;
// Source crop rectangles only. Placement comes from the shared attachment rig.
export const creatorAtlases: Record<AccessoryField, AccessoryAtlas> = {
    hair: {
        file: 'hair',
        width: 1536,
        height: 1024,
        parts: {
            cropped: { crop: [75, 37, 371, 327] },
            waves: { crop: [515, 37, 488, 475] },
            curls: { crop: [1060, 32, 435, 399] },
            braid: { crop: [91, 515, 367, 509] },
            swept: { crop: [549, 528, 414, 347] },
        },
    },
    hat: {
        file: 'hats',
        width: 1536,
        height: 1024,
        parts: {
            watchcap: { crop: [549, 94, 411, 307] },
            widebrim: { crop: [984, 144, 544, 274] },
            tricorn: { crop: [4, 567, 538, 249] },
            hood: { crop: [540, 510, 465, 508] },
            antlers: { crop: [1058, 528, 453, 406] },
        },
    },
    detail: {
        file: 'details',
        width: 1254,
        height: 1254,
        parts: {
            spectacles: { crop: [658, 200, 573, 203] },
            earring: { crop: [160, 753, 307, 355] },
            brooch: { crop: [745, 665, 389, 500] },
        },
    },
};
export const creatorPoseAccessories: Record<
    string,
    Partial<Record<AccessoryField, AccessoryAtlas>>
> = {
    three_quarter: {
        hair: {
            file: 'hair-three_quarter-v2',
            width: 1536,
            height: 1024,
            parts: {
                cropped: { crop: [49, 23, 410, 382] },
                waves: { crop: [522, 14, 483, 487] },
                curls: { crop: [1045, 15, 452, 401] },
                braid: { crop: [59, 479, 445, 545] },
                swept: { crop: [550, 505, 440, 394] },
            },
        },
        hat: {
            file: 'hats-three_quarter-v2',
            width: 1536,
            height: 1024,
            parts: {
                watchcap: { crop: [548, 41, 411, 355] },
                widebrim: { crop: [975, 83, 561, 320] },
                tricorn: { crop: [9, 510, 544, 304] },
                hood: { crop: [529, 450, 505, 550] },
                antlers: { crop: [1040, 445, 485, 517] },
            },
        },
    },
    defiant: {
        hair: {
            file: 'hair-defiant-v2',
            width: 1536,
            height: 1024,
            parts: {
                cropped: { crop: [57, 17, 408, 422] },
                waves: { crop: [520, 5, 491, 496] },
                curls: { crop: [1035, 7, 476, 460] },
                braid: { crop: [53, 454, 426, 570] },
                swept: { crop: [526, 486, 474, 445] },
            },
        },
        hat: {
            file: 'hats-defiant-v2',
            width: 1536,
            height: 1024,
            parts: {
                watchcap: { crop: [511, 21, 423, 377] },
                widebrim: { crop: [953, 46, 583, 390] },
                tricorn: { crop: [11, 491, 534, 297] },
                hood: { crop: [526, 441, 493, 556] },
                antlers: { crop: [1031, 440, 493, 550] },
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
    parts: Record<string, PaintedPart>;
};
const point = (p: number[]): Point => [p[0], p[1]];
const line = (p: Point) => p.join(' ');
function paintLayer(
    field: CreatorLayerField,
    value: string,
    sheet: { file: string; width: number; height: number },
    crop: number[],
    fit: Similarity,
): CreatorLayer {
    const [x, y, width, height] = crop;
    const origin = rigPoint(fit, [x, y]);
    const degrees = (fit.angle * 180) / Math.PI;
    return {
        field,
        value,
        file: sheet.file.replace(/\.png$/, ''),
        atlas: { width: sheet.width, height: sheet.height },
        crop: { x, y, width, height },
        destination: {
            x: origin[0],
            y: origin[1],
            width: width * fit.scale,
            height: height * fit.scale,
        },
        transform: degrees
            ? 'rotate(' + degrees + ' ' + origin[0] + ' ' + origin[1] + ')'
            : undefined,
    };
}
export function creatorFitting(input?: Partial<CreatorRecipe> | null) {
    const recipe = { ...defaultCreator, ...input };
    const heads = creatorHeadArt as Record<string, PaintedAtlas>,
        outfits = creatorOutfitArt as Record<string, PaintedAtlas>;
    let key = recipe.body_type + '/' + recipe.pose;
    if (!heads[key]) {
        recipe.body_type = 'type1';
        recipe.pose = 'front';
        key = 'type1/front';
    }
    if (!heads[key].parts[recipe.face]) recipe.face = defaultCreator.face;
    if (!outfits[key].parts[recipe.outfit])
        recipe.outfit = defaultCreator.outfit;
    const head = heads[key].parts[recipe.face],
        outfit = outfits[key].parts[recipe.outfit];
    const outfitScale =
        (recipe.body_type === 'type2' ? 340 : 360) / outfit.crop[2];
    const outfitFit: Similarity = {
        scale: outfitScale,
        angle: 0,
        x: 200 - outfit.neck[0] * outfitScale,
        y: 264 - outfit.neck[1] * outfitScale,
    };
    const rim = collarAttachments[key][recipe.outfit].rim.map((p) =>
        rigPoint(outfitFit, p),
    );
    const seam: Point = [
        (rim[0][0] + 2 * rim[1][0] + rim[2][0]) / 4,
        (rim[0][1] + 2 * rim[1][1] + rim[2][1]) / 4,
    ];
    const scale = 150 / (head.chin![1] - head.crop[1]);
    // Put the painted neck base behind the actual front collar seam. The head
    // keeps adult proportions; changing clothing never rescales a face.
    const neckTarget: Point = [200, seam[1] + 7];
    const headFit: Similarity = {
        scale,
        angle: 0,
        x: neckTarget[0] - head.neck[0] * scale,
        y: neckTarget[1] - head.neck[1] * scale,
    };
    const landmarks = headAttachments[key][recipe.face];
    const temples: readonly [Point, Point] = [
        rigPoint(headFit, landmarks.temples[0]),
        rigPoint(headFit, landmarks.temples[1]),
    ];
    const eyes = rigPoint(headFit, point(head.eyes!)),
        chin = rigPoint(headFit, point(head.chin!)),
        ear = rigPoint(headFit, landmarks.ear);
    const headLayer = paintLayer(
        'face',
        recipe.face,
        heads[key],
        head.crop,
        headFit,
    );
    const outfitLayer = paintLayer(
        'outfit',
        recipe.outfit,
        outfits[key],
        outfit.crop,
        outfitFit,
    );
    const layers: CreatorLayer[] = [headLayer, outfitLayer];
    for (const field of ['hair', 'hat', 'detail'] as const) {
        if (field === 'hair' && recipe.hat === 'hood') continue;
        const atlas =
            creatorPoseAccessories[recipe.pose]?.[field] ??
            creatorAtlases[field];
        const part = atlas.parts[recipe[field]];
        if (!part) continue;
        if (field === 'hair' || field === 'hat') {
            const source =
                accessoryAttachments[recipe.pose][field + '/' + recipe[field]];
            const fit = fitAttachment(source, temples);
            const layer = paintLayer(
                field,
                recipe[field],
                atlas,
                part.crop,
                fit,
            );
            if (
                field === 'hat' &&
                recipe.hat === 'hood' &&
                recipe.pose !== 'front'
            ) {
                // The atlas cell overlaps a neighboring tricorn tip; this clips
                // that stray sprite, never the hood's opening or the face.
                layer.sourceClip =
                    recipe.pose === 'three_quarter'
                        ? 'M560 450H1040V1000H529V820L560 780Z'
                        : 'M560 441H1019V997H526V800L560 780Z';
            }
            if (
                field === 'hat' &&
                recipe.hat === 'tricorn' &&
                recipe.pose === 'three_quarter'
            ) {
                layer.sourceClip = 'M0 500H560V770L525 820H0Z';
            }
            layer.attachment = { source, target: temples, fit };
            if (field === 'hat' && recipe.hat === 'antlers') {
                // Pendants hang behind the temples; the brow band stays in
                // front. Split on its painted lower edge, not across eyes.
                layer.frontClip =
                    recipe.pose === 'front'
                        ? 'M1000 500H1536V775H1000Z'
                        : recipe.pose === 'three_quarter'
                          ? 'M1000 420H1536V763H1000Z'
                          : 'M1000 420H1536V746H1000Z';
            }
            layers.push(layer);
        } else if (recipe.detail === 'spectacles') {
            const angled = recipe.pose !== 'front',
                right = recipe.pose === 'three_quarter';
            const sheet = angled
                ? { file: 'spectacles-poses-v2', width: 1254, height: 1254 }
                : atlas;
            const crop = angled
                ? right
                    ? [43, 180, 1188, 370]
                    : [52, 712, 1160, 393]
                : part.crop;
            const bridge: Point = angled
                ? right
                    ? [794, 368]
                    : [507, 922]
                : [944, 300];
            const width =
                Math.hypot(
                    temples[1][0] - temples[0][0],
                    temples[1][1] - temples[0][1],
                ) * 0.94;
            const factor = width / crop[2];
            const fit = {
                scale: factor,
                angle: 0,
                x: eyes[0] - bridge[0] * factor,
                y: eyes[1] - bridge[1] * factor,
            };
            const layer = paintLayer(field, recipe.detail, sheet, crop, fit);
            layer.attachment = { source: [bridge], target: [eyes], fit };
            layers.push(layer);
        } else {
            const isEar = recipe.detail === 'earring';
            const target: Point = isEar
                ? ear
                : rigPoint(outfitFit, [
                      outfit.neck[0] + (recipe.pose === 'defiant' ? -75 : 75),
                      outfit.neck[1] + 142,
                  ]);
            const origin: Point = [
                part.crop[0] + part.crop[2] / 2,
                part.crop[1],
            ];
            const factor = (isEar ? 12 : 24) / part.crop[2];
            const fit = {
                scale: factor,
                angle: 0,
                x: target[0] - origin[0] * factor,
                y: target[1] - origin[1] * factor,
            };
            const layer = paintLayer(
                field,
                recipe.detail,
                atlas,
                part.crop,
                fit,
            );
            layer.attachment = { source: [origin], target: [target], fit };
            layers.push(layer);
        }
    }

    const [left, control, right] = rim;
    const jawLeft = rigPoint(headFit, landmarks.jaw[0]),
        jawRight = rigPoint(headFit, landmarks.jaw[1]);
    const chinEdge: Point = [chin[0], chin[1] + 3];
    const jawControlLeft: Point = [(jawLeft[0] + chin[0]) / 2, chin[1] + 9];
    const jawControlRight: Point = [(jawRight[0] + chin[0]) / 2, chin[1] + 9];
    const neckHalf =
        (landmarks.neckSides[1] - landmarks.neckSides[0]) * scale * 0.35;
    const neckTop = chin[1] - 15;
    // Union of the actual jaw contour and the neck stem. A horizontal cut at
    // the lowest chin kept the source neck's broad corners beside the jaw.
    // The two overlapping contours retain every facial feature and tuck only
    // the painted neck flare behind this garment's measured front collar lip.
    let faceClip =
        'M0 0H400V' +
        jawRight[1] +
        'H' +
        jawRight[0] +
        'Q' +
        line(jawControlRight) +
        ' ' +
        line(chinEdge) +
        'Q' +
        line(jawControlLeft) +
        ' ' +
        line(jawLeft) +
        'H0Z' +
        'M' +
        (200 - neckHalf) +
        ' ' +
        neckTop +
        'H' +
        (200 + neckHalf) +
        'L' +
        line(right) +
        'Q' +
        line(control) +
        ' ' +
        line(left) +
        'Z';

    // Angled heads expose the rear neck from just below the ear. Follow one
    // continuous ear-to-collar curve there: a jaw/neck union leaves a square
    // ledge when the painted neck silhouette continues behind the jaw.
    if (recipe.pose === 'three_quarter') {
        const rear = rigPoint(headFit, [
            landmarks.neckSides[0],
            landmarks.ear[1] + 12,
        ]);
        faceClip =
            'M0 0H400V' +
            jawRight[1] +
            'H' +
            jawRight[0] +
            'Q' +
            line(jawControlRight) +
            ' ' +
            line(chinEdge) +
            'L' +
            line(right) +
            'Q' +
            line(control) +
            ' ' +
            line(left) +
            'C' +
            left[0] +
            ' ' +
            (left[1] - 12) +
            ' ' +
            rear[0] +
            ' ' +
            (rear[1] + (left[1] - rear[1]) * 0.55) +
            ' ' +
            line(rear) +
            'H0Z';
    } else if (recipe.pose === 'defiant') {
        const rear = rigPoint(headFit, [
            landmarks.neckSides[1],
            landmarks.ear[1] + 12,
        ]);
        faceClip =
            'M0 0H400V' +
            rear[1] +
            'H' +
            rear[0] +
            'C' +
            rear[0] +
            ' ' +
            (rear[1] + (right[1] - rear[1]) * 0.55) +
            ' ' +
            right[0] +
            ' ' +
            (right[1] - 12) +
            ' ' +
            line(right) +
            'Q' +
            line(control) +
            ' ' +
            line(left) +
            'L' +
            line(chinEdge) +
            'Q' +
            line(jawControlLeft) +
            ' ' +
            line(jawLeft) +
            'H0Z';
    }
    const collarClip =
        'M0 650V' +
        left[1] +
        'H' +
        left[0] +
        'Q' +
        line(control) +
        ' ' +
        line(right) +
        'H400V650Z';
    const faceProtection =
        'M' +
        line(temples[0]) +
        'Q' +
        (temples[0][0] + temples[1][0]) / 2 +
        ' ' +
        (Math.min(temples[0][1], temples[1][1]) - 18) +
        ' ' +
        line(temples[1]) +
        'L400 650H0Z';
    const hood = layers.find(
        (layer) => layer.field === 'hat' && layer.value === 'hood',
    );
    const hoodLining = hood
        ? {
              ...hood,
              interior:
                  recipe.pose === 'front'
                      ? 'M752 650C654 650 621 745 637 834L700 902H836L900 834C929 738 875 650 752 650Z'
                      : recipe.pose === 'three_quarter'
                        ? 'M800 550C870 560 926 640 895 770L841 868L720 859C680 756 690 670 800 550Z'
                        : 'M675 515C752 517 820 582 849 700L850 759L762 849L646 793L605 692L608 568Z',
          }
        : undefined;
    const enclosedHat = layers.find(
        (layer) =>
            layer.field === 'hat' &&
            ['watchcap', 'widebrim', 'tricorn'].includes(layer.value),
    );
    let hairClip: string | undefined;
    if (enclosedHat?.attachment) {
        const band = hatHairEnclosures[recipe.pose][enclosedHat.value].map(
            (p) => rigPoint(enclosedHat.attachment!.fit, p),
        );

        const flare = (band[2][0] - band[0][0]) * 0.14;
        const drop = flare * 1.35;
        // Hair compresses under a hat before emerging at the temples. Curved
        // side exits avoid cutting curls into a horizontal shelf beside a cap.
        hairClip =
            'M0 ' +
            (band[0][1] + drop) +
            'H' +
            (band[0][0] - flare) +
            'Q' +
            (band[0][0] - flare * 0.45) +
            ' ' +
            (band[0][1] + drop) +
            ' ' +
            line(band[0]) +
            'Q' +
            line(band[1]) +
            ' ' +
            line(band[2]) +
            'Q' +
            (band[2][0] + flare * 0.45) +
            ' ' +
            (band[2][1] + drop) +
            ' ' +
            (band[2][0] + flare) +
            ' ' +
            (band[2][1] + drop) +
            'H400V650H0Z';
    }
    return {
        layers,
        hoodLining,
        hairClip,
        faceClip,
        collarClip,
        faceProtection,
        headLayer,
        anchors: {
            temples,
            eyes,
            chin,
            ear,
            neck: rigPoint(headFit, point(head.neck)),
            seam,
            rim,
        },
        headFit,
        outfitFit,
    };
}
export function creatorLayers(input?: Partial<CreatorRecipe> | null) {
    return creatorFitting(input).layers;
}
