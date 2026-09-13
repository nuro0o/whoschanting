<script setup lang="ts">
import { computed, useId } from 'vue';
import type { RoomState } from '@/lib/chanting';
import {
    expansionTargets,
    isExpansionRole,
    relicNames,
} from '@/lib/expansions';
const props = defineProps<{ state: RoomState; disabled: boolean }>();
const action = defineModel<string>('action', { required: true });
const target = defineModel<string | null>('target', { required: true });
const secondary = defineModel<string | null>('secondary', { required: true });
const relic = defineModel<string | null>('relic', { required: true });
const id = useId();
const choice = computed(() =>
    props.state.expansion?.night_choices.find(
        (item) => item.id === action.value,
    ),
);
const targets = computed(() =>
    choice.value ? expansionTargets(props.state, choice.value) : [],
);
const destinations = computed(() =>
    props.state.players.filter(
        (p) =>
            p.alive &&
            p.id !== target.value &&
            p.id !== props.state.me.id &&
            !props.state.me.allies.some((a) => a.id === p.id) &&
            !props.state.expansion?.marks?.includes(p.id),
    ),
);
const relics = computed(() =>
    choice.value?.relic === 'owned'
        ? (props.state.expansion?.inventory ?? [])
        : Object.keys(relicNames),
);
</script>
<template>
    <fieldset class="expansion-actions" :disabled="disabled">
        <legend>{{ state.expansion?.name }} · night choice</legend>
        <label :for="`${id}-action`">What will you do?</label>
        <select :id="`${id}-action`" v-model="action">
            <option value="">
                {{
                    isExpansionRole(state.me.role)
                        ? 'Keep watch tonight'
                        : 'Use my usual role action'
                }}
            </option>
            <option
                v-for="item in state.expansion?.night_choices"
                :key="item.id"
                :value="item.id"
            >
                {{ item.label }}
            </option>
        </select>
        <template v-if="choice">
            <p class="expansion-action-description">{{ choice.description }}</p>
            <p class="expansion-action-cost">
                This replaces your usual night ability or chant.
            </p>
            <template v-if="choice.target !== 'none'">
                <p v-if="!targets.length" class="expansion-action-cost">
                    No eligible player is available for this action. Choose
                    another action or keep watch.
                </p>
                <label :for="`${id}-target`">{{
                    choice.secondary ? 'Move the mark from' : 'Choose a player'
                }}</label>
                <select :id="`${id}-target`" v-model="target">
                    <option :value="null" disabled>
                        Select a living player
                    </option>
                    <option
                        v-for="player in targets"
                        :key="player.id"
                        :value="player.id"
                    >
                        {{ player.name
                        }}{{ player.id === state.me.id ? ' (you)' : '' }}
                    </option>
                </select>
            </template>
            <template v-if="choice.secondary">
                <label :for="`${id}-destination`">Move the mark to</label>
                <select :id="`${id}-destination`" v-model="secondary">
                    <option :value="null" disabled>
                        Select an unmarked player
                    </option>
                    <option
                        v-for="player in destinations"
                        :key="player.id"
                        :value="player.id"
                    >
                        {{ player.name }}
                    </option>
                </select>
            </template>
            <template v-if="choice.relic">
                <label :for="`${id}-relic`">Choose a relic</label>
                <select :id="`${id}-relic`" v-model="relic">
                    <option :value="null" disabled>Select a relic</option>
                    <option v-for="item in relics" :key="item" :value="item">
                        {{ relicNames[item] ?? item }}
                    </option>
                </select>
            </template>
        </template>
    </fieldset>
</template>
<style scoped>
.expansion-actions {
    border: 0;
    border-bottom: 1px solid var(--line);
    margin: 0 0 20px;
    padding: 0 0 18px;
    min-width: 0;
}
legend {
    font-family: 'Fraunces', Georgia, serif;
    color: var(--cream);
    font-size: 21px;
    margin-bottom: 14px;
}
label {
    display: block;
    color: var(--cream);
    font-size: 12px;
    margin-top: 12px;
}
select {
    width: 100%;
    min-height: 44px;
    margin-top: 6px;
    padding: 10px;
    border: 1px solid var(--line);
    border-radius: 4px;
    background: #152c2d;
    color: var(--cream);
    font: inherit;
}
select:focus-visible {
    outline: 2px solid var(--cream);
    outline-offset: 3px;
}
p {
    line-height: 1.7;
    font-size: 13px;
}
.expansion-action-cost {
    color: var(--green);
    font-size: 12px;
}
fieldset:disabled {
    opacity: 0.65;
}
</style>
