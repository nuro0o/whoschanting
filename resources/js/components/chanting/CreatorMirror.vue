<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    LockKeyhole,
    RotateCcw,
    Shuffle,
    Sparkles,
} from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import CreatedCharacter from './CreatedCharacter.vue';
import {
    creatorLockedOptions,
    creatorPalettes,
    defaultCreator,
    type CreatorCatalog,
    type CreatorField,
    type CreatorRecipe,
} from '@/lib/creator';

const props = defineProps<{
    catalog: CreatorCatalog;
    disabled?: boolean;
    changed: boolean;
    notice?: string;
    error?: string;
    frame: string;
    accent: string;
    background: string;
}>();
const recipe = defineModel<CreatorRecipe>({ required: true });
const emit = defineEmits<{ revert: [] }>();
const controlId = useId();
const active = ref('face');
const ripple = ref(0);
const parts = [
    { field: 'face', label: 'Face', note: 'A stranger, familiar somehow' },
    { field: 'hair', label: 'Hair', note: 'Tossed by the coastal wind' },
    { field: 'hat', label: 'Hat', note: 'Something for the journey' },
    { field: 'outfit', label: 'Clothes', note: 'Dressed for the gathering' },
    { field: 'detail', label: 'Details', note: 'A little evidence of a life' },
] as const;
const palettes = [
    { field: 'skin', label: 'Complexion' },
    { field: 'hair_color', label: 'Hair color' },
    { field: 'outfit_color', label: 'Clothing color' },
] as const;
const locked = computed(() =>
    creatorLockedOptions(recipe.value, props.catalog),
);
function selected(field: CreatorField) {
    return props.catalog.options[field].find(
        (option) =>
            option.id === (recipe.value[field] ?? defaultCreator[field]),
    );
}
function choose(field: CreatorField, value: string) {
    if (props.disabled) return;
    recipe.value = { ...recipe.value, [field]: value };
    ripple.value++;
}
function cycle(field: CreatorField, direction: number) {
    const options = props.catalog.options[field];
    const index = options.findIndex(
        (item) => item.id === (recipe.value[field] ?? defaultCreator[field]),
    );
    choose(
        field,
        options[(index + direction + options.length) % options.length].id,
    );
}
function randomize() {
    if (props.disabled) return;
    const next = { ...recipe.value };
    for (const field of Object.keys(props.catalog.options) as CreatorField[]) {
        const options = props.catalog.options[field].filter(
            (item) => item.unlocked,
        );
        if (options.length)
            (next[field] as string) =
                options[Math.floor(Math.random() * options.length)].id;
    }
    recipe.value = next;
    ripple.value++;
}
</script>
<template>
    <section class="creator-mirror" aria-labelledby="mirror-heading">
        <header class="mirror-heading">
            <p>THE LOOKING GLASS <span>·</span> YOUR OWN VILLAGER</p>
            <h3 id="mirror-heading">Who looks back?</h3>
            <p>A face of your making. A secret still your own.</p>
        </header>
        <fieldset class="mirror-body-types" :disabled="disabled">
            <legend>Body type</legend>
            <label v-for="body in catalog.options.body_type" :key="body.id">
                <input
                    type="radio"
                    :name="`${controlId}-body-type`"
                    :value="body.id"
                    :checked="
                        (recipe.body_type ?? defaultCreator.body_type) ===
                        body.id
                    "
                    @change="choose('body_type', body.id)"
                />
                <span
                    >{{ body.id === 'type1' ? 'Body type 1' : 'Body type 2'
                    }}<small>{{
                        body.id === 'type1' ? 'Male' : 'Female'
                    }}</small></span
                >
            </label>
        </fieldset>
        <div class="mirror-workshop">
            <figure class="mirror-reflection">
                <div class="mirror-crest" aria-hidden="true">✦</div>
                <div class="mirror-frame">
                    <div
                        class="mirror-glass"
                        role="img"
                        aria-label="Live reflection of your custom villager"
                    >
                        <CreatedCharacter :recipe="recipe" mode="mirror" />
                        <span
                            :key="ripple"
                            class="mirror-ripple"
                            aria-hidden="true"
                        ></span>
                        <span
                            class="mirror-glass-edge"
                            aria-hidden="true"
                        ></span>
                    </div>
                </div>
                <figcaption class="mirror-pose-control">
                    <span class="mirror-part-label">Pose</span>
                    <div class="mirror-stepper">
                        <button
                            type="button"
                            :disabled="disabled"
                            aria-label="Previous pose"
                            @click="cycle('pose', -1)"
                        >
                            <ChevronLeft :size="19" aria-hidden="true" />
                        </button>
                        <span aria-live="polite" aria-atomic="true"
                            ><strong>{{ selected('pose')?.name }}</strong
                            ><small
                                >{{
                                    catalog.options.pose.findIndex(
                                        (option) =>
                                            option.id ===
                                            (recipe.pose ??
                                                defaultCreator.pose),
                                    ) + 1
                                }}
                                / {{ catalog.options.pose.length }}</small
                            ></span
                        >
                        <button
                            type="button"
                            :disabled="disabled"
                            aria-label="Next pose"
                            @click="cycle('pose', 1)"
                        >
                            <ChevronRight :size="19" aria-hidden="true" />
                        </button>
                    </div>
                </figcaption>
            </figure>
            <div
                class="mirror-mobile-categories"
                role="group"
                aria-label="Choose a feature to change"
            >
                <button
                    v-for="part in parts"
                    :key="part.field"
                    type="button"
                    :aria-pressed="active === part.field"
                    :disabled="disabled"
                    @click="active = part.field"
                >
                    {{ part.label }}
                </button>
            </div>
            <div
                v-for="part in parts"
                :key="part.field"
                class="mirror-part"
                :class="[
                    `mirror-part-${part.field}`,
                    { 'is-active': active === part.field },
                ]"
            >
                <p :id="`${controlId}-${part.field}`" class="mirror-part-label">
                    {{ part.label }}
                </p>
                <div
                    class="mirror-stepper"
                    :class="{
                        'is-locked': selected(part.field)?.unlocked === false,
                    }"
                >
                    <button
                        type="button"
                        :disabled="disabled"
                        :aria-label="`Previous ${part.label.toLowerCase()}`"
                        @click="cycle(part.field, -1)"
                    >
                        <ChevronLeft :size="19" aria-hidden="true" />
                    </button>
                    <span aria-live="polite" aria-atomic="true"
                        ><strong>{{ selected(part.field)?.name }}</strong
                        ><small
                            >{{
                                catalog.options[part.field].findIndex(
                                    (item) => item.id === recipe[part.field],
                                ) + 1
                            }}
                            / {{ catalog.options[part.field].length }}</small
                        ></span
                    >
                    <button
                        type="button"
                        :disabled="disabled"
                        :aria-label="`Next ${part.label.toLowerCase()}`"
                        @click="cycle(part.field, 1)"
                    >
                        <ChevronRight :size="19" aria-hidden="true" />
                    </button>
                </div>
                <p class="mirror-part-note">
                    <template v-if="selected(part.field)?.unlocked === false"
                        ><LockKeyhole :size="11" aria-hidden="true" />
                        {{ selected(part.field)?.requirement }}</template
                    ><template v-else>{{ part.note }}</template>
                </p>
            </div>
        </div>
        <div class="mirror-finishing">
            <div class="mirror-palettes">
                <fieldset
                    v-for="palette in palettes"
                    :key="palette.field"
                    :disabled="disabled"
                >
                    <legend>
                        {{ palette.label }}
                        <span>{{ selected(palette.field)?.name }}</span>
                    </legend>
                    <div>
                        <label
                            v-for="color in catalog.options[palette.field]"
                            :key="color.id"
                            class="mirror-swatch"
                            :class="{
                                'is-selected':
                                    recipe[palette.field] === color.id,
                            }"
                            :title="color.name"
                        >
                            <input
                                type="radio"
                                :name="`${controlId}-${palette.field}`"
                                :value="color.id"
                                :checked="recipe[palette.field] === color.id"
                                :aria-label="color.name"
                                :disabled="!color.unlocked"
                                @change="choose(palette.field, color.id)"
                            />
                            <span
                                :style="{
                                    background:
                                        creatorPalettes[palette.field][
                                            color.id
                                        ],
                                }"
                                aria-hidden="true"
                            ></span>
                        </label>
                    </div>
                </fieldset>
            </div>
            <figure class="mirror-table-preview">
                <CharacterPortrait
                    character="custom"
                    :creator="recipe"
                    :frame="frame"
                    :accent="accent"
                    :background="background"
                />
                <figcaption>
                    <strong>At the table</strong
                    ><span>The same face, among friends.</span>
                </figcaption>
            </figure>
        </div>
        <p v-if="locked.length" class="mirror-locked-note" role="status">
            <LockKeyhole :size="15" aria-hidden="true" /><span
                >Previewing locked pieces:
                {{
                    locked
                        .map((item) => `${item.name} — ${item.requirement}`)
                        .join('; ')
                }}. Choose unlocked pieces to save.</span
            >
        </p>
        <p
            v-if="error"
            class="mirror-feedback mirror-feedback--error"
            role="alert"
        >
            {{ error }}
        </p>
        <p v-else class="mirror-feedback" role="status">
            {{
                disabled
                    ? 'Saving your reflection…'
                    : notice ||
                      (changed
                          ? 'Your reflection has unsaved changes.'
                          : 'Your saved appearance is ready to wear.')
            }}
        </p>
        <footer class="mirror-actions">
            <button
                type="button"
                :disabled="disabled"
                class="mirror-randomize"
                @click="randomize"
            >
                <Shuffle :size="15" aria-hidden="true" /> Randomize
            </button>
            <div>
                <button
                    type="button"
                    :disabled="disabled || !changed"
                    class="mirror-revert"
                    @click="emit('revert')"
                >
                    <RotateCcw :size="14" aria-hidden="true" /> Revert
                </button>
                <button
                    type="submit"
                    :disabled="disabled || !changed || locked.length > 0"
                    class="mirror-save"
                >
                    <Sparkles :size="15" aria-hidden="true" />{{
                        disabled ? 'Saving…' : 'Save appearance'
                    }}
                </button>
            </div>
        </footer>
    </section>
</template>
<style scoped>
.creator-mirror {
    --mirror-ink: #13282b;
    --mirror-paper: #eee5cd;
    --mirror-muted: #b9c3b6;
    --mirror-brass: #c4a879;
    color: var(--mirror-paper);
    background: radial-gradient(
        ellipse at 50% 42%,
        #314541 0,
        #172e30 40%,
        #112326 77%
    );
    padding: 30px 34px 24px;
    border: 1px solid #667669;
    box-shadow: 5px 5px 0 #0b191b;
}
.mirror-heading {
    text-align: center;
}
.mirror-heading > p:first-child {
    font-size: 9px;
    letter-spacing: 2px;
    color: var(--mirror-brass);
}
.mirror-heading > p:first-child span {
    margin: 0 9px;
}
.mirror-heading h3 {
    font-family: 'Fraunces', Georgia, serif;
    font-size: clamp(30px, 3vw, 42px);
    letter-spacing: -1.2px;
    margin: 8px 0;
    font-weight: 400;
}
.mirror-heading > p:last-child {
    font-size: 11px;
    color: var(--mirror-muted);
}
.mirror-body-types {
    display: flex;
    justify-content: center;
    gap: 10px;
    border: 0;
    margin: 22px auto 4px;
    padding: 0;
}
.mirror-body-types legend {
    width: 100%;
    text-align: center;
    margin-bottom: 9px;
    color: var(--mirror-brass);
    text-transform: uppercase;
    letter-spacing: 1.8px;
    font-size: 9px;
}
.mirror-body-types label {
    position: relative;
    cursor: pointer;
}
.mirror-body-types input {
    position: absolute;
    opacity: 0;
}
.mirror-body-types label > span {
    display: block;
    min-width: 118px;
    padding: 9px 20px;
    text-align: center;
    font-size: 11px;
    border: 1px solid #71816a77;
    background: #13282b;
}
.mirror-body-types small {
    display: block;
    margin-top: 3px;
    color: var(--mirror-muted);
    font-size: 9px;
}
.mirror-body-types input:checked + span {
    border-color: var(--mirror-brass);
    background: #c4a87920;
}
.mirror-body-types input:focus-visible + span {
    outline: 2px solid #e7d59f;
    outline-offset: 3px;
}
.mirror-body-types:disabled {
    opacity: 0.45;
}
.mirror-pose-control .mirror-part-label {
    display: block;
    margin: 0 0 7px;
}
.mirror-pose-control .mirror-stepper {
    min-height: 50px;
}
.mirror-pose-control .mirror-stepper strong {
    font-size: 16px;
}
.mirror-pose-control .mirror-stepper button {
    min-width: 34px;
}
.mirror-workshop {
    display: grid;
    grid-template-columns: minmax(130px, 1fr) minmax(220px, 320px) minmax(
            130px,
            1fr
        );
    grid-template-rows: repeat(3, 1fr);
    column-gap: 25px;
    align-items: center;
    margin: 24px auto 14px;
    max-width: 870px;
}
.mirror-reflection {
    grid-column: 2;
    grid-row: 1 / 4;
    margin: 0;
    position: relative;
    padding: 12px 10px 0;
}
.mirror-frame {
    padding: 9px;
    border-radius: 50% 50% 5px 5px / 39% 39% 5px 5px;
    border: 2px solid #a49064;
    background: linear-gradient(
        110deg,
        #334338,
        #9a835d 18%,
        #3d4a3c 25%,
        #c1a476 48%,
        #405043 73%,
        #a38b62 87%,
        #344336
    );
    box-shadow:
        0 0 0 3px #26382f,
        0 0 0 4px #6b7558,
        0 18px 28px #030e1266;
}
.mirror-glass {
    position: relative;
    overflow: hidden;
    aspect-ratio: 4 / 5;
    border-radius: inherit;
    background: radial-gradient(
        ellipse at 50% 38%,
        #687d70,
        #344d48 49%,
        #172d30 85%
    );
    border: 1px solid #111f1e;
}
.mirror-glass :deep(.created-character) {
    inset: 0;
}
.mirror-glass-edge {
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    box-shadow:
        inset 0 0 26px #06141499,
        inset 2px 0 2px #eee8bd33;
    background: linear-gradient(
        115deg,
        #f1f3c10d,
        transparent 36%,
        transparent 66%,
        #bdcdb409
    );
}
.mirror-crest {
    position: absolute;
    z-index: 1;
    left: calc(50% - 15px);
    top: -5px;
    width: 30px;
    text-align: center;
    color: #dfc98f;
    font-size: 28px;
    text-shadow: 0 2px 5px #071414;
}
.mirror-reflection figcaption {
    color: #b1bdae;
    text-align: center;
    font-size: 9px;
    letter-spacing: 0.8px;
    margin-top: 14px;
}
.mirror-ripple {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        145deg,
        transparent 20%,
        #c6ddc12b 45%,
        transparent 65%
    );
    animation: glass-ripple 0.65s ease-out both;
    pointer-events: none;
}
@keyframes glass-ripple {
    from {
        opacity: 0;
        transform: translateY(-55%);
    }
    30% {
        opacity: 1;
    }
    to {
        opacity: 0;
        transform: translateY(55%);
    }
}
.mirror-part-face {
    grid-column: 1;
    grid-row: 1;
}
.mirror-part-hair {
    grid-column: 1;
    grid-row: 2;
}
.mirror-part-hat {
    grid-column: 3;
    grid-row: 1;
}
.mirror-part-outfit {
    grid-column: 3;
    grid-row: 2;
}
.mirror-part-detail {
    grid-column: 3;
    grid-row: 3;
}
.mirror-part-label {
    font-size: 10px;
    color: var(--mirror-brass);
    text-transform: uppercase;
    letter-spacing: 1.8px;
    margin-bottom: 8px;
}
.mirror-stepper {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) 34px;
    align-items: center;
    border-top: 1px solid #71816a77;
    border-bottom: 1px solid #71816a77;
    min-height: 57px;
}
.mirror-stepper > span {
    text-align: center;
    padding: 4px;
}
.mirror-stepper strong {
    display: block;
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 400;
    font-size: 17px;
    line-height: 1.25;
}
.mirror-stepper small {
    display: block;
    font-size: 9px;
    margin-top: 4px;
    color: var(--mirror-muted);
}
.mirror-stepper button {
    min-height: 44px;
    display: grid;
    place-items: center;
    color: var(--mirror-paper);
}
.mirror-stepper button:hover:not(:disabled) {
    background: #cbcca315;
    color: #e3ce92;
}
.mirror-stepper.is-locked {
    border-color: #c39267;
}
.mirror-part-note {
    font-size: 9px;
    line-height: 1.5;
    color: var(--mirror-muted);
    margin-top: 8px;
    min-height: 27px;
}
.mirror-part-note svg {
    display: inline;
    vertical-align: -1px;
}
.mirror-mobile-categories {
    display: none;
}
.mirror-finishing {
    display: flex;
    gap: 24px;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #71816a55;
    padding: 20px 0;
}
.mirror-palettes {
    display: flex;
    flex-wrap: wrap;
    gap: 18px 26px;
}
.mirror-palettes fieldset {
    border: 0;
    padding: 0;
    min-width: 0;
}
.mirror-palettes legend {
    font-size: 10px;
    margin-bottom: 8px;
}
.mirror-palettes legend span {
    display: block;
    color: var(--mirror-muted);
    font-size: 9px;
    margin-top: 4px;
}
.mirror-palettes fieldset > div {
    display: flex;
    gap: 7px;
}
.mirror-swatch {
    position: relative;
    width: 28px;
    height: 32px;
    cursor: pointer;
    display: grid;
    place-items: center;
}
.mirror-swatch input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.mirror-swatch > span {
    width: 23px;
    height: 23px;
    border-radius: 50%;
    border: 1px solid #dbd9b65c;
    pointer-events: none;
}
.mirror-swatch.is-selected > span {
    outline: 1px solid #e7d59f;
    outline-offset: 3px;
}
.mirror-swatch:focus-within {
    outline: 2px solid #e7d59f;
    outline-offset: 4px;
}
.mirror-table-preview {
    display: flex;
    gap: 15px;
    align-items: center;
    margin: 0;
    padding-left: 25px;
    border-left: 1px solid #71816a55;
}
.mirror-table-preview .character-portrait {
    width: 61px;
    height: 68px;
    border-radius: 29px 29px 5px 5px;
    flex-shrink: 0;
}
.mirror-table-preview strong,
.mirror-table-preview span {
    display: block;
    font-size: 10px;
    font-weight: 400;
}
.mirror-table-preview span {
    color: var(--mirror-muted);
    line-height: 1.5;
    margin-top: 5px;
    max-width: 100px;
}
.mirror-locked-note {
    display: flex;
    align-items: start;
    gap: 8px;
    color: #e5bd8e;
    font-size: 11px;
    line-height: 1.65;
    margin: 0 0 16px;
}
.mirror-locked-note svg {
    flex-shrink: 0;
    margin-top: 3px;
}
.mirror-actions,
.mirror-actions > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.mirror-feedback {
    font-size: 11px;
    color: var(--mirror-muted);
    margin-bottom: 14px;
}
.mirror-feedback--error {
    color: #efb5a2;
}
.mirror-actions {
    padding-top: 18px;
    border-top: 1px solid #71816a55;
}
.mirror-actions button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 14px;
    font-size: 11px;
}
.mirror-randomize {
    color: var(--mirror-muted);
    padding-left: 0 !important;
}
.mirror-revert {
    border: 1px solid #71816a77;
}
.mirror-save {
    background: #c1cca2;
    color: #172b2b;
}
button:focus-visible {
    outline: 2px solid #e7d59f;
    outline-offset: 4px;
}
button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.mirror-actions button:hover:not(:disabled) {
    filter: brightness(1.2);
}
@media (max-width: 1050px) {
    .creator-mirror {
        padding: 26px 22px 20px;
    }
    .mirror-workshop {
        gap: 16px;
        grid-template-columns: minmax(110px, 1fr) minmax(180px, 270px) minmax(
                110px,
                1fr
            );
    }
    .mirror-stepper {
        grid-template-columns: 28px minmax(0, 1fr) 28px;
    }
    .mirror-stepper strong {
        font-size: 15px;
    }
    .mirror-table-preview {
        padding-left: 15px;
    }
    .mirror-table-preview figcaption {
        display: none;
    }
}
@media (max-width: 700px) {
    .creator-mirror {
        padding: 22px 16px 18px;
    }
    .mirror-heading > p:first-child {
        font-size: 8px;
        letter-spacing: 1.2px;
    }
    .mirror-heading h3 {
        font-size: 32px;
    }
    .mirror-workshop {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin: 18px 0 15px;
    }
    .mirror-reflection {
        width: min(100%, 290px);
    }
    .mirror-mobile-categories {
        display: flex;
        width: 100%;
        border-bottom: 1px solid #71816a77;
        margin-top: 18px;
    }
    .mirror-mobile-categories button {
        flex: 1;
        font-size: 10px;
        min-height: 44px;
        color: var(--mirror-muted);
    }
    .mirror-mobile-categories button[aria-pressed='true'] {
        color: #ecdfb8;
        box-shadow: inset 0 -2px #c4a879;
    }
    .mirror-part {
        display: none;
        width: 100%;
        margin-top: 14px;
    }
    .mirror-part.is-active {
        display: block;
    }
    .mirror-part-label {
        display: none;
    }
    .mirror-stepper {
        grid-template-columns: 48px minmax(0, 1fr) 48px;
        border-top: 0;
        border-bottom: 0;
        min-height: 48px;
    }
    .mirror-stepper strong {
        font-size: 21px;
    }
    .mirror-stepper button {
        border: 1px solid #71816a77;
    }
    .mirror-part-note {
        text-align: center;
        min-height: 14px;
        margin-top: 7px;
    }
    .mirror-finishing {
        flex-direction: column;
        align-items: stretch;
        gap: 20px;
        padding-top: 18px;
    }
    .mirror-palettes {
        justify-content: space-between;
        gap: 14px;
    }
    .mirror-palettes legend {
        font-size: 9px;
    }
    .mirror-palettes fieldset > div {
        gap: 2px;
    }
    .mirror-swatch {
        width: 23px;
    }
    .mirror-swatch > span {
        width: 18px;
        height: 18px;
    }
    .mirror-table-preview {
        border-left: 0;
        padding: 0;
        justify-content: center;
    }
    .mirror-table-preview figcaption {
        display: block;
    }
    .mirror-table-preview span {
        max-width: none;
    }
    .mirror-actions {
        flex-wrap: wrap;
    }
    .mirror-actions > div {
        flex: 1;
        justify-content: end;
        gap: 8px;
    }
    .mirror-actions button {
        padding: 10px;
        font-size: 10px;
    }
}
@media (max-width: 390px) {
    .mirror-palettes {
        justify-content: center;
        gap: 18px;
    }
    .mirror-actions > div {
        width: 100%;
        flex-basis: 100%;
    }
    .mirror-actions > div > button {
        flex: 1;
    }
    .mirror-randomize {
        margin: auto;
    }
}
@media (prefers-reduced-motion: reduce) {
    .mirror-ripple {
        animation: none;
        opacity: 0;
    }
}
</style>
