<script setup lang="ts">
import { LockKeyhole } from '@lucide/vue';
import { computed, useId } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import { characterIds, type Character } from '@/lib/chanting';
const props = defineProps<{ characters: Character[]; disabled?: boolean }>();
const selected = defineModel<string>({ default: '' });
const groupName = useId();
const groups = computed(() => [
    {
        id: 'custom',
        characters: props.characters.filter((item) => item.id === 'custom'),
    },
    {
        id: 'original',
        characters: props.characters.filter(
            (item) =>
                item.id !== 'custom' && characterIds.indexOf(item.id) < 16,
        ),
    },
    {
        id: 'earned',
        characters: props.characters.filter(
            (item) => characterIds.indexOf(item.id) >= 16,
        ),
    },
]);
</script>
<template>
    <fieldset class="character-picker" :disabled="disabled">
        <legend>Your face in the village</legend>
        <p>
            All looks, no clues. Your character never reveals your secret role.
        </p>
        <template v-for="group in groups" :key="group.id">
            <h3
                v-if="group.id !== 'original' && group.characters.length"
                class="character-unlock-heading"
            >
                {{
                    group.id === 'custom'
                        ? 'Made in the looking glass'
                        : 'Earned in the village'
                }}
            </h3>
            <div
                class="character-choices"
                :class="{ 'character-choices--earned': group.id === 'earned' }"
            >
                <label
                    v-for="character in group.characters"
                    :key="character.id"
                    :class="{
                        selected: selected === character.id,
                        'is-locked': character.unlocked === false,
                    }"
                >
                    <input
                        :name="groupName"
                        v-model="selected"
                        type="radio"
                        :value="character.id"
                        :aria-label="character.name"
                        :disabled="character.unlocked === false"
                        :aria-describedby="
                            group.id === 'earned'
                                ? `${groupName}-${character.id}-requirement`
                                : undefined
                        "
                    />
                    <CharacterPortrait
                        :character="character.id"
                        :creator="character.creator"
                        decorative
                    />
                    <span class="character-choice-name">{{
                        character.name.replace('The ', '')
                    }}</span>
                    <small
                        v-if="group.id === 'earned'"
                        :id="`${groupName}-${character.id}-requirement`"
                        class="character-requirement"
                    >
                        <LockKeyhole
                            v-if="character.unlocked === false"
                            :size="11"
                            aria-hidden="true"
                        />
                        {{
                            character.unlocked === false
                                ? `Locked · ${character.requirement}`
                                : 'Unlocked'
                        }}
                    </small>
                </label>
            </div>
        </template>
    </fieldset>
</template>
<style scoped>
.character-unlock-heading {
    margin: 18px 0 10px;
    padding-top: 14px;
    border-top: 1px solid #b9cda833;
    color: var(--cream);
    font-size: 13px;
}
.character-choices--earned {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.character-choices--earned label {
    padding: 10px 7px;
    border-color: #b9cda833;
    text-align: center;
    min-width: 0;
}
.character-choices label.is-locked {
    cursor: not-allowed;
}
.character-choices label.is-locked .character-portrait {
    filter: saturate(0.7);
}
.character-choices .character-choice-name {
    font-size: 9px;
    color: var(--cream);
}
.character-requirement {
    color: #c6cabe;
    font-size: 10px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
.character-requirement svg {
    display: inline;
    vertical-align: -1px;
    margin-right: 3px;
}
</style>
