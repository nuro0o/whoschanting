<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { progressPercent, type ProgressionData } from '@/lib/progression';
defineProps<{ progression: ProgressionData }>();
</script>
<template>
    <section class="progression-summary" aria-label="Your progression">
        <div>
            <p class="account-kicker">Your growing reputation</p>
            <h2>
                Level {{ progression.profile.level }}
                <span>· {{ progression.season.tier.name }}</span>
            </h2>
        </div>
        <div class="summary-meter">
            <span
                >{{ progression.profile.level_xp }} /
                {{ progression.profile.next_level_xp }} XP to level
                {{ progression.profile.level + 1 }}</span
            ><progress
                :value="
                    progressPercent(
                        progression.profile.level_xp,
                        progression.profile.next_level_xp,
                    )
                "
                max="100"
                aria-label="Progress to next level"
            ></progress>
        </div>
        <Link href="/progression" class="account-text-link"
            >Progression & wardrobe <ArrowUpRight :size="16"
        /></Link>
    </section>
</template>
<style scoped>
.progression-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px 28px;
    border-block: 1px solid var(--account-line);
    padding: 22px 0;
    margin-top: 28px;
}
h2 {
    font-size: 23px;
    margin-top: 4px;
}
h2 span {
    font-family: 'DM Sans', sans-serif;
    font-size: 12px;
    color: var(--account-muted);
}
.summary-meter {
    flex: 1;
    min-width: 180px;
    max-width: 300px;
    font-size: 11px;
    color: var(--account-muted);
}
progress {
    display: block;
    width: 100%;
    height: 5px;
    margin-top: 10px;
    accent-color: var(--account-green);
}
@media (max-width: 600px) {
    .summary-meter {
        max-width: none;
        flex-basis: 100%;
    }
}
</style>
