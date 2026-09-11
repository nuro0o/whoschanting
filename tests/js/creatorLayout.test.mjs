import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { defaultCreator } from '../../resources/js/lib/creator.ts';
import {
    creatorAtlases,
    creatorLayers,
    creatorPoseAccessories,
} from '../../resources/js/lib/creatorLayout.ts';
import {
    creatorHeadArt,
    creatorOutfitArt,
} from '../../resources/js/lib/creatorPoseArt.ts';

const variants = ['type1', 'type2'].flatMap((body_type) =>
    ['front', 'three_quarter', 'defiant'].map((pose) => ({ body_type, pose })),
);
const nearly = (actual, expected, message) =>
    assert.ok(
        Math.abs(actual - expected) < 1e-8,
        `${message}: ${actual} != ${expected}`,
    );
const recipes = variants.flatMap((variant) =>
    ['harbor', 'weathered', 'keen', 'round'].flatMap((face) =>
        ['mariner', 'scholar', 'waistcoat', 'ritual'].map((outfit) => ({
            ...defaultCreator,
            ...variant,
            face,
            outfit,
        })),
    ),
);

await test('every face and outfit combination preserves painted proportions for both bodies and all poses', () => {
    assert.equal(recipes.length, 96);
    for (const recipe of recipes) {
        const layers = creatorLayers(recipe);
        for (const layer of layers)
            nearly(
                layer.destination.width / layer.destination.height,
                layer.crop.width / layer.crop.height,
                `${recipe.body_type}/${recipe.pose}/${layer.field}/${layer.value}`,
            );
        for (const field of ['face', 'outfit'])
            assert.ok(layers.some((layer) => layer.field === field));
    }
});

await test('each pose and body uses its own coordinated head and clothing artwork', () => {
    const heads = new Set();
    const outfits = new Set();
    for (const variant of variants) {
        const layers = creatorLayers({ ...defaultCreator, ...variant });
        heads.add(layers.find((layer) => layer.field === 'face').file);
        outfits.add(layers.find((layer) => layer.field === 'outfit').file);
    }
    assert.equal(heads.size, 6);
    assert.equal(outfits.size, 6);
});

await test('all rendered atlas crops are real transparent PNGs within the original image bounds', () => {
    const all = [];
    for (const recipe of recipes) all.push(...creatorLayers(recipe));
    for (const variant of variants)
        for (const field of ['hair', 'hat', 'detail']) {
            const options = {
                ...creatorAtlases[field].parts,
                ...creatorPoseAccessories[variant.pose]?.[field]?.parts,
            };
            for (const value of Object.keys(options))
                all.push(
                    ...creatorLayers({
                        ...defaultCreator,
                        ...variant,
                        [field]: value,
                    }),
                );
        }
    const seen = new Set();
    for (const layer of all) {
        const key = `${layer.file}/${layer.value}/${Object.values(layer.crop).join(',')}`;
        nearly(
            layer.destination.width / layer.destination.height,
            layer.crop.width / layer.crop.height,
            key,
        );
        if (seen.has(key)) continue;
        seen.add(key);
        const png = readFileSync(
            new URL(
                `../../public/assets/chanting/creator/${layer.file}.png`,
                import.meta.url,
            ),
        );
        assert.equal(png.toString('ascii', 1, 4), 'PNG');
        assert.equal(png.readUInt32BE(16), layer.atlas.width, key);
        assert.equal(png.readUInt32BE(20), layer.atlas.height, key);
        assert.equal(png[25], 6, `${key} must retain RGBA transparency`);
        const { x, y, width, height } = layer.crop;
        assert.ok(
            x >= 0 &&
                y >= 0 &&
                width > 0 &&
                height > 0 &&
                x + width <= layer.atlas.width &&
                y + height <= layer.atlas.height,
            key,
        );
    }
    assert.ok(
        seen.size > 60,
        'All painted body, pose and accessory variants must be checked',
    );
});

await test('missing additive recipe fields render the original default body and classic pose', () => {
    const { body_type, pose, ...legacy } = defaultCreator;
    assert.equal(body_type, 'type1');
    assert.equal(pose, 'front');
    assert.deepEqual(creatorLayers(legacy), creatorLayers(defaultCreator));
    const invalid = { ...defaultCreator, face: 'unknown', outfit: 'unknown' };
    assert.deepEqual(creatorLayers(invalid), creatorLayers(defaultCreator));
});

await test('optional layers and covered hair remain absent across all bodies and poses without mutating saved recipes', () => {
    for (const variant of variants) {
        const recipe = Object.freeze({
            ...defaultCreator,
            ...variant,
            hair: 'bald',
            hat: 'none',
            detail: 'none',
        });
        const original = JSON.stringify(recipe);
        assert.deepEqual(
            creatorLayers(recipe)
                .map((layer) => layer.field)
                .sort(),
            ['face', 'outfit'],
        );
        const hood = creatorLayers({ ...recipe, hair: 'waves', hat: 'hood' });
        assert.ok(hood.some((layer) => layer.field === 'hat'));
        assert.ok(!hood.some((layer) => layer.field === 'hair'));
        assert.equal(JSON.stringify(recipe), original);
    }
});

await test('every painted head has valid facial and neck landmarks and every garment has a matching neck anchor', () => {
    for (const variant of variants) {
        const key = `${variant.body_type}/${variant.pose}`;
        for (const part of Object.values(creatorHeadArt[key].parts)) {
            const [x, y, width, height] = part.crop;
            for (const landmark of [part.eyes, part.chin, part.neck])
                assert.ok(
                    landmark[0] >= x &&
                        landmark[0] <= x + width &&
                        landmark[1] >= y &&
                        landmark[1] <= y + height,
                    key,
                );
            assert.ok(
                part.eyes[1] < part.chin[1] && part.chin[1] < part.neck[1],
                key,
            );
        }
        for (const part of Object.values(creatorOutfitArt[key].parts)) {
            const [x, y, width, height] = part.crop;
            assert.ok(
                part.neck[0] >= x &&
                    part.neck[0] <= x + width &&
                    part.neck[1] >= y &&
                    part.neck[1] <= y + height,
                key,
            );
        }
    }
});
