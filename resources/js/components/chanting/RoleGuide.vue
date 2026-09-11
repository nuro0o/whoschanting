<script setup lang="ts">
import { useId } from 'vue';
import { roles } from '@/lib/chanting';

withDefaults(
    defineProps<{
        compact?: boolean;
        minimumPlayers?: Record<string, number>;
    }>(),
    { compact: false },
);

const headingId = useId();
const allegiances = [
    {
        id: 'town',
        name: 'Town',
        purpose: 'Uncover the cult.',
        roles: [
            'townsperson',
            'oracle',
            'warden',
            'lamplighter',
            'medium',
            'bellkeeper',
        ],
    },
    {
        id: 'cult',
        name: 'Cult',
        purpose: 'Keep the ritual alive.',
        roles: ['veilweaver', 'acolyte', 'dreamweaver'],
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
            <p class="eyebrow">SECRET ROLES. VERY DIFFERENT INTENTIONS.</p>
            <component
                :is="compact ? 'h3' : 'h2'"
                :id="headingId"
                class="role-guide__title"
            >
                {{ compact ? 'Know the roles.' : 'A face is just a face.' }}
                <em v-if="!compact">A role is a secret.</em>
            </component>
            <p class="role-guide__intro">
                Your character is cosmetic. Your secret role decides your side
                and abilities. These are the possible roles, not a reveal of who
                has them. The mix depends on the number of players.
            </p>
        </header>
        <div class="role-guide__sides">
            <section
                v-for="side in allegiances"
                :key="side.id"
                class="role-guide__side"
                :class="`role-guide__side--${side.id}`"
                :aria-labelledby="`${headingId}-${side.id}`"
            >
                <div class="role-guide__allegiance">
                    <component
                        :is="compact ? 'h4' : 'h3'"
                        :id="`${headingId}-${side.id}`"
                    >
                        {{ side.name }}
                    </component>
                    <p>{{ side.purpose }}</p>
                </div>
                <ul class="role-guide__list">
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
                            >
                                {{ roles[roleId].name }}
                            </component>
                            <p class="role-guide__subtitle">
                                {{ roles[roleId].subtitle }}
                            </p>
                            <p
                                v-if="minimumPlayers?.[roleId]"
                                class="role-guide__subtitle"
                            >
                                Available with {{ minimumPlayers[roleId] }}+
                                players
                            </p>
                            <p class="role-guide__description">
                                {{ roles[roleId].description }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</template>

<style scoped>
.role-guide {
    padding-block: 48px;
    border-top: 1px solid var(--line);
    scroll-margin-top: 25px;
}

.role-guide__header {
    max-width: 670px;
    margin-bottom: 32px;
}

.role-guide__title {
    margin-block: 12px 16px;
    font-size: clamp(28px, 3.5vw, 40px);
    line-height: 1.2;
    letter-spacing: -0.6px;
}

.role-guide__title em {
    display: block;
}

.role-guide__intro {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.75;
}

.role-guide__sides {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 40px;
}

.role-guide__side {
    --role-accent: var(--green);
    min-width: 0;
    border-top: 2px solid var(--role-accent);
}

.role-guide__side--cult {
    --role-accent: var(--coral);
}

.role-guide__allegiance {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 14px;
    padding-top: 16px;
}

.role-guide__allegiance h3,
.role-guide__allegiance h4 {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    color: var(--role-accent);
}

.role-guide__allegiance p {
    color: var(--muted);
    font-size: 12px;
}

.role-guide__list {
    padding: 0;
    margin: 0;
    list-style: none;
}

.role-guide__entry {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 14px;
    padding-block: 24px;
}

.role-guide__entry + .role-guide__entry {
    border-top: 1px solid var(--line);
}

.role-guide__symbol {
    color: var(--role-accent);
    font-family: Georgia, serif;
    font-size: 32px;
    line-height: 1;
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

.role-guide__subtitle {
    margin-top: 6px;
    color: var(--role-accent);
    font-size: 11px;
}

.role-guide__description {
    margin-top: 12px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.75;
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
    grid-template-columns: 1fr;
    gap: 8px;
}

.role-guide--compact .role-guide__name {
    font-size: 22px;
}

.role-guide--compact .eyebrow {
    font-size: 9px;
    letter-spacing: 1.2px;
}

@media (max-width: 700px) {
    .role-guide {
        padding-block: 32px;
    }

    .role-guide__sides {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .role-guide__entry {
        grid-template-columns: 26px minmax(0, 1fr);
        gap: 12px;
    }

    .role-guide__symbol {
        font-size: 27px;
    }

    .role-guide--compact {
        padding-block: 24px 0;
    }
}
</style>
