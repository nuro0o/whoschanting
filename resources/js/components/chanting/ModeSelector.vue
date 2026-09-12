<script setup lang="ts">
import { t } from '@/i18n';
import { computed, useId } from 'vue';
import { roles } from '@/lib/chanting';
import {
    cultRoleIds,
    customModeError,
    defaultModeSetup,
    rosterTotals,
    townRoleIds,
    type ModeSetup,
} from '@/lib/gameModes';

const props = withDefaults(
    defineProps<{
        modelValue: ModeSetup;
        disabled?: boolean;
        minPlayers?: number;
        maxPlayers?: number;
    }>(),
    { minPlayers: 3, maxPlayers: 15 },
);
const emit = defineEmits<{ 'update:modelValue': [value: ModeSetup] }>();
const id = useId();
const totals = computed(() => rosterTotals(props.modelValue.roles));
const error = computed(() =>
    customModeError(props.modelValue, props.minPlayers, props.maxPlayers),
);
const choices = [
    {
        id: 'classic',
        title: t('modeSelector.classic.name'),
        detail: t('modeSelector.classic.hint'),
        mark: '01',
    },
    {
        id: 'hard',
        title: t('modeSelector.hard.name'),
        detail: t('modeSelector.hard.hint'),
        mark: '02',
    },
    {
        id: 'chaos',
        title: t('modeSelector.chaos.name'),
        detail: t('modeSelector.chaos.hint'),
        mark: '03',
    },
    {
        id: 'paranoia',
        title: t('modeSelector.paranoia.name'),
        detail: t('modeSelector.paranoia.hint'),
        mark: '04',
    },
    {
        id: 'custom',
        title: t('modeSelector.custom.name'),
        detail: t('modeSelector.custom.hint'),
        mark: '05',
    },
] as const;
const sides = [
    { id: 'town', name: t('modeSelector.town'), ids: townRoleIds },
    { id: 'cult', name: t('modeSelector.cult'), ids: cultRoleIds },
];
function update(change: Partial<ModeSetup>) {
    if (change.mode === 'custom' && !Object.keys(props.modelValue.roles).length)
        change.roles = defaultModeSetup().roles;
    emit('update:modelValue', { ...props.modelValue, ...change });
}
function toggle(role: string, enabled: boolean) {
    const counts = { ...props.modelValue.roles };
    if (enabled) counts[role] = 1;
    else delete counts[role];
    update({ roles: counts });
}
function count(role: string, event: Event) {
    update({
        roles: {
            ...props.modelValue.roles,
            [role]: Number((event.target as HTMLInputElement).value),
        },
    });
}
function invalidCount(role: string): boolean {
    const count = props.modelValue.roles[role];
    return (
        count !== undefined &&
        (!Number.isInteger(count) || count < 1 || count > props.maxPlayers)
    );
}
</script>

<template>
    <fieldset class="mode-selector" :disabled="disabled">
        <legend>{{ t('modeSelector.legend') }}</legend>
        <div class="mode-choices">
            <label
                v-for="choice in choices"
                :key="choice.id"
                class="mode-choice"
                :class="{
                    selected: modelValue.mode === choice.id,
                    chaos: choice.id === 'chaos',
                }"
            >
                <input
                    type="radio"
                    :name="`${id}-mode`"
                    :value="choice.id"
                    :checked="modelValue.mode === choice.id"
                    @change="update({ mode: choice.id })"
                />
                <span class="mode-mark" aria-hidden="true">{{
                    choice.mark
                }}</span>
                <strong>{{ choice.title }}</strong
                ><small>{{ choice.detail }}</small>
            </label>
        </div>
        <div class="mode-detail" aria-live="polite">
            <template v-if="modelValue.mode === 'classic'">
                <p class="mode-kicker">
                    {{ t('modeSelector.classic.eyebrow') }}
                </p>
                <p>{{ t('modeSelector.classic.description') }}</p>
                <label :for="`${id}-classic`">{{
                    t('modeSelector.classic.variant_label')
                }}</label>
                <select
                    :id="`${id}-classic`"
                    :value="modelValue.classic_variant"
                    @change="
                        update({
                            classic_variant: (
                                $event.target as HTMLSelectElement
                            ).value as ModeSetup['classic_variant'],
                        })
                    "
                >
                    <option value="classic">
                        {{ t('modeSelector.classic.original_option') }}
                    </option>
                    <option value="illusions">
                        {{ t('modeSelector.classic.illusions_option') }}
                    </option>
                </select>
                <p class="mode-footnote">
                    {{
                        modelValue.classic_variant === 'illusions'
                            ? t('modeSelector.classic.illusions_description')
                            : t('modeSelector.classic.original_description')
                    }}
                </p>
            </template>
            <template v-else-if="modelValue.mode === 'hard'">
                <p class="mode-kicker">{{ t('modeSelector.hard.eyebrow') }}</p>
                <p>{{ t('modeSelector.hard.description') }}</p>
                <p class="mode-footnote">
                    {{ t('modeSelector.hard.details') }}
                </p>
            </template>
            <template v-else-if="modelValue.mode === 'chaos'">
                <p class="mode-kicker chaos-text">
                    {{ t('modeSelector.chaos.eyebrow') }}
                </p>
                <label :for="`${id}-chaos`">{{
                    t('modeSelector.chaos.variant_label')
                }}</label>
                <select
                    :id="`${id}-chaos`"
                    :value="modelValue.chaos_variant"
                    @change="
                        update({
                            chaos_variant: ($event.target as HTMLSelectElement)
                                .value as ModeSetup['chaos_variant'],
                        })
                    "
                >
                    <option value="wildcards">
                        {{ t('modeSelector.chaos.wildcards_option') }}
                    </option>
                    <option value="maelstrom">
                        {{ t('modeSelector.chaos.maelstrom_option') }}
                    </option>
                </select>
                <p>{{ t('modeSelector.chaos.description') }}</p>
                <p
                    v-if="modelValue.chaos_variant === 'maelstrom'"
                    class="mode-footnote"
                >
                    {{ t('modeSelector.chaos.maelstrom_description') }}
                </p>
                <p v-else class="mode-footnote">
                    {{ t('modeSelector.chaos.wildcards_description') }}
                </p>
            </template>
            <template v-else-if="modelValue.mode === 'paranoia'">
                <p class="mode-kicker">
                    {{ t('modeSelector.paranoia.eyebrow') }}
                </p>
                <p>{{ t('modeSelector.paranoia.description') }}</p>
                <p>{{ t('modeSelector.paranoia.oaths') }}</p>
                <p class="mode-footnote">
                    {{ t('modeSelector.paranoia.timers') }}
                </p>
            </template>
            <template v-else>
                <p class="mode-kicker">
                    {{ t('modeSelector.custom.eyebrow') }}
                </p>
                <p>{{ t('modeSelector.custom.description') }}</p>
                <div class="mode-totals" role="status">
                    <strong>{{
                        t('modeSelector.custom.seats', {
                            total: totals.total,
                            maxPlayers: maxPlayers,
                        })
                    }}</strong
                    ><span>{{
                        t('modeSelector.custom.town_count', {
                            town: totals.town,
                        })
                    }}</span
                    ><span>{{
                        t('modeSelector.custom.cult_count', {
                            cult: totals.cult,
                        })
                    }}</span>
                </div>
                <section
                    v-for="side in sides"
                    :key="side.id"
                    class="role-ledger"
                    :aria-label="
                        t('modeSelector.custom.side_label', { side: side.name })
                    "
                >
                    <div class="role-ledger-heading">
                        {{ side.name }}
                        <span>{{
                            side.id === 'town'
                                ? t('modeSelector.custom.town_purpose')
                                : t('modeSelector.custom.cult_purpose')
                        }}</span>
                    </div>
                    <div
                        v-for="role in side.ids"
                        :key="role"
                        class="role-ledger-row"
                        :class="{
                            enabled: modelValue.roles[role] !== undefined,
                        }"
                    >
                        <label :for="`${id}-${role}`"
                            ><input
                                :id="`${id}-${role}`"
                                type="checkbox"
                                :checked="modelValue.roles[role] !== undefined"
                                @change="
                                    toggle(
                                        role,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            /><span
                                ><strong>{{
                                    roles[role]?.name.replace(/^The /, '')
                                }}</strong
                                ><small>{{
                                    roles[role]?.subtitle.split(' · ')[1]
                                }}</small></span
                            ></label
                        >
                        <input
                            v-if="modelValue.roles[role] !== undefined"
                            type="number"
                            :aria-label="
                                t('modeSelector.custom.count_label', {
                                    role: roles[role]?.name,
                                })
                            "
                            :aria-invalid="invalidCount(role)"
                            :aria-describedby="
                                invalidCount(role)
                                    ? `${id}-${role}-count-error`
                                    : undefined
                            "
                            :min="1"
                            :max="maxPlayers"
                            step="1"
                            :value="modelValue.roles[role]"
                            @input="count(role, $event)"
                        />
                        <span v-else class="role-not-included">—</span>
                        <p
                            v-if="invalidCount(role)"
                            :id="`${id}-${role}-count-error`"
                            class="role-count-error"
                        >
                            {{
                                t('modeSelector.custom.invalid_count', {
                                    maxPlayers: maxPlayers,
                                    value: roles[role]?.name.replace(
                                        /^The /,
                                        '',
                                    ),
                                })
                            }}
                        </p>
                    </div>
                </section>
                <p v-if="error" class="form-error" role="status">{{ error }}</p>
                <p v-else class="mode-footnote">
                    {{
                        t('modeSelector.custom.ready', { total: totals.total })
                    }}
                </p>
            </template>
        </div>
    </fieldset>
</template>

<style scoped>
.mode-selector {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}
.mode-selector legend {
    margin-bottom: 14px;
    color: var(--cream);
    font-family: 'Fraunces', Georgia, serif;
    font-size: 21px;
}
.mode-choices {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.mode-choice {
    position: relative;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 5px 8px;
    padding: 13px;
    border: 1px solid var(--line);
    background: rgb(0 0 0 / 12%);
    cursor: pointer;
    transition:
        border-color 0.15s,
        background 0.15s;
}
.mode-choice input {
    grid-column: 2;
    grid-row: 1;
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--green);
}
.mode-choice strong {
    grid-column: 1;
    font-family: 'Fraunces', Georgia, serif;
    color: var(--cream);
    font-size: 20px;
    font-weight: 400;
}
.mode-choice small {
    grid-column: 1 / -1;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}
.mode-mark {
    grid-column: 1;
    grid-row: 1;
    color: var(--muted);
    font-size: 9px;
    letter-spacing: 1px;
}
.mode-choice.selected {
    border-color: var(--green);
    background: rgb(143 172 142 / 12%);
}
.mode-choice.chaos.selected {
    border-color: var(--coral);
    background: rgb(183 98 74 / 12%);
}
.mode-choice:hover {
    border-color: var(--cream);
}
.mode-choice:focus-within {
    outline: 2px solid var(--cream);
    outline-offset: 2px;
}
.mode-detail {
    padding: 17px 0 4px;
    font-size: 12px;
    line-height: 1.7;
    color: var(--muted);
}
.mode-detail p {
    margin: 0 0 12px;
}
.mode-detail .mode-kicker {
    color: var(--green);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.mode-detail .chaos-text {
    color: var(--coral);
}
.mode-detail label {
    color: var(--cream);
    font-size: 12px;
}
.mode-detail select {
    width: 100%;
    min-height: 42px;
    padding: 8px 10px;
    margin: 6px 0 12px;
    border: 1px solid var(--line);
    border-radius: 4px;
    background: #152c2d;
    color: var(--cream);
    font: inherit;
}
.mode-detail .mode-footnote {
    font-size: 11px;
}
.mode-totals {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    padding: 12px 0;
    border-block: 1px solid var(--line);
    color: var(--cream);
}
.mode-totals strong {
    margin-right: auto;
}
.role-ledger {
    margin-top: 18px;
}
.role-ledger-heading {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin: 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--line);
    color: var(--green);
    font-size: 12px;
    font-weight: 700;
}
.role-ledger-heading span {
    color: var(--muted);
    font-size: 10px;
    font-weight: 400;
}
.role-ledger-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 56px;
    padding: 8px 0;
    border-bottom: 1px solid var(--line);
}
.mode-detail .role-count-error {
    flex-basis: 100%;
    margin: 0;
    color: var(--coral);
    font-size: 11px;
}
.role-ledger-row label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}
.role-ledger-row input[type='checkbox'] {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--green);
}
.role-ledger-row label strong,
.role-ledger-row label small {
    display: block;
}
.role-ledger-row label strong {
    font-size: 12px;
    font-weight: 500;
}
.role-ledger-row label small {
    color: var(--muted);
    font-size: 10px;
    line-height: 1.4;
}
.role-ledger-row input[type='number'] {
    width: 60px;
    min-height: 38px;
    flex-shrink: 0;
    padding: 6px;
    border: 1px solid var(--green);
    border-radius: 3px;
    background: #152c2d;
    color: var(--cream);
    font: inherit;
    text-align: center;
}
.role-not-included {
    width: 60px;
    text-align: center;
    color: var(--muted);
}
.mode-selector:disabled {
    opacity: 0.65;
}
.mode-detail input:focus-visible,
.mode-detail select:focus-visible {
    outline: 2px solid var(--cream);
    outline-offset: 2px;
}
@media (prefers-reduced-motion: reduce) {
    .mode-choice {
        transition: none;
    }
}
</style>
