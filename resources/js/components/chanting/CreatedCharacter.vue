<script setup lang="ts">
import { computed } from 'vue';
import { defaultCreator, type CreatorRecipe } from '@/lib/creator';
const props = defineProps<{ recipe?: CreatorRecipe | null }>();
const appearance = computed(() => ({ ...defaultCreator, ...props.recipe }));
type Part = {
    crop: [number, number, number, number];
    position: [number, number, number, number];
};
const portraitParts: Record<
    string,
    { file: string; width: number; height: number; parts: Record<string, Part> }
> = {
    face: {
        file: 'faces',
        width: 1122,
        height: 1402,
        parts: {
            harbor: { crop: [124, 130, 343, 512], position: [32, 18, 36, 45] },
            weathered: {
                crop: [654, 130, 346, 513],
                position: [32, 18, 36, 45],
            },
            keen: { crop: [126, 791, 338, 507], position: [32, 18, 36, 45] },
            round: { crop: [644, 791, 366, 509], position: [31, 18, 38, 45] },
        },
    },
    outfit: {
        file: 'outfits',
        width: 1122,
        height: 1402,
        parts: {
            mariner: { crop: [17, 356, 547, 438], position: [5, 45, 90, 58] },
            scholar: { crop: [558, 352, 545, 442], position: [5, 45, 90, 58] },
            waistcoat: { crop: [14, 890, 550, 481], position: [5, 45, 90, 58] },
            ritual: { crop: [558, 844, 563, 527], position: [5, 45, 90, 58] },
        },
    },
    hair: {
        file: 'hair',
        width: 1536,
        height: 1024,
        parts: {
            cropped: { crop: [75, 37, 371, 327], position: [29, 15, 42, 27] },
            waves: { crop: [515, 37, 488, 475], position: [26, 15, 48, 44] },
            curls: { crop: [1060, 32, 435, 399], position: [27, 14, 46, 34] },
            braid: { crop: [91, 515, 367, 509], position: [31, 15, 38, 46] },
            swept: { crop: [549, 528, 414, 347], position: [28, 14, 44, 29] },
        },
    },
    hat: {
        file: 'hats',
        width: 1536,
        height: 1024,
        parts: {
            watchcap: { crop: [549, 94, 411, 307], position: [29, 11, 42, 20] },
            widebrim: {
                crop: [984, 144, 544, 274],
                position: [21, 10, 58, 23],
            },
            tricorn: { crop: [4, 567, 538, 249], position: [22, 12, 56, 20] },
            hood: { crop: [540, 510, 465, 508], position: [23, 12, 54, 48] },
            antlers: { crop: [1058, 528, 453, 406], position: [25, 3, 50, 35] },
        },
    },
    detail: {
        file: 'details',
        width: 1254,
        height: 1254,
        parts: {
            spectacles: {
                crop: [658, 200, 573, 203],
                position: [36, 32, 28, 8],
            },
            earring: { crop: [160, 753, 307, 355], position: [65, 40, 4, 5] },
            brooch: { crop: [745, 665, 389, 500], position: [62, 63, 7, 8] },
        },
    },
};
const colorFilters: Record<string, Record<string, string>> = {
    face: {
        porcelain:
            'sepia(.7) saturate(.85) hue-rotate(335deg) brightness(1.15)',
        warm: 'sepia(1) saturate(1.5) hue-rotate(335deg) brightness(.94)',
        umber: 'sepia(1) saturate(2) hue-rotate(330deg) brightness(.67)',
        deep: 'sepia(1) saturate(1.8) hue-rotate(330deg) brightness(.45)',
    },
    hair: {
        ink: 'brightness(.34) sepia(.1)',
        ash: 'brightness(1.05) sepia(.15)',
        copper: 'sepia(1) saturate(2.3) hue-rotate(338deg) brightness(.83)',
        flax: 'sepia(.8) saturate(1.3) brightness(1.15)',
    },
    outfit: {
        sea: 'sepia(.7) saturate(1.3) hue-rotate(107deg) brightness(.76)',
        ink: 'sepia(.3) saturate(.7) hue-rotate(154deg) brightness(.47)',
        wine: 'sepia(.8) saturate(1.8) hue-rotate(292deg) brightness(.7)',
        ochre: 'sepia(.95) saturate(1.6) brightness(.92)',
    },
};
const layers = computed(() => {
    const recipe = appearance.value;
    const cropped = (
        ['face', 'outfit', 'hair', 'hat', 'detail'] as const
    ).flatMap((field) => {
        const atlas = portraitParts[field];
        const part = atlas.parts[recipe[field]];
        if (!part) return [];
        const [x, y, width, height] = part.crop;
        const [left, top, displayWidth, displayHeight] = part.position;
        const color =
            field === 'face'
                ? recipe.skin
                : field === 'hair'
                  ? recipe.hair_color
                  : recipe.outfit_color;
        return [
            {
                field,
                value: recipe[field],
                style: {
                    left: `${left}%`,
                    top: `${top}%`,
                    width: `${displayWidth}%`,
                    height: `${displayHeight}%`,
                    backgroundImage: `url(/assets/chanting/creator/${atlas.file}.png)`,
                    backgroundSize: `${(atlas.width / width) * 100}% ${(atlas.height / height) * 100}%`,
                    backgroundPosition: `${(x / (atlas.width - width)) * 100}% ${(y / (atlas.height - height)) * 100}%`,
                    filter: colorFilters[field]?.[color],
                },
            },
        ];
    });
    return cropped;
});
</script>
<template>
    <span class="created-character" aria-hidden="true">
        <span
            v-for="layer in layers"
            :key="layer.field"
            class="created-character-layer"
            :class="`creator-layer-${layer.field} creator-part-${layer.value}`"
            :style="layer.style"
        ></span>
    </span>
</template>
<style scoped>
.created-character {
    display: block;
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: inherit;
}
.created-character-layer {
    position: absolute;
    background-repeat: no-repeat;
}
</style>
