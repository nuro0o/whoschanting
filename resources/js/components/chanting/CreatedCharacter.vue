<script setup lang="ts">
import { computed, useId } from 'vue';
import { defaultCreator, type CreatorRecipe } from '@/lib/creator';
import {
    creatorLayers,
    creatorChest,
    creatorViews,
    type CreatorView,
} from '@/lib/creatorLayout';
const props = withDefaults(
    defineProps<{ recipe?: CreatorRecipe | null; mode?: CreatorView }>(),
    { mode: 'portrait' },
);
const identity = useId();
const appearance = computed(() => ({ ...defaultCreator, ...props.recipe }));
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
const face = computed(
    () => layers.value.find((layer) => layer.field === 'face')!.crop,
);
const chest = computed(() => creatorChest(appearance.value));
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
    return colorFilters[field]?.[color];
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
                <linearGradient
                    :id="`${identity}-neck-fade`"
                    gradientUnits="userSpaceOnUse"
                    :x1="face.x"
                    :y1="face.y + face.height * 0.8"
                    :x2="face.x"
                    :y2="face.y + face.height"
                >
                    <stop offset="0" stop-color="white" />
                    <stop offset="1" stop-color="black" />
                </linearGradient>
                <mask
                    :id="`${identity}-neck-mask`"
                    maskUnits="userSpaceOnUse"
                    :x="face.x"
                    :y="face.y"
                    :width="face.width"
                    :height="face.height"
                >
                    <rect v-bind="face" :fill="`url(#${identity}-neck-fade)`" />
                </mask>
                <clipPath :id="`${identity}-chest`">
                    <path d="M174 244 H226 L238 266 V352 H162 V266Z" />
                </clipPath>
            </defs>
            <g :clip-path="`url(#${identity}-chest)`">
                <svg
                    v-bind="chest.destination"
                    :viewBox="chest.crop.join(' ')"
                    preserveAspectRatio="xMidYMid slice"
                    overflow="hidden"
                    :style="{ filter: filter('face') }"
                >
                    <image
                        href="/assets/chanting/creator/faces.png"
                        width="1122"
                        height="1402"
                    />
                </svg>
            </g>
            <svg
                v-for="layer in layers"
                :key="layer.field"
                class="created-character-layer"
                :class="`creator-layer-${layer.field} creator-part-${layer.value}`"
                v-bind="layer.destination"
                :viewBox="`${layer.crop.x} ${layer.crop.y} ${layer.crop.width} ${layer.crop.height}`"
                preserveAspectRatio="xMidYMid meet"
                overflow="hidden"
                :style="{ filter: filter(layer.field) }"
            >
                <image
                    :mask="
                        layer.field === 'face'
                            ? `url(#${identity}-neck-mask)`
                            : undefined
                    "
                    :href="`/assets/chanting/creator/${layer.file}.png`"
                    :width="layer.atlas.width"
                    :height="layer.atlas.height"
                />
            </svg>
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
