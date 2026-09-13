<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Crown, Shirt, ShoppingBag, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import type { ProgressionData } from '@/lib/progression';
const props = defineProps<{ progression: ProgressionData | null }>();
const unlockedLooks = computed(() =>
    props.progression
        ? Object.values(props.progression.cosmetics)
              .flat()
              .filter((item) => item.unlocked).length
        : null,
);
</script>

<template>
    <nav
        class="resident-destinations"
        aria-label="Store, wardrobe and progression"
    >
        <Link
            href="/settings/profile#store"
            preserve-state
            class="resident-destination destination-store"
        >
            <ShoppingBag
                class="destination-icon"
                :size="24"
                :stroke-width="1.4"
                aria-hidden="true"
            />
            <div class="destination-copy">
                <h2>Store</h2>
                <p>Find your next village keepsake.</p>
            </div>
            <span class="destination-detail"
                ><template v-if="progression"
                    ><Crown :size="13" aria-hidden="true" />{{
                        progression.store.balance.toLocaleString()
                    }}
                    Crowns</template
                ><template v-else>Keepsakes &amp; collections</template></span
            >
            <span class="destination-action"
                >Browse store <ArrowUpRight :size="16" aria-hidden="true"
            /></span>
        </Link>
        <Link
            href="/progression?tab=wardrobe#progression-sections"
            preserve-state
            class="resident-destination destination-wardrobe"
        >
            <Shirt
                class="destination-icon"
                :size="24"
                :stroke-width="1.4"
                aria-hidden="true"
            />
            <div class="destination-copy">
                <h2>Wardrobe</h2>
                <p>Choose your character &amp; look.</p>
            </div>
            <span class="destination-detail"
                ><template v-if="unlockedLooks !== null"
                    >{{ unlockedLooks }} cosmetics unlocked</template
                ><template v-else>Your personal collection</template></span
            >
            <span class="destination-action"
                >Change your look <ArrowUpRight :size="16" aria-hidden="true"
            /></span>
        </Link>
        <Link
            href="/progression?tab=achievements"
            preserve-state
            class="resident-destination destination-progression"
        >
            <Trophy
                class="destination-icon"
                :size="24"
                :stroke-width="1.4"
                aria-hidden="true"
            />
            <div class="destination-copy">
                <h2>Progression</h2>
                <p>Follow your levels &amp; achievements.</p>
            </div>
            <span class="destination-detail"
                ><template v-if="progression"
                    >Level {{ progression.profile.level }}
                    <span aria-hidden="true">&middot;</span>
                    {{ progression.season.tier.name }}</template
                ><template v-else
                    >Your reputation in the village</template
                ></span
            >
            <span class="destination-action"
                >View progress <ArrowUpRight :size="16" aria-hidden="true"
            /></span>
        </Link>
    </nav>
</template>

<style scoped>
.resident-destinations {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin: 24px 0 28px;
    border: 1px solid var(--account-line);
    background: var(--account-panel);
}
.resident-destination {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    align-content: start;
    gap: 12px 14px;
    padding: 23px 24px 18px;
    border-top: 3px solid var(--account-brass);
    min-width: 0;
    transition: background 150ms ease;
}
.resident-destination + .resident-destination {
    border-left: 1px solid var(--account-line);
}
.destination-wardrobe {
    border-top-color: var(--account-green);
}
.destination-progression {
    border-top-color: var(--account-muted);
}
.destination-icon {
    margin-top: 5px;
    color: var(--account-brass);
}
.destination-wardrobe .destination-icon {
    color: var(--account-green);
}
.destination-progression .destination-icon {
    color: var(--account-muted);
}
.destination-copy h2 {
    font-size: 28px;
    line-height: 1.2;
}
.destination-copy p {
    color: var(--account-muted);
    font-size: 12px;
    margin-top: 7px;
    line-height: 1.5;
}
.destination-detail {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
    grid-column: 2;
    color: var(--account-muted);
    font-size: 11px;
}
.destination-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    grid-column: 1 / -1;
    border-top: 1px solid var(--account-line);
    padding-top: 12px;
    margin-top: 3px;
    color: var(--account-green);
    font-size: 12px;
    font-weight: 600;
}
.resident-destination:hover {
    background: var(--account-deep);
}
.resident-destination:hover .destination-action {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.resident-destination:focus-visible {
    position: relative;
    z-index: 1;
    outline-offset: -3px;
}
.resident-destination:active {
    background: color-mix(
        in srgb,
        var(--account-green) 12%,
        var(--account-panel)
    );
}
@media (max-width: 1000px) and (min-width: 701px) {
    .resident-destination {
        padding: 18px 15px 15px;
        gap: 10px;
    }
    .destination-copy h2 {
        font-size: 24px;
    }
    .destination-copy p {
        min-height: 36px;
    }
}
@media (max-width: 700px) {
    .resident-destinations {
        grid-template-columns: minmax(0, 1fr);
        margin: 20px 0 24px;
    }
    .resident-destination {
        grid-template-columns: 25px minmax(0, 1fr) 16px;
        gap: 3px 12px;
        padding: 14px 16px;
        border-top: 0;
        border-left: 3px solid var(--account-brass);
    }
    .resident-destination + .resident-destination {
        border-top: 1px solid var(--account-line);
        border-left: 3px solid var(--account-green);
    }
    .resident-destination.destination-progression {
        border-left-color: var(--account-muted);
    }
    .destination-icon {
        width: 22px;
        grid-row: 1 / 3;
        margin-top: 3px;
    }
    .destination-copy h2 {
        font-size: 23px;
    }
    .destination-copy p {
        font-size: 11px;
        margin-top: 3px;
    }
    .destination-detail {
        font-size: 10px;
    }
    .destination-action {
        grid-column: 3;
        grid-row: 1 / 3;
        border: 0;
        padding: 0;
        margin: 0;
        font-size: 0;
    }
    .destination-action svg {
        flex-shrink: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .resident-destination {
        transition: none;
    }
}
</style>
