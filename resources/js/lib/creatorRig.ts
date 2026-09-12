// Attachment coordinates are measured in the original atlas, not the portrait.
// Each row: left/right temple, visible ear lobe. Temples lie above the eyebrows;
// unlike a crop box they describe the skull that a hat or hairstyle must fit.
export type Point = readonly [number, number];
export type Pair = readonly [Point, Point];
export const headAttachments: Record<
    string,
    Record<
        string,
        {
            temples: Pair;
            ear: Point;
            neckSides: readonly [number, number];
            jaw: Pair;
        }
    >
> = {};
const jawEnds: Record<string, Pair[]> = {
    'type1/front': [
        [
            [180, 410],
            [470, 410],
        ],
        [
            [789, 424],
            [1069, 424],
        ],
        [
            [214, 1010],
            [449, 1010],
        ],
        [
            [779, 1008],
            [1073, 1008],
        ],
    ],
    'type2/front': [
        [
            [197, 407],
            [465, 407],
        ],
        [
            [798, 417],
            [1063, 417],
        ],
        [
            [221, 1009],
            [455, 1009],
        ],
        [
            [797, 1020],
            [1066, 1020],
        ],
    ],
    'type1/three_quarter': [
        [
            [206, 407],
            [500, 407],
        ],
        [
            [807, 413],
            [1090, 413],
        ],
        [
            [223, 1001],
            [484, 1001],
        ],
        [
            [803, 1003],
            [1098, 1003],
        ],
    ],
    'type2/three_quarter': [
        [
            [230, 395],
            [495, 395],
        ],
        [
            [819, 408],
            [1084, 408],
        ],
        [
            [231, 1000],
            [480, 1000],
        ],
        [
            [811, 1022],
            [1082, 1022],
        ],
    ],
    'type1/defiant': [
        [
            [166, 388],
            [458, 383],
        ],
        [
            [755, 398],
            [1045, 389],
        ],
        [
            [193, 984],
            [470, 972],
        ],
        [
            [762, 985],
            [1060, 983],
        ],
    ],
    'type2/defiant': [
        [
            [182, 390],
            [470, 392],
        ],
        [
            [775, 398],
            [1050, 400],
        ],
        [
            [206, 997],
            [491, 996],
        ],
        [
            [789, 1005],
            [1083, 1003],
        ],
    ],
};
// Neck sides immediately below the chin, before the sprite's base flares out.
const neckSides: Record<string, [number, number][]> = {
    'type1/front': [
        [180, 468],
        [783, 1070],
        [214, 448],
        [778, 1070],
    ],
    'type2/front': [
        [213, 442],
        [813, 1043],
        [238, 434],
        [810, 1045],
    ],
    'type1/three_quarter': [
        [177, 465],
        [785, 1056],
        [200, 445],
        [775, 1060],
    ],
    'type2/three_quarter': [
        [216, 417],
        [798, 997],
        [218, 395],
        [793, 991],
    ],
    'type1/defiant': [
        [184, 491],
        [768, 1076],
        [220, 499],
        [782, 1079],
    ],
    'type2/defiant': [
        [246, 480],
        [817, 1056],
        [266, 498],
        [828, 1095],
    ],
};
const headRows: Record<string, number[][]> = {
    'type1/front': [
        [175, 184, 479, 184, 499, 350],
        [779, 190, 1077, 190, 1091, 362],
        [192, 788, 471, 788, 491, 936],
        [763, 785, 1078, 785, 1091, 959],
    ],
    'type2/front': [
        [169, 198, 491, 198, 499, 354],
        [772, 204, 1095, 204, 1096, 363],
        [193, 792, 472, 792, 488, 940],
        [770, 803, 1087, 803, 1099, 954],
    ],
    'type1/three_quarter': [
        [167, 174, 502, 187, 184, 349],
        [765, 176, 1093, 191, 785, 363],
        [172, 770, 495, 787, 190, 934],
        [758, 772, 1102, 788, 782, 959],
    ],
    'type2/three_quarter': [
        [184, 182, 510, 192, 207, 339],
        [775, 187, 1101, 198, 794, 350],
        [187, 785, 497, 796, 205, 925],
        [773, 785, 1099, 799, 791, 937],
    ],
    'type1/defiant': [
        [172, 179, 499, 171, 509, 341],
        [754, 181, 1087, 173, 1092, 350],
        [191, 772, 499, 762, 509, 933],
        [758, 776, 1095, 761, 1104, 949],
    ],
    'type2/defiant': [
        [178, 170, 514, 175, 526, 333],
        [753, 176, 1093, 182, 1102, 355],
        [196, 778, 526, 780, 533, 930],
        [769, 782, 1113, 788, 1122, 938],
    ],
};
for (const [key, rows] of Object.entries(headRows)) {
    headAttachments[key] = Object.fromEntries(
        ['harbor', 'weathered', 'keen', 'round'].map((name, i) => {
            const [lx, ly, rx, ry, ex, ey] = rows[i];
            return [
                name,
                {
                    temples: [
                        [lx, ly],
                        [rx, ry],
                    ],
                    ear: [ex, ey],
                    neckSides: neckSides[key][i],
                    jaw: jawEnds[key][i],
                },
            ];
        }),
    );
}

// The two ends and quadratic control of the FRONT lip of each painted collar.
// The center of an empty collar is not its seam: the skin must extend behind
// this front lip. Angled collars retain their painted slope and shape.
export const collarAttachments: Record<
    string,
    Record<string, { rim: readonly [Point, Point, Point] }>
> = {};
const collarRows: Record<string, number[][]> = {
    'type1/front': [
        [268, 40, 318, 97, 368, 41],
        [882, 40, 940, 90, 1002, 40],
        [256, 660, 314, 714, 372, 660],
        [882, 658, 940, 705, 998, 658],
    ],
    'type2/front': [
        [258, 47, 313, 124, 368, 48],
        [875, 48, 940, 101, 1004, 48],
        [259, 671, 316, 721, 370, 671],
        [882, 671, 940, 717, 998, 671],
    ],
    'type1/three_quarter': [
        [302, 41, 340, 87, 396, 69],
        [908, 41, 952, 88, 1008, 69],
        [292, 661, 337, 706, 396, 685],
        [903, 646, 950, 692, 1013, 674],
    ],
    'type2/three_quarter': [
        [283, 68, 325, 111, 392, 93],
        [910, 63, 950, 104, 1007, 88],
        [286, 680, 324, 718, 383, 706],
        [914, 683, 958, 715, 1019, 704],
    ],
    'type1/defiant': [
        [284, 52, 323, 85, 392, 36],
        [907, 52, 931, 86, 1012, 37],
        [290, 676, 329, 704, 397, 657],
        [912, 666, 944, 693, 1010, 650],
    ],
    'type2/defiant': [
        [278, 79, 321, 103, 388, 49],
        [877, 78, 918, 103, 984, 49],
        [279, 689, 319, 717, 382, 666],
        [863, 693, 904, 714, 977, 658],
    ],
};
for (const [key, rows] of Object.entries(collarRows)) {
    collarAttachments[key] = Object.fromEntries(
        ['mariner', 'scholar', 'waistcoat', 'ritual'].map((name, i) => {
            const [lx, ly, cx, cy, rx, ry] = rows[i];
            return [
                name,
                {
                    rim: [
                        [lx, ly],
                        [cx, cy],
                        [rx, ry],
                    ],
                },
            ];
        }),
    );
}

// Two points on the inner headband / hair opening, left to right. The fitter
// maps this line onto the selected face's temples with one similarity transform.
export const accessoryAttachments: Record<string, Record<string, Pair>> = {
    front: {
        'hair/cropped': [
            [130.5, 245],
            [405.5, 245],
        ],
        'hair/waves': [
            [615, 246],
            [915, 246],
        ],
        'hair/curls': [
            [1126.5, 243],
            [1418.5, 243],
        ],
        'hair/braid': [
            [120, 744],
            [408, 744],
        ],
        'hair/swept': [
            [612, 749],
            [924, 749],
        ],
        'hat/watchcap': [
            [575, 382],
            [932, 382],
        ],
        'hat/widebrim': [
            [1131, 389],
            [1402, 389],
        ],
        'hat/tricorn': [
            [109, 790],
            [430, 790],
        ],
        'hat/hood': [
            [648, 713],
            [900, 713],
        ],
        'hat/antlers': [
            [1126, 767],
            [1425, 767],
        ],
    },
    three_quarter: {
        'hair/cropped': [
            [129.5, 220],
            [435.5, 229],
        ],
        'hair/waves': [
            [645, 233],
            [949, 241],
        ],
        'hair/curls': [
            [1155, 233],
            [1451, 242],
        ],
        'hair/braid': [
            [128.5, 698],
            [438.5, 706],
        ],
        'hair/swept': [
            [666, 733],
            [985, 741],
        ],
        'hat/watchcap': [
            [581, 394],
            [926, 316],
        ],
        'hat/widebrim': [
            [1128, 371],
            [1416, 380],
        ],
        'hat/tricorn': [
            [150, 754],
            [451, 775],
        ],
        'hat/hood': [
            [702, 641],
            [932, 649],
        ],
        'hat/antlers': [
            [1112, 744],
            [1430, 756],
        ],
    },
    defiant: {
        'hair/cropped': [
            [115, 206],
            [433, 209],
        ],
        'hair/waves': [
            [606.5, 211],
            [914.5, 215],
        ],
        'hair/curls': [
            [1138, 222],
            [1448, 226],
        ],
        'hair/braid': [
            [116, 674],
            [432, 678],
        ],
        'hair/swept': [
            [595.5, 744],
            [925.5, 748],
        ],
        'hat/watchcap': [
            [553, 389],
            [905, 268],
        ],
        'hat/widebrim': [
            [1091, 375],
            [1380, 380],
        ],
        'hat/tricorn': [
            [115, 749],
            [417, 752],
        ],
        'hat/hood': [
            [638, 599],
            [827, 603],
        ],
        'hat/antlers': [
            [1104, 720],
            [1400, 734],
        ],
    },
};

export type Similarity = { scale: number; angle: number; x: number; y: number };
export function fitAttachment(source: Pair, target: Pair): Similarity {
    const dx = source[1][0] - source[0][0],
        dy = source[1][1] - source[0][1];
    const tx = target[1][0] - target[0][0],
        ty = target[1][1] - target[0][1];
    const scale = Math.hypot(tx, ty) / Math.hypot(dx, dy);
    const angle = Math.atan2(ty, tx) - Math.atan2(dy, dx);
    const c = Math.cos(angle) * scale,
        s = Math.sin(angle) * scale;
    return {
        scale,
        angle,
        x: target[0][0] - c * source[0][0] + s * source[0][1],
        y: target[0][1] - s * source[0][0] - c * source[0][1],
    };
}
export function rigPoint(fit: Similarity, point: Point): Point {
    const c = Math.cos(fit.angle) * fit.scale,
        s = Math.sin(fit.angle) * fit.scale;
    return [
        fit.x + c * point[0] - s * point[1],
        fit.y + s * point[0] + c * point[1],
    ];
}

// Painted lower headband curves. Hair under enclosed hats can emerge below
// this curve, but cannot pass through the crown outside the cap silhouette.
export const hatHairEnclosures: Record<
    string,
    Record<string, readonly [Point, Point, Point]>
> = {
    front: {
        watchcap: [
            [575, 385],
            [755, 255],
            [935, 385],
        ],
        widebrim: [
            [1110, 390],
            [1260, 419],
            [1408, 390],
        ],
        tricorn: [
            [110, 790],
            [270, 731],
            [430, 790],
        ],
    },
    three_quarter: {
        watchcap: [
            [577, 383],
            [760, 355],
            [924, 294],
        ],
        widebrim: [
            [1128, 374],
            [1270, 403],
            [1416, 385],
        ],
        tricorn: [
            [150, 764],
            [290, 720],
            [451, 785],
        ],
    },
    defiant: {
        watchcap: [
            [553, 380],
            [740, 338],
            [915, 251],
        ],
        widebrim: [
            [1091, 375],
            [1240, 420],
            [1380, 385],
        ],
        tricorn: [
            [115, 759],
            [270, 710],
            [417, 762],
        ],
    },
};
