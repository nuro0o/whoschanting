<script setup lang="ts">
import { computed } from 'vue';
import { LockKeyhole } from '@lucide/vue';
import type { RoomState } from '@/lib/chanting';
import { factionStyle, relicNames } from '@/lib/expansions';
const props = defineProps<{ state: RoomState; revealed: boolean }>();
const expansion = computed(
    () =>
        props.state.expansion ??
        props.state.expansion_catalog?.find(
            (item) => item.id === props.state.mode_setup?.expansion,
        ),
);
const progress = computed(() => props.state.expansion);
const name = (id: string) =>
    props.state.players.find((p) => p.id === id)?.name ?? 'A former villager';
const unit = computed(
    () =>
        ({
            drowned: 'living marks',
            'gilded-hand': 'relics held',
            'hollow-choir': 'echoes',
            carnival: 'different acts',
        })[expansion.value?.id ?? ''],
);
</script>
<template>
    <section
        v-if="expansion"
        class="game-panel expansion-panel"
        :style="{ '--faction-ink': factionStyle[expansion.id]?.color }"
        :aria-label="expansion.name"
    >
        <header>
            <span class="expansion-sigil" aria-hidden="true">{{
                factionStyle[expansion.id]?.symbol
            }}</span>
            <div>
                <p class="eyebrow">ROOM EXPANSION</p>
                <h2>{{ expansion.name }}</h2>
            </div>
            <p v-if="progress" class="expansion-score">
                <strong>{{ progress.progress }} / {{ expansion.goal }}</strong
                ><span>{{ unit }}</span>
            </p>
        </header>
        <p>{{ expansion.instructions }}</p>
        <p v-if="state.phase === 'lobby'" class="expansion-note">
            One seated owner unlocks this expansion for everyone. Roles are
            dealt normally to any player. {{ expansion.min_players }}–{{
                state.rules.max_players
            }}
            players; Classic or Classic Illusions.
        </p>
        <p v-if="progress?.tide_day" class="expansion-note">
            Next tide: after the vote on day {{ progress.tide_day }}. Marks
            never change a role or eliminate their holder.
        </p>
        <p v-if="progress?.secured" class="expansion-note">
            Objective secured. The Town and Cult match continues.
        </p>
        <div
            v-if="
                revealed &&
                progress &&
                (progress.marks ||
                    progress.inventory?.length ||
                    progress.court_inventory ||
                    progress.acts ||
                    progress.plan)
            "
            class="expansion-private"
        >
            <p class="eyebrow"><LockKeyhole :size="12" /> YOUR EYES ONLY</p>
            <p v-if="progress.marks">
                Marked villagers:
                {{
                    progress.marks.length
                        ? progress.marks.map(name).join(', ')
                        : 'none yet'
                }}.
            </p>
            <p v-if="progress.inventory?.length">
                In your hands:
                {{
                    progress.inventory
                        .map((item) => relicNames[item] ?? item)
                        .join(', ')
                }}. You may give a relic instead of your usual night action.
            </p>
            <ul v-if="progress.court_inventory?.length">
                <li
                    v-for="item in progress.court_inventory"
                    :key="item.relic_id"
                >
                    {{ relicNames[item.relic_id] ?? item.relic_id }} ·
                    {{ name(item.holder_id) }}
                </li>
            </ul>
            <p v-if="progress.acts">
                Completed acts:
                {{
                    progress.acts.length
                        ? progress.acts.join(', ')
                        : 'none yet'
                }}.
            </p>
            <p v-if="progress.plan">
                Your sealed act today: {{ progress.plan.action
                }}{{
                    progress.plan.target_id
                        ? ` · ${name(progress.plan.target_id)}`
                        : ''
                }}. Keep this objective secret.
            </p>
        </div>
    </section>
</template>
<style scoped>
.expansion-panel {
    border-top: 2px solid var(--faction-ink, var(--green));
}
header {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
h2 {
    margin: 3px 0 0;
    font-size: clamp(21px, 4vw, 27px);
}
.eyebrow {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    color: var(--faction-ink, var(--green));
}
.expansion-sigil {
    font-size: 38px;
    color: var(--faction-ink, var(--green));
}
.expansion-score {
    margin: 0 0 0 auto;
    text-align: right;
}
.expansion-score strong,
.expansion-score span {
    display: block;
}
.expansion-score strong {
    color: var(--faction-ink, var(--green));
    font:
        26px 'Fraunces',
        Georgia,
        serif;
}
.expansion-score span,
.expansion-note {
    font-size: 12px;
    color: var(--muted);
}
p,
li {
    line-height: 1.7;
}
.expansion-private {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
}
.expansion-private p:last-child {
    margin-bottom: 0;
}
</style>
