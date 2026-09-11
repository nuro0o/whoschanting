<script setup lang="ts">
import { computed, ref } from 'vue';
import type { RoomState } from '@/lib/chanting';
const props = defineProps<{
    state: RoomState;
    revealed: boolean;
    disabled: boolean;
}>();
const emit = defineEmits<{
    reveal: [];
    act: [type: string, extra: { target: string }];
}>();
const target = ref('');
const oath = computed(
    () => props.state.players.find((p) => p.id === props.state.me.id)?.oath,
);
const available = computed(() =>
    props.state.me.role === 'exorcist'
        ? !props.state.me.ability_used
        : !oath.value,
);
</script>

<template>
    <div
        v-if="
            state.me.alive &&
            (!revealed ||
                ['exorcist', 'oathkeeper'].includes(state.me.role ?? ''))
        "
        class="discussion-ability"
    >
        <button v-if="!revealed" class="button" @click="emit('reveal')">
            Reveal my role &amp; day ability
        </button>
        <template v-else>
            <h3>
                {{
                    state.me.role === 'exorcist'
                        ? 'Cleanse a villager'
                        : 'Make a public oath'
                }}
            </h3>
            <p v-if="!available" role="status">
                {{
                    state.me.role === 'exorcist'
                        ? 'Your exorcism is spent.'
                        : 'Your oath is public and cannot be changed.'
                }}
            </p>
            <template v-else>
                <p>
                    {{
                        state.me.role === 'exorcist'
                            ? 'Once per match, clear another player’s active curse and haunting. This spends your ability even if neither is present.'
                            : 'Promise to vote for this player today. A matching actual ballot grants protection from new curses next night. Everyone sees your promise.'
                    }}
                </p>
                <label
                    >Choose a villager
                    <select v-model="target" :disabled="disabled">
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
                    :disabled="disabled || !target"
                    @click="
                        emit(
                            'act',
                            state.me.role === 'exorcist' ? 'exorcise' : 'oath',
                            { target },
                        )
                    "
                >
                    {{
                        state.me.role === 'exorcist'
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
    margin-block: 20px;
    padding-block: 18px;
    border-block: 1px solid var(--line);
}
h3 {
    margin-bottom: 8px;
}
label {
    display: grid;
    gap: 8px;
    margin-block: 12px;
}
select {
    width: 100%;
    padding: 10px;
    background: var(--panel, #152226);
    color: var(--text, #eee);
    border: 1px solid var(--line);
    border-radius: 6px;
}
</style>
