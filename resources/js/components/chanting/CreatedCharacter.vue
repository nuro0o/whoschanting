<script setup lang="ts">
import { computed, useId } from 'vue';
import { defaultCreator, type CreatorRecipe } from '@/lib/creator';
import {
    creatorLayers,
    creatorViews,
    type CreatorView,
} from '@/lib/creatorLayout';
const props = withDefaults(
    defineProps<{ recipe?: CreatorRecipe | null; mode?: CreatorView }>(),
    { mode: 'portrait' },
);
const appearance = computed(() => ({ ...defaultCreator, ...props.recipe }));
const identity = useId();
const neckClip = computed(() =>
    appearance.value.pose === 'defiant'
        ? 'M0 0H400V232H255L232 250V270H169V250L139 232H0Z'
        : appearance.value.pose === 'three_quarter'
          ? 'M0 0H400V232H270L232 250V270H169V250L151 232H0Z'
          : 'M0 0H400V208H246Q237 229 231 250V270H169V250Q163 229 154 208H0Z',
);
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

const layers = computed(() => creatorLayers(appearance.value));
const paintedLayers = computed(() => {
    const outfit = layers.value.find((layer) => layer.field === 'outfit')!;
    const face = layers.value.find((layer) => layer.field === 'face')!;
    const hair = layers.value.find((layer) => layer.field === 'hair');
    const hood = layers.value.find(
        (layer) => layer.field === 'hat' && layer.value === 'hood',
    );
    return [
        { ...outfit, pass: 'back' },
        ...(hair ? [{ ...hair, pass: 'hair-back' }] : []),
        ...(hood ? [{ ...hood, pass: 'hood-back' }] : []),
        { ...face, pass: 'head' },
        { ...outfit, pass: 'collar' },
        ...(hair ? [{ ...hair, pass: 'hair-front' }] : []),
        ...(hood ? [{ ...hood, pass: 'hood-front' }] : []),
        ...layers.value
            .filter(
                (layer) =>
                    (layer.field === 'hat' && layer.value !== 'hood') ||
                    layer.field === 'detail',
            )
            .map((layer) => ({ ...layer, pass: layer.field })),
    ];
});
const viewBox = computed(() => {
    const view = creatorViews[props.mode];
    return `${view.x} ${view.y} ${view.width} ${view.height}`;
});
function filter(field: string) {
    const recipe = appearance.value;
    const color =
        field === 'face'
            ? recipe.skin
            : field === 'hair'
              ? recipe.hair_color
              : recipe.outfit_color;
    return colorFilters[field]?.[color]
        ? `grayscale(1) ${colorFilters[field][color]}`
        : undefined;
}
</script>
<template>
    <span class="created-character" aria-hidden="true">
        <svg
            class="created-character-artboard"
            :viewBox="viewBox"
            preserveAspectRatio="xMidYMid meet"
        >
            <defs>
                <clipPath :id="`${identity}-neck`">
                    <path :d="neckClip" />
                </clipPath>
                <mask
                    :id="`${identity}-collar`"
                    maskUnits="userSpaceOnUse"
                    x="0"
                    y="0"
                    width="400"
                    height="650"
                >
                    <rect width="400" height="650" fill="white" />
                    <path d="M169 0H231V248Q200 270 169 248Z" fill="black" />
                </mask>
                <mask
                    :id="`${identity}-hair`"
                    maskUnits="userSpaceOnUse"
                    x="0"
                    y="0"
                    width="400"
                    height="650"
                >
                    <rect width="400" height="650" fill="white" />
                    <rect
                        x="119"
                        y="132"
                        width="162"
                        height="127"
                        rx="8"
                        fill="black"
                    />
                </mask>
            </defs>
            <g
                v-for="layer in paintedLayers"
                :key="layer.pass"
                :clip-path="
                    layer.field === 'face'
                        ? `url(#${identity}-neck)`
                        : undefined
                "
                :mask="
                    layer.pass === 'collar'
                        ? `url(#${identity}-collar)`
                        : layer.pass === 'hair-front' ||
                            layer.pass === 'hood-front'
                          ? `url(#${identity}-hair)`
                          : undefined
                "
            >
                <svg
                    class="created-character-layer"
                    :class="`creator-layer-${layer.field} creator-part-${layer.value}`"
                    v-bind="layer.destination"
                    :viewBox="`${layer.crop.x} ${layer.crop.y} ${layer.crop.width} ${layer.crop.height}`"
                    preserveAspectRatio="xMidYMid meet"
                    overflow="hidden"
                    :style="{ filter: filter(layer.field) }"
                    :transform="
                        layer.rotation
                            ? `rotate(${layer.rotation} 200 100)`
                            : layer.mirrored
                              ? `translate(400 0) scale(-1 1)`
                              : undefined
                    "
                >
                    <defs v-if="layer.sourceClip">
                        <clipPath :id="`${identity}-${layer.pass}-crop`">
                            <path :d="layer.sourceClip" />
                        </clipPath>
                    </defs>
                    <image
                        :clip-path="
                            layer.sourceClip
                                ? `url(#${identity}-${layer.pass}-crop)`
                                : undefined
                        "
                        :href="`/assets/chanting/creator/${layer.file}.png`"
                        :width="layer.atlas.width"
                        :height="layer.atlas.height"
                    />
                </svg>
            </g>
        </svg>
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
.created-character-artboard {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
}
</style>
