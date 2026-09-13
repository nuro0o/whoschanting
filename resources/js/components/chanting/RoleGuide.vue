<script setup lang="ts">
import {
    ChevronDown,
    Eye,
    Flower2,
    Moon,
    Shield,
    Sprout,
    Users,
    Volume2,
} from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { t } from '@/i18n';
import { roles } from '@/lib/chanting';
import { factionStyle, type ExpansionMetadata } from '@/lib/expansions';

const props = withDefaults(
    defineProps<{
        compact?: boolean;
        faeInMatch?: boolean;
        expansionInMatch?: ExpansionMetadata | null;
        minimumPlayers?: Record<string, number>;
    }>(),
    { compact: false, faeInMatch: false },
);
const page = usePage();
const visibleExpansions = computed(() => {
    const catalog = (page.props.factionCatalog ?? []).filter(
        (item) => item.id !== 'fae-court',
    );
    if (props.compact)
        return props.expansionInMatch ? [props.expansionInMatch] : [];
    return catalog;
});
const showFae = computed(
    () =>
        props.faeInMatch ||
        (!props.compact && page.props.activeFactions?.includes('fae-court')),
);
const headingId = useId();
const expanded = ref<Record<string, boolean>>({ town: false, cult: false });
const allegiances = [
    {
        id: 'town',
        icon: Users,
        name: t('roleGuide.town.name'),
        purpose: t('roleGuide.town.purpose'),
        roles: [
            'vigilante',
            'townsperson',
            'oracle',
            'warden',
            'lamplighter',
            'medium',
            'bellkeeper',
            'exorcist',
            'oathkeeper',
            'tracker',
            'herbalist',
        ],
    },
    {
        id: 'cult',
        icon: Eye,
        name: t('roleGuide.cult.name'),
        purpose: t('roleGuide.cult.purpose'),
        roles: [
            'veilweaver',
            'acolyte',
            'dreamweaver',
            'phantasm',
            'counterfeiter',
        ],
    },
];
const gifts = [
    {
        id: 'thorn',
        icon: Shield,
        name: t('roleGuide.fae.thorn.name'),
        description: t('roleGuide.fae.thorn.description'),
    },
    {
        id: 'voice',
        icon: Volume2,
        name: t('roleGuide.fae.voice.name'),
        description: t('roleGuide.fae.voice.description'),
    },
    {
        id: 'passage',
        icon: Moon,
        name: t('roleGuide.fae.passage.name'),
        description: t('roleGuide.fae.passage.description'),
    },
];
</script>

<template>
    <section
        class="role-guide"
        :class="{ 'role-guide--compact': compact }"
        :aria-labelledby="headingId"
    >
        <header class="role-guide__header">
            <p class="eyebrow">{{ t('roleGuide.eyebrow') }}</p>
            <component
                :is="compact ? 'h3' : 'h2'"
                :id="headingId"
                class="role-guide__title"
                >{{
                    compact
                        ? t('roleGuide.compact_title')
                        : t('roleGuide.title')
                }}</component
            >
            <p class="role-guide__intro">
                {{
                    compact
                        ? t('roleGuide.intro')
                        : t('roleGuide.collections_intro')
                }}
            </p>
        </header>
        <div class="role-guide__sides">
            <section
                v-for="side in allegiances"
                :key="side.id"
                class="role-guide__side"
                :class="[
                    `role-guide__side--${side.id}`,
                    { 'is-expanded': expanded[side.id] },
                ]"
                :aria-labelledby="`${headingId}-${side.id}`"
            >
                <div v-if="compact" class="role-guide__allegiance">
                    <h4 :id="`${headingId}-${side.id}`">{{ side.name }}</h4>
                    <p>{{ side.purpose }}</p>
                </div>
                <h3 v-else class="role-guide__row-heading">
                    <button
                        :id="`${headingId}-${side.id}`"
                        type="button"
                        class="role-guide__toggle"
                        :aria-expanded="expanded[side.id]"
                        :aria-controls="`${headingId}-${side.id}-roles`"
                        @click="expanded[side.id] = !expanded[side.id]"
                    >
                        <span class="role-guide__emblem" aria-hidden="true"
                            ><component
                                :is="side.icon"
                                :size="28"
                                :stroke-width="1.4"
                        /></span>
                        <span class="role-guide__row-copy"
                            ><span class="role-guide__faction-name">{{
                                side.name
                            }}</span
                            ><span class="role-guide__purpose">{{
                                side.purpose
                            }}</span></span
                        >
                        <span class="role-guide__count">{{
                            t('roleGuide.role_count', {
                                count: side.roles.length,
                            })
                        }}</span>
                        <ChevronDown
                            class="role-guide__chevron"
                            :size="21"
                            aria-hidden="true"
                        />
                    </button>
                </h3>
                <ul
                    :id="`${headingId}-${side.id}-roles`"
                    v-show="compact || expanded[side.id]"
                    class="role-guide__list"
                >
                    <li
                        v-for="roleId in side.roles"
                        :key="roleId"
                        class="role-guide__entry"
                    >
                        <span class="role-guide__symbol" aria-hidden="true">{{
                            roles[roleId].symbol
                        }}</span>
                        <div>
                            <component
                                :is="compact ? 'h5' : 'h4'"
                                class="role-guide__name"
                                >{{ roles[roleId].name }}</component
                            >
                            <p class="role-guide__subtitle">
                                {{ roles[roleId].subtitle }}
                            </p>
                            <p
                                v-if="minimumPlayers?.[roleId]"
                                class="role-guide__threshold"
                            >
                                {{
                                    t('roleGuide.minimum_players', {
                                        value: minimumPlayers[roleId],
                                    })
                                }}
                            </p>
                            <p class="role-guide__description">
                                {{ roles[roleId].description }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
        <section
            class="role-guide__expansions"
            v-if="showFae || visibleExpansions.length"
            :aria-labelledby="`${headingId}-expansions`"
        >
            <header class="role-guide__expansions-heading">
                <component
                    :is="compact ? 'h4' : 'h2'"
                    :id="`${headingId}-expansions`"
                    class="role-guide__title"
                    >{{ t('roleGuide.expansions.title') }}</component
                >
                <p v-if="!compact" class="role-guide__intro">
                    {{ t('roleGuide.expansions.intro') }}
                </p>
            </header>
            <article
                v-if="showFae"
                class="role-guide__fae"
                :class="{ 'is-expanded': expanded['fae-court'] }"
                :aria-labelledby="`${headingId}-fae`"
            >
                <h3 v-if="!compact" class="role-guide__row-heading">
                    <button
                        :id="`${headingId}-fae`"
                        type="button"
                        class="role-guide__toggle"
                        :aria-expanded="!!expanded['fae-court']"
                        :aria-controls="`${headingId}-fae-details`"
                        @click="expanded['fae-court'] = !expanded['fae-court']"
                    >
                        <span class="role-guide__emblem" aria-hidden="true"
                            ><Flower2 :size="28" :stroke-width="1.4"
                        /></span>
                        <span class="role-guide__row-copy">
                            <span class="role-guide__faction-name">{{
                                t('roleGuide.fae.name')
                            }}</span>
                            <span class="role-guide__purpose">{{
                                t('roleGuide.fae.bargains_intro')
                            }}</span>
                        </span>
                        <span class="role-guide__count">{{
                            t('roleGuide.role_count', { count: 2 })
                        }}</span>
                        <ChevronDown
                            class="role-guide__chevron"
                            :size="21"
                            aria-hidden="true"
                        />
                    </button>
                </h3>
                <div
                    :id="`${headingId}-fae-details`"
                    v-show="compact || expanded['fae-court']"
                    class="role-guide__expansion-details"
                >
                    <div class="role-guide__fae-introduction">
                        <span
                            v-if="!compact"
                            class="role-guide__fae-emblem"
                            aria-hidden="true"
                            ><Flower2 :size="42" :stroke-width="1"
                        /></span>
                        <div>
                            <p class="role-guide__expansion-label">
                                {{ t('roleGuide.fae.eyebrow') }}
                            </p>
                            <component
                                v-if="compact"
                                :is="'h5'"
                                :id="`${headingId}-fae`"
                                class="role-guide__fae-name"
                                >{{ t('roleGuide.fae.name') }}</component
                            >
                            <p class="role-guide__intro">
                                {{ t('roleGuide.fae.intro') }}
                            </p>
                            <p class="role-guide__contents">
                                {{ t('roleGuide.fae.contents') }}
                            </p>
                        </div>
                    </div>
                    <div class="role-guide__fae-details">
                        <div
                            v-for="role in [
                                roles.fae_broker,
                                roles.fae_collector,
                            ]"
                            :key="role.name"
                            class="role-guide__broker role-guide__entry"
                        >
                            <span
                                class="role-guide__symbol"
                                aria-hidden="true"
                                >{{ role.symbol }}</span
                            >
                            <div>
                                <component
                                    :is="compact ? 'h6' : 'h4'"
                                    class="role-guide__name"
                                    >{{ role.name }}</component
                                >
                                <p class="role-guide__subtitle">
                                    {{ role.subtitle }}
                                </p>
                                <p class="role-guide__description">
                                    {{ role.description }}
                                </p>
                            </div>
                        </div>
                        <section
                            v-if="!compact"
                            class="role-guide__bargains"
                            :aria-labelledby="`${headingId}-bargains`"
                        >
                            <h4 :id="`${headingId}-bargains`">
                                {{ t('roleGuide.fae.bargains_title') }}
                            </h4>
                            <p class="role-guide__bargains-intro">
                                {{ t('roleGuide.fae.bargains_intro') }}
                            </p>
                            <ul>
                                <li v-for="gift in gifts" :key="gift.id">
                                    <component
                                        :is="gift.icon"
                                        :size="18"
                                        :stroke-width="1.5"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h5>{{ gift.name }}</h5>
                                        <p>{{ gift.description }}</p>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>
                    <footer class="role-guide__fae-footer">
                        <p>
                            <Sprout :size="17" aria-hidden="true" />{{
                                t('roleGuide.fae.sharing')
                            }}
                        </p>
                        <p>{{ t('roleGuide.fae.support') }}</p>
                    </footer>
                </div>
            </article>
            <article
                v-for="expansion in visibleExpansions"
                :key="expansion.id"
                class="role-guide__new-expansion"
                :class="{ 'is-expanded': expanded[expansion.id] }"
                :aria-labelledby="`${headingId}-${expansion.id}`"
                :style="{ '--role-accent': factionStyle[expansion.id]?.color }"
            >
                <h3 v-if="!compact" class="role-guide__row-heading">
                    <button
                        :id="`${headingId}-${expansion.id}`"
                        type="button"
                        class="role-guide__toggle"
                        :aria-expanded="!!expanded[expansion.id]"
                        :aria-controls="`${headingId}-${expansion.id}-details`"
                        @click="
                            expanded[expansion.id] = !expanded[expansion.id]
                        "
                    >
                        <span
                            class="role-guide__emblem role-guide__expansion-symbol"
                            aria-hidden="true"
                            >{{ factionStyle[expansion.id]?.symbol }}</span
                        >
                        <span class="role-guide__row-copy">
                            <span class="role-guide__faction-name">{{
                                expansion.name
                            }}</span>
                            <span class="role-guide__purpose">{{
                                expansion.description
                            }}</span>
                        </span>
                        <span class="role-guide__count">{{
                            t('roleGuide.role_count', {
                                count: expansion.roles.length,
                            })
                        }}</span>
                        <ChevronDown
                            class="role-guide__chevron"
                            :size="21"
                            aria-hidden="true"
                        />
                    </button>
                </h3>
                <div
                    :id="`${headingId}-${expansion.id}-details`"
                    v-show="compact || expanded[expansion.id]"
                    class="role-guide__expansion-details"
                >
                    <header v-if="compact" class="role-guide__new-heading">
                        <span
                            class="role-guide__new-emblem"
                            aria-hidden="true"
                            >{{ factionStyle[expansion.id]?.symbol }}</span
                        >
                        <div>
                            <p class="role-guide__expansion-label">
                                ROOM EXPANSION · {{ expansion.min_players }}–15
                                PLAYERS
                            </p>
                            <component
                                :is="compact ? 'h5' : 'h3'"
                                :id="`${headingId}-${expansion.id}`"
                                class="role-guide__fae-name"
                                >{{ expansion.name }}</component
                            >
                            <p class="role-guide__intro">
                                {{ expansion.description }}
                            </p>
                        </div>
                    </header>
                    <p v-if="!compact" class="role-guide__expansion-label">
                        ROOM EXPANSION · {{ expansion.min_players }}–15 PLAYERS
                    </p>
                    <p class="role-guide__new-objective">
                        {{ expansion.instructions }}
                    </p>
                    <ul class="role-guide__new-roles">
                        <li
                            v-for="roleId in expansion.roles"
                            :key="roleId"
                            class="role-guide__entry"
                        >
                            <span
                                class="role-guide__symbol"
                                aria-hidden="true"
                                >{{ roles[roleId]?.symbol }}</span
                            >
                            <div>
                                <component
                                    :is="compact ? 'h6' : 'h4'"
                                    class="role-guide__name"
                                    >{{
                                        roles[roleId]?.name ?? roleId
                                    }}</component
                                >
                                <p class="role-guide__subtitle">
                                    {{ roles[roleId]?.subtitle }}
                                </p>
                                <p class="role-guide__description">
                                    {{ roles[roleId]?.description }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <footer class="role-guide__fae-footer">
                        <p>
                            <Users :size="17" aria-hidden="true" />One owner
                            unlocks the whole room. Anyone can be dealt these
                            roles.
                        </p>
                        <p>
                            Classic or Classic Illusions · one expansion per
                            room · victory can be shared with Town or Cult.
                        </p>
                    </footer>
                </div>
            </article>
        </section>
    </section>
</template>

<style scoped>
.role-guide:not(.role-guide--compact) .role-guide__fae,
.role-guide:not(.role-guide--compact) .role-guide__new-expansion {
    padding: 0;
    border: 1px solid var(--line);
    border-left: 3px solid var(--role-accent);
}
.role-guide:not(.role-guide--compact) .role-guide__new-expansion {
    margin-top: 14px;
}
.role-guide__expansion-symbol {
    font-size: 28px;
}
.role-guide:not(.role-guide--compact) .role-guide__expansion-details {
    border-top: 1px solid var(--line);
}
.role-guide:not(.role-guide--compact)
    .role-guide__new-expansion
    .role-guide__expansion-details {
    padding: clamp(18px, 3vw, 28px);
}

.role-guide__new-expansion {
    margin-top: 20px;
    border: 1px solid var(--line);
    border-top: 3px solid var(--role-accent);
    padding: clamp(18px, 3vw, 32px);
}
.role-guide__new-heading {
    display: flex;
    align-items: center;
    gap: 22px;
}
.role-guide__new-emblem {
    color: var(--role-accent);
    font-size: 52px;
}
.role-guide__new-objective {
    padding-block: 18px;
    border-block: 1px solid var(--line);
    line-height: 1.75;
    color: var(--cream);
    font-size: 13px;
}
.role-guide__new-roles {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    list-style: none;
    padding: 12px 0;
}
@media (max-width: 640px) {
    .role-guide__new-roles {
        grid-template-columns: 1fr;
    }
    .role-guide__new-heading {
        gap: 12px;
    }
}
.role-guide {
    padding-block: 52px;
    border-top: 1px solid var(--line);
    scroll-margin-top: 25px;
}
.role-guide__header {
    max-width: 670px;
    margin-bottom: 30px;
}
.role-guide .role-guide__title {
    margin-block: 12px;
    font-family: 'Fraunces', Georgia, serif;
    font-size: clamp(30px, 3.5vw, 42px);
    font-weight: 400;
    line-height: 1.2;
    letter-spacing: -0.6px;
}
.role-guide__intro {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.75;
}
.role-guide__sides {
    display: grid;
    gap: 14px;
}
.role-guide__side {
    --role-accent: var(--green);
    min-width: 0;
    border: 1px solid var(--line);
    border-left: 3px solid var(--role-accent);
}
.role-guide__side--cult {
    --role-accent: var(--coral);
}
.role-guide__row-heading {
    margin: 0;
}
.role-guide__toggle {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr) auto 24px;
    align-items: center;
    gap: 20px;
    width: 100%;
    padding: 23px 26px;
    border: 0;
    background: var(--panel);
    color: var(--cream);
    text-align: left;
    transition: background 160ms ease;
}
.role-guide__toggle:hover {
    background: color-mix(in srgb, var(--role-accent) 9%, var(--panel));
}
.role-guide__toggle:focus-visible {
    position: relative;
    z-index: 1;
    outline: 2px solid var(--role-accent);
    outline-offset: 3px;
}
.role-guide__toggle:active {
    background: color-mix(in srgb, var(--role-accent) 14%, var(--panel));
}
.role-guide__emblem {
    display: grid;
    place-items: center;
    width: 54px;
    height: 54px;
    border: 1px solid color-mix(in srgb, var(--role-accent) 35%, transparent);
    border-radius: 50%;
    color: var(--role-accent);
}
.role-guide__row-copy {
    display: grid;
    gap: 3px;
}
.role-guide__faction-name {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 29px;
    font-weight: 400;
    line-height: 1.2;
}
.role-guide__purpose {
    color: var(--muted);
    font-size: 12px;
    font-weight: 400;
}
.role-guide__count {
    color: var(--role-accent);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}
.role-guide__chevron {
    color: var(--role-accent);
    transition: transform 180ms ease;
}
.is-expanded .role-guide__chevron {
    transform: rotate(180deg);
}
.role-guide__list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 36px;
    padding: 4px 28px 10px;
    margin: 0;
    list-style: none;
    border-top: 1px solid var(--line);
}
.role-guide__entry {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    align-content: start;
    gap: 15px;
    padding-block: 26px;
}
.role-guide__list .role-guide__entry:nth-child(n + 3) {
    border-top: 1px solid var(--line);
}
.role-guide__symbol {
    color: var(--role-accent);
    font-family: Georgia, serif;
    font-size: 31px;
    line-height: 1.15;
    text-align: center;
}
.role-guide__name {
    margin: 0;
    color: var(--cream);
    font-family: 'Fraunces', Georgia, serif;
    font-size: 24px;
    font-weight: 400;
    line-height: 1.2;
}
.role-guide .role-guide__subtitle {
    margin-top: 6px;
    color: var(--role-accent);
    font-size: 11px;
}
.role-guide .role-guide__threshold {
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
}
.role-guide .role-guide__description {
    margin-top: 12px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.75;
}
.role-guide__allegiance {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 14px;
    padding-top: 16px;
}
.role-guide__allegiance h4 {
    margin: 0;
    color: var(--role-accent);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}
.role-guide__allegiance p {
    color: var(--muted);
    font-size: 12px;
}
.role-guide__expansions {
    margin-top: 46px;
}
.role-guide__expansions-heading {
    margin-bottom: 24px;
}
.role-guide__fae {
    --role-accent: var(--curse);
    border: 1px solid color-mix(in srgb, var(--curse) 32%, var(--line));
    background: linear-gradient(120deg, #c6addc0a, transparent 65%);
}
.role-guide__fae-introduction {
    display: flex;
    align-items: flex-start;
    gap: 24px;
    padding: 30px 30px 25px;
}
.role-guide__fae-introduction > div {
    max-width: 680px;
}
.role-guide__fae-emblem {
    display: grid;
    place-items: center;
    width: 68px;
    height: 82px;
    flex-shrink: 0;
    border: 1px solid #c6addc55;
    border-radius: 38px 38px 4px 4px;
    color: var(--curse);
}
.role-guide__expansion-label {
    color: var(--curse);
    font-size: 9px;
    letter-spacing: 1.5px;
    font-weight: 600;
}
.role-guide .role-guide__fae-name {
    margin-block: 8px 12px;
    color: var(--cream);
    font-family: 'Fraunces', Georgia, serif;
    font-size: 33px;
    font-weight: 400;
    line-height: 1.2;
}
.role-guide .role-guide__contents {
    margin-top: 14px;
    color: var(--curse);
    font-size: 12px;
}
.role-guide__fae-details {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 36px;
    margin: 0 30px;
    padding-block: 26px;
    border-top: 1px solid var(--line);
}
.role-guide__broker {
    padding: 0;
}
.role-guide__bargains {
    padding-left: 30px;
    border-left: 1px solid var(--line);
}
.role-guide__bargains h4 {
    margin: 0;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 22px;
    font-weight: 400;
}
.role-guide .role-guide__bargains-intro {
    margin-top: 6px;
    color: var(--muted);
    font-size: 12px;
}
.role-guide__bargains ul {
    display: grid;
    gap: 17px;
    margin: 22px 0 0;
    padding: 0;
    list-style: none;
}
.role-guide__bargains li {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr);
    gap: 12px;
    color: var(--curse);
}
.role-guide__bargains h5 {
    margin: 0 0 4px;
    color: var(--cream);
    font-size: 12px;
    font-weight: 500;
}
.role-guide__bargains li p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}
.role-guide__fae-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 24px;
    padding: 18px 30px;
    border-top: 1px solid var(--line);
    font-size: 11px;
    color: var(--muted);
}
.role-guide__fae-footer p:first-child {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--curse);
}
.role-guide--compact {
    margin-top: 24px;
    padding-block: 24px 0;
}
.role-guide--compact .role-guide__header {
    margin-bottom: 24px;
}
.role-guide--compact .role-guide__title {
    font-size: 26px;
}
.role-guide--compact .role-guide__sides {
    gap: 8px;
}
.role-guide--compact .role-guide__side {
    border: 0;
    border-top: 2px solid var(--role-accent);
}
.role-guide--compact .role-guide__list {
    grid-template-columns: 1fr;
    padding: 0;
    border: 0;
}
.role-guide--compact .role-guide__entry + .role-guide__entry {
    border-top: 1px solid var(--line);
}
.role-guide--compact .role-guide__name {
    font-size: 22px;
}
.role-guide--compact .eyebrow {
    font-size: 9px;
    letter-spacing: 1.2px;
}
.role-guide--compact .role-guide__expansions {
    margin-top: 24px;
}
.role-guide--compact .role-guide__expansions-heading {
    margin-bottom: 16px;
}
.role-guide--compact .role-guide__fae-introduction {
    padding: 20px;
}
.role-guide--compact .role-guide__fae-name {
    font-size: 26px;
}
.role-guide--compact .role-guide__fae-details {
    grid-template-columns: 1fr;
    margin-inline: 20px;
    padding-block: 22px;
}
.role-guide--compact .role-guide__fae-footer {
    padding: 16px 20px;
}
@media (max-width: 700px) {
    .role-guide {
        padding-block: 32px;
    }
    .role-guide__toggle {
        grid-template-columns: 42px minmax(0, 1fr) auto 18px;
        gap: 12px;
        padding: 19px 16px;
    }
    .role-guide__emblem {
        width: 42px;
        height: 42px;
    }
    .role-guide__emblem svg {
        width: 23px;
        height: 23px;
    }
    .role-guide__faction-name {
        font-size: 25px;
    }
    .role-guide__purpose {
        font-size: 11px;
    }
    .role-guide__count {
        font-size: 11px;
    }
    .role-guide__list {
        grid-template-columns: 1fr;
        padding-inline: 18px;
    }
    .role-guide__list .role-guide__entry + .role-guide__entry {
        border-top: 1px solid var(--line);
    }
    .role-guide__entry {
        grid-template-columns: 26px minmax(0, 1fr);
        gap: 12px;
    }
    .role-guide__symbol {
        font-size: 27px;
    }
    .role-guide__expansions {
        margin-top: 34px;
    }
    .role-guide__fae-introduction {
        gap: 16px;
        padding: 24px 20px;
    }
    .role-guide__fae-emblem {
        width: 44px;
        height: 62px;
    }
    .role-guide__fae-emblem svg {
        width: 30px;
    }
    .role-guide .role-guide__fae-name {
        font-size: 27px;
    }
    .role-guide__fae-details {
        grid-template-columns: 1fr;
        gap: 26px;
        margin-inline: 20px;
    }
    .role-guide__bargains {
        padding-top: 22px;
        padding-left: 0;
        border-top: 1px solid var(--line);
        border-left: 0;
    }
    .role-guide__fae-footer {
        padding: 18px 20px;
    }
    .role-guide--compact {
        padding-block: 24px 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .role-guide__toggle,
    .role-guide__chevron {
        transition: none;
    }
}
</style>
