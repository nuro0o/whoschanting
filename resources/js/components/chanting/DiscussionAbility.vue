<script setup lang="ts">
import { computed, reactive } from 'vue';
import type { RoomState } from '@/lib/chanting';
import { discussionActionTypes } from '@/lib/roleActions';
const props = defineProps<{
    state: RoomState;
    revealed: boolean;
    disabled: boolean;
}>();
const emit = defineEmits<{
    reveal: [];
    act: [type: string, extra: { target: string }];
}>();
const targets = reactive({ exorcise: '', oath: '' });
const actions = computed(() =>
    discussionActionTypes(props.state, props.revealed),
);
const oath = computed(
    () => props.state.players.find((p) => p.id === props.state.me.id)?.oath,
);
function available(type: 'exorcise' | 'oath') {
    return type === 'exorcise' ? !props.state.me.ability_used : !oath.value;
}
</script>

<template>
    <div v-for="type in actions" :key="type" class="discussion-ability">
        <template v-if="actions.length">
            <h3>
                {{
                    type === 'exorcise'
                        ? 'Cleanse a villager'
                        : 'Make a public oath'
                }}
            </h3>
            <p v-if="!available(type)" role="status">
                {{
                    type === 'exorcise'
                        ? 'Your exorcism is spent.'
                        : 'Your oath is public and cannot be changed.'
                }}
            </p>
            <template v-else>
                <p>
                    {{
                        type === 'exorcise'
                            ? 'Once per match, clear another player’s active curse and haunting. This spends your ability even if neither is present.'
                            : state.mode_setup?.mode === 'paranoia'
                              ? 'Promise to vote for this player today. Everyone can make an oath, so it proves no role. Only an Oathkeeper whose actual ballot matches earns protection from new curses next night.'
                              : 'Promise to vote for this player today. A matching actual ballot grants protection from new curses next night. Everyone sees your promise.'
                    }}
                </p>
                <label
                    >Choose a villager
                    <select v-model="targets[type]" :disabled="disabled">
                        <option disabled value="">Select a player</option>
                        <option
                            v-for="player in state.players.filter(
                                (p) => p.alive && p.id !== state.me.id,
                            )"
                            :key="player.id"
                            :value="player.id"
                        >
                            {{ player.name }}
                        </option>
                    </select>
                </label>
                <button
                    class="button"
                    :disabled="disabled || !targets[type]"
                    @click="emit('act', type, { target: targets[type] })"
                >
                    {{
                        type === 'exorcise'
                            ? 'Use my exorcism'
                            : 'Publish my oath'
                    }}
                </button>
            </template>
        </template>
    </div>
</template>

<style scoped>
.discussion-ability {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--line);
}
h3 {
    width: 100%;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
}
p {
    width: 100%;
    color: #b9c5b8;
    font-size: 12px;
    line-height: 1.5;
}
label {
    display: grid;
    flex: 1 1 220px;
    gap: 8px;
    font-size: 12px;
}
select {
    width: 100%;
    min-height: 46px;
    padding: 10px;
    background: var(--panel, #152226);
    color: var(--text, #eee);
    border: 1px solid var(--line);
    border-radius: 6px;
}
</style>
