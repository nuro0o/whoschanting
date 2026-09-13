<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';
import { t } from '@/i18n';
import { roles } from '@/lib/chanting';
import { factionStyle, type ExpansionMetadata } from '@/lib/expansions';

const props = defineProps<{
    faeInMatch?: boolean;
    expansionInMatch?: ExpansionMetadata | null;
}>();
const page = usePage();
const glossaryId = useId();
const selectedId = ref('general');

type Definition = { term: string; meaning: string };
type Category = { id: string; name: string; terms: Definition[] };

const baseGroups = [
    {
        id: 'general',
        name: t('glossary.general'),
        terms: [
            'modes',
            'maelstrom',
            'role',
            'alignment',
            'phase',
            'small_gathering',
            'ritual_progress',
            'disrupted',
            'banishment',
            'abstain',
            'ready_for_voting',
        ],
    },
    {
        id: 'town',
        name: t('glossary.town.term'),
        terms: [
            'town',
            'townsperson',
            'oracle',
            'warden',
            'lamplighter',
            'tracker',
            'herbalist',
            'exorcist',
            'oathkeeper',
            'medium',
            'bellkeeper',
        ],
    },
    {
        id: 'cult',
        name: t('glossary.cult.term'),
        terms: [
            'cult',
            'veilweaver',
            'acolyte',
            'phantasm',
            'counterfeiter',
            'dreamweaver',
            'chant',
            'shared_mission',
            'veiled',
            'eldritch_curse',
            'soul_bind',
            'mind_mist',
            'misdirection',
        ],
    },
] as const;

function roleDefinition(id: string): Definition[] {
    const role = roles[id];
    return role ? [{ term: role.name, meaning: role.description }] : [];
}

const baseCategories: Category[] = baseGroups.map((group) => ({
    id: group.id,
    name: group.name,
    terms: [
        ...group.terms.map((id) => ({
            term: t(`glossary.${id}.term`),
            meaning: t(`glossary.${id}.description`),
        })),
        ...(group.id === 'town' ? roleDefinition('vigilante') : []),
    ],
}));

const expansionCategories = computed<Category[]>(() => {
    const catalog = [...(page.props.factionCatalog ?? [])];
    const inMatch = props.expansionInMatch;
    if (inMatch) {
        const existing = catalog.findIndex((item) => item.id === inMatch.id);
        if (existing === -1) catalog.push(inMatch);
        else catalog[existing] = inMatch;
    }

    const categories = catalog
        .filter((item) => item.id !== 'fae-court')
        .map((expansion) => ({
            id: expansion.id,
            name: expansion.name,
            terms: [
                { term: expansion.name, meaning: expansion.description },
                {
                    term: t('glossary.how_to_play'),
                    meaning: expansion.instructions,
                },
                ...expansion.roles.flatMap(roleDefinition),
            ],
        }));

    if (
        props.faeInMatch ||
        inMatch?.id === 'fae-court' ||
        page.props.activeFactions?.includes('fae-court')
    ) {
        const fae = catalog.find((item) => item.id === 'fae-court');
        categories.unshift({
            id: 'fae-court',
            name: t('roleGuide.fae.name'),
            terms: [
                {
                    term: t('roleGuide.fae.name'),
                    meaning: fae?.description ?? t('roleGuide.fae.intro'),
                },
                ...(fae?.instructions
                    ? [
                          {
                              term: t('glossary.how_to_play'),
                              meaning: fae.instructions,
                          },
                      ]
                    : []),
                ...['fae_broker', 'fae_collector'].flatMap(roleDefinition),
                ...(['thorn', 'voice', 'passage'] as const).map((gift) => ({
                    term: t(`roleGuide.fae.${gift}.name`),
                    meaning: t(`roleGuide.fae.${gift}.description`),
                })),
            ],
        });
    }
    return categories;
});

const selectedCategory = computed(
    () =>
        [...baseCategories, ...expansionCategories.value].find(
            (category) => category.id === selectedId.value,
        ) ?? baseCategories[0],
);
</script>

<template>
    <details class="game-glossary rules-details">
        <summary>
            {{ t('glossary.title') }} <span aria-hidden="true">+</span>
        </summary>
        <p class="glossary-intro">{{ t('glossary.intro') }}</p>
        <div class="glossary-navigation">
            <div
                class="glossary-categories glossary-categories--base"
                role="group"
                :aria-label="t('glossary.base_categories')"
            >
                <button
                    v-for="category in baseCategories"
                    :key="category.id"
                    type="button"
                    class="glossary-category"
                    :class="`glossary-category--${category.id}`"
                    :aria-pressed="selectedCategory.id === category.id"
                    :aria-controls="`${glossaryId}-definitions`"
                    @click="selectedId = category.id"
                >
                    {{ category.name }}
                </button>
            </div>
            <div
                v-if="expansionCategories.length"
                class="glossary-expansions"
                role="group"
                :aria-labelledby="`${glossaryId}-expansions`"
            >
                <p
                    :id="`${glossaryId}-expansions`"
                    class="glossary-group-label"
                >
                    {{ t('roleGuide.expansions.title') }}
                </p>
                <div class="glossary-categories">
                    <button
                        v-for="category in expansionCategories"
                        :key="category.id"
                        type="button"
                        class="glossary-category"
                        :style="{
                            '--category-accent':
                                factionStyle[category.id]?.color,
                        }"
                        :aria-pressed="selectedCategory.id === category.id"
                        :aria-controls="`${glossaryId}-definitions`"
                        @click="selectedId = category.id"
                    >
                        {{ category.name }}
                    </button>
                </div>
            </div>
        </div>
        <section
            :id="`${glossaryId}-definitions`"
            :aria-labelledby="`${glossaryId}-heading`"
        >
            <div class="glossary-section-heading">
                <h3 :id="`${glossaryId}-heading`">
                    {{ selectedCategory.name }}
                </h3>
                <span>{{
                    t('glossary.term_count', {
                        count: selectedCategory.terms.length,
                    })
                }}</span>
            </div>
            <dl
                :key="selectedCategory.id"
                tabindex="0"
                :aria-label="t('glossary.label')"
            >
                <div
                    v-for="{ term, meaning } in selectedCategory.terms"
                    :key="term"
                >
                    <dt>{{ term }}</dt>
                    <dd>{{ meaning }}</dd>
                </div>
            </dl>
        </section>
    </details>
</template>

<style scoped>
.glossary-navigation {
    display: grid;
    gap: 18px;
    padding: 0 24px 22px;
}
.glossary-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.glossary-category {
    --category-accent: var(--green);
    min-height: 44px;
    padding: 9px 14px;
    border: 1px solid var(--line);
    border-radius: 4px;
    background: transparent;
    color: var(--cream);
    font-size: 12px;
    line-height: 1.4;
    text-align: left;
    transition:
        background 140ms,
        border-color 140ms;
}
.glossary-category--cult {
    --category-accent: var(--coral);
}
.glossary-category:hover {
    border-color: var(--category-accent);
    background: #ffffff08;
}
.glossary-category[aria-pressed='true'] {
    border-color: var(--category-accent);
    background: var(--category-accent);
    color: var(--ink);
    font-weight: 600;
}
.glossary-category:focus-visible {
    outline: 2px solid var(--category-accent);
    outline-offset: 3px;
}
.glossary-categories--base .glossary-category {
    min-width: 80px;
    text-align: center;
}
.glossary-expansions {
    display: grid;
    gap: 9px;
}
.glossary-group-label {
    color: var(--muted);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.glossary-section-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px 14px;
    margin: 0 24px 18px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}
.glossary-section-heading h3 {
    color: var(--cream);
    font-size: 23px;
    line-height: 1.25;
}
.glossary-section-heading span {
    color: var(--muted);
    font-size: 11px;
}
@media (max-width: 600px) {
    .game-glossary dl {
        grid-template-columns: minmax(0, 1fr);
    }
}
@media (prefers-reduced-motion: reduce) {
    .glossary-category {
        transition: none;
    }
}
</style>
