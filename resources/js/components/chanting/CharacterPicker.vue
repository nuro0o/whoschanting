<script setup lang="ts">
import { useId } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import type { Character } from '@/lib/chanting';
defineProps<{ characters: Character[]; disabled?: boolean }>();
const selected = defineModel<string>({ default: '' });
const groupName = useId();
</script>
<template>
    <fieldset class="character-picker" :disabled="disabled">
        <legend>Your face in the village</legend>
        <p>
            All looks, no clues. Your character never reveals your secret role.
        </p>
        <div class="character-choices">
            <label
                v-for="character in characters"
                :key="character.id"
                :class="{ selected: selected === character.id }"
            >
                <input
                    :name="groupName"
                    v-model="selected"
                    type="radio"
                    :value="character.id"
                    :aria-label="character.name"
                />
                <CharacterPortrait :character="character.id" decorative />
                <span>{{ character.name.replace('The ', '') }}</span>
            </label>
        </div>
    </fieldset>
</template>
