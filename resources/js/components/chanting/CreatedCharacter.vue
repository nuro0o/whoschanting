<script setup lang="ts">
import { computed, useId } from 'vue';
import { defaultCreator, type CreatorRecipe } from '@/lib/creator';
import {
    creatorFitting,
    creatorViews,
    type CreatorView,
} from '@/lib/creatorLayout';
const props = withDefaults(
    defineProps<{ recipe?: CreatorRecipe | null; mode?: CreatorView }>(),
    { mode: 'portrait' },
);
const appearance = computed(() => ({ ...defaultCreator, ...props.recipe }));
const identity = useId();
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

const fitting = computed(() => creatorFitting(appearance.value));
const layers = computed(() => fitting.value.layers);
const paintedLayers = computed(() => {
    const outfit = layers.value.find((layer) => layer.field === 'outfit')!;
    const face = layers.value.find((layer) => layer.field === 'face')!;
    const hair = layers.value.find((layer) => layer.field === 'hair');
    const hood = layers.value.find(
        (layer) => layer.field === 'hat' && layer.value === 'hood',
    );
    const antlers = layers.value.find(
        (layer) => layer.field === 'hat' && layer.value === 'antlers',
    );
    return [
        { ...outfit, pass: 'back' },
        ...(hair ? [{ ...hair, pass: 'hair-back' }] : []),
        ...(hood ? [{ ...hood, pass: 'hood-back' }] : []),
        ...(antlers ? [{ ...antlers, pass: 'antlers-back' }] : []),

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
            .map((layer) => ({
                ...layer,
                sourceClip: layer.frontClip ?? layer.sourceClip,
                pass: layer.field,
            })),
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
                    <path :d="fitting.faceClip" />
                </clipPath>
                <clipPath :id="`${identity}-collar`">
                    <path :d="fitting.collarClip" />
                </clipPath>
                <clipPath :id="`${identity}-features`">
                    <path :d="fitting.faceProtection" />
                </clipPath>
                <clipPath
                    v-if="fitting.hairClip"
                    :id="`${identity}-hat-enclosure`"
                >
                    <path :d="fitting.hairClip" />
                </clipPath>
                <mask
                    v-for="coverage in ['hair', 'hood']"
                    :key="coverage"
                    :id="`${identity}-${coverage}-silhouette`"
                    maskUnits="userSpaceOnUse"
                    x="0"
                    y="0"
                    width="400"
                    height="650"
                    style="mask-type: luminance"
                >
                    <rect width="400" height="650" fill="white" />
                    <g
                        :clip-path="
                            coverage === 'hair'
                                ? `url(#${identity}-features)`
                                : undefined
                        "
                    >
                        <svg
                            v-bind="fitting.headLayer.destination"
                            :viewBox="`${fitting.headLayer.crop.x} ${fitting.headLayer.crop.y} ${fitting.headLayer.crop.width} ${fitting.headLayer.crop.height}`"
                            preserveAspectRatio="xMidYMid meet"
                            overflow="hidden"
                        >
                            <image
                                :href="`/assets/chanting/creator/${fitting.headLayer.file}.png`"
                                :width="fitting.headLayer.atlas.width"
                                :height="fitting.headLayer.atlas.height"
                                style="filter: brightness(0)"
                            />
                        </svg>
                    </g>
                </mask>
            </defs>
            <svg
                v-if="fitting.hoodLining"
                v-bind="fitting.hoodLining.destination"
                :viewBox="`${fitting.hoodLining.crop.x} ${fitting.hoodLining.crop.y} ${fitting.hoodLining.crop.width} ${fitting.hoodLining.crop.height}`"
                :transform="fitting.hoodLining.transform"
                preserveAspectRatio="xMidYMid meet"
                overflow="hidden"
            >
                <path :d="fitting.hoodLining.interior" fill="#161c12" />
            </svg>
            <g
                v-for="layer in paintedLayers"
                :key="layer.pass"
                :clip-path="
                    layer.field === 'face'
                        ? `url(#${identity}-neck)`
                        : layer.pass === 'collar'
                          ? `url(#${identity}-collar)`
                          : layer.field === 'hair' && fitting.hairClip
                            ? `url(#${identity}-hat-enclosure)`
                            : undefined
                "
                :mask="
                    layer.pass === 'hair-front'
                        ? `url(#${identity}-hair-silhouette)`
                        : layer.pass === 'hood-front'
                          ? `url(#${identity}-hood-silhouette)`
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
                    :transform="layer.transform"
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
