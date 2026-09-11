import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { defaultCreator } from '../../resources/js/lib/creator.ts';
import {
    creatorAtlases,
    creatorLayers,
} from '../../resources/js/lib/creatorLayout.ts';

const nearly = (actual, expected, message) =>
    assert.ok(
        Math.abs(actual - expected) < 1e-8,
        `${message}: ${actual} != ${expected}`,
    );

await test('every cropped character piece preserves its native shape on the shared artboard', () => {
    for (const [field, atlas] of Object.entries(creatorAtlases)) {
        for (const value of Object.keys(atlas.parts)) {
            const layer = creatorLayers({
                ...defaultCreator,
                [field]: value,
            }).find((item) => item.field === field);
            assert.ok(layer, `${field}/${value} must render`);
            const nativeRatio = layer.crop.width / layer.crop.height;
            nearly(
                layer.destination.width / layer.destination.height,
                nativeRatio,
                `${field}/${value}`,
            );
        }
    }
});

await test('atlas crop rectangles stay inside the actual PNG dimensions', () => {
    for (const atlas of Object.values(creatorAtlases)) {
        const png = readFileSync(
            new URL(
                `../../public/assets/chanting/creator/${atlas.file}.png`,
                import.meta.url,
            ),
        );
        assert.equal(png.toString('ascii', 1, 4), 'PNG');
        assert.equal(atlas.width, png.readUInt32BE(16));
        assert.equal(atlas.height, png.readUInt32BE(20));
        for (const [id, part] of Object.entries(atlas.parts)) {
            const [x, y, width, height] = part.crop;
            assert.ok(
                x >= 0 && y >= 0 && width > 0 && height > 0,
                `${atlas.file}/${id} crop`,
            );
            assert.ok(
                x + width <= atlas.width && y + height <= atlas.height,
                `${atlas.file}/${id} overflows its sheet`,
            );
        }
    }
});

await test('optional layers and covered hair do not produce floating duplicate pieces', () => {
    const bare = creatorLayers({
        ...defaultCreator,
        hair: 'bald',
        hat: 'none',
        detail: 'none',
    });
    assert.deepEqual(bare.map((item) => item.field).sort(), ['face', 'outfit']);
    const hooded = creatorLayers({
        ...defaultCreator,
        hair: 'waves',
        hat: 'hood',
    });
    assert.ok(hooded.some((item) => item.field === 'hat'));
    assert.ok(!hooded.some((item) => item.field === 'hair'));
    const recipe = Object.freeze({
        ...defaultCreator,
        face: 'round',
        outfit: 'ritual',
        detail: 'brooch',
    });
    const original = JSON.stringify(recipe);
    creatorLayers(recipe);
    assert.equal(
        JSON.stringify(recipe),
        original,
        'Rendering must not change a saved recipe',
    );
});
