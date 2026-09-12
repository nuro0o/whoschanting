<script setup lang="ts">
import { LockKeyhole } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import CharacterPortrait from './CharacterPortrait.vue';
import SealedCharacter from './SealedCharacter.vue';
import { characterIds, type Character } from '@/lib/chanting';
import { characterCollections } from '@/lib/characterCollections';
const props = defineProps<{ characters: Character[]; disabled?: boolean }>();
const selected = defineModel<string>({ default: '' });
const groupName = useId();
const collection = ref('all');
const groups = computed(() =>
    characterCollections(props.characters, characterIds),
);
const categories = computed(() =>
    groups.value.filter(
        (group) =>
            ['classics', 'levelup'].includes(group.id) ||
            group.id.startsWith('season:'),
    ),
);
const visibleGroups = computed(() =>
    groups.value.filter(
        (group) =>
            group.characters.length &&
            (collection.value === 'all' || group.id === collection.value),
    ),
);
</script>
<template>
    <fieldset class="character-picker" :disabled="disabled">
        <legend>Your face in the village</legend>
        <p>
            All looks, no clues. Your character never reveals your secret role.
        </p>
        <div class="character-collection-filter">
            <label :for="`${groupName}-collection`">Collection</label>
            <select :id="`${groupName}-collection`" v-model="collection">
                <option value="all">All</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>
        </div>
        <p v-if="!visibleGroups.length" role="status">
            No characters in this collection yet.
        </p>
        <template v-for="group in visibleGroups" :key="group.id">
            <h3 v-if="collection === 'all'" class="character-unlock-heading">
                {{ group.name }}
            </h3>
            <div
                class="character-choices"
                :class="{
                    'character-choices--earned': group.earned,
                }"
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
                        :aria-label="
                            character.hidden
                                ? `? · ${character.role_name}`
                                : character.name
                        "
                        :disabled="character.unlocked === false"
                        :aria-describedby="
                            group.earned
                                ? `${groupName}-${character.id}-requirement`
                                : undefined
                        "
                    />
                    <SealedCharacter
                        v-if="character.hidden"
                        class="character-portrait"
                    />
                    <CharacterPortrait
                        v-else
                        :character="character.id"
                        :creator="character.creator"
                        decorative
                    />
                    <span class="character-choice-name">{{
                        character.name.replace('The ', '')
                    }}</span>
                    <small
                        v-if="group.earned"
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
.character-collection-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
    margin: 16px 0;
}
.character-collection-filter label {
    color: var(--cream);
    font-size: 12px;
}
.character-collection-filter select {
    flex: 1;
    min-width: 0;
    min-height: 42px;
    padding: 8px 12px;
    border: 1px solid var(--line);
    border-radius: 4px;
    background: #152b26;
    color: var(--cream);
    font: inherit;
    font-size: 12px;
    color-scheme: dark;
}
.character-collection-filter select:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 3px;
}
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
