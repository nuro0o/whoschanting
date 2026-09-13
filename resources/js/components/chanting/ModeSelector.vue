<script setup lang="ts">
import { t } from '@/i18n';
import { computed, ref, useId } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Check, Circle, LockKeyhole } from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { roles } from '@/lib/chanting';
import { factionStyle, type ExpansionAvailability } from '@/lib/expansions';
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
        faeAvailable?: boolean;
        expansionCatalog?: ExpansionAvailability[];
        faeActive?: boolean;
        faeMinPlayers?: number;
        minPlayers?: number;
        maxPlayers?: number;
    }>(),
    { minPlayers: 3, maxPlayers: 15, faeMinPlayers: 7, faeActive: undefined },
);
const page = usePage();
const expansionCatalog = computed(() =>
    (
        props.expansionCatalog ??
        (page.props.factionCatalog ?? [])
            .filter((item) => item.id !== 'fae-court')
            .map((item) => ({ ...item, active: true, available: false }))
    ).filter((item) => item.active || item.id === props.modelValue.expansion),
);
const faeActive = computed(
    () =>
        props.faeActive ??
        page.props.activeFactions?.includes('fae-court') ??
        false,
);
const emit = defineEmits<{ 'update:modelValue': [value: ModeSetup] }>();
const id = useId();
const selectedTab = ref('modes');
const inspectedExpansionId = ref<string | null>(null);
const selectedExpansionId = computed(() =>
    props.modelValue.fae_court ? 'fae-court' : props.modelValue.expansion,
);
type ExpansionOption = Pick<
    ExpansionAvailability,
    | 'id'
    | 'name'
    | 'description'
    | 'instructions'
    | 'min_players'
    | 'active'
    | 'available'
>;
const expansionOptions = computed<ExpansionOption[]>(() => [
    ...(faeActive.value || props.modelValue.fae_court
        ? [
              {
                  id: 'fae-court',
                  name: t('modeSelector.expansions.fae_name'),
                  description: t('modeSelector.expansions.fae_description'),
                  instructions: t('modeSelector.expansions.fae_instructions'),
                  min_players: props.faeMinPlayers,
                  active: faeActive.value,
                  available: props.faeAvailable ?? false,
              },
          ]
        : []),
    ...expansionCatalog.value.filter((item) => item.id !== 'fae-court'),
]);
const selectedExpansionName = computed(
    () =>
        expansionOptions.value.find(
            (item) => item.id === selectedExpansionId.value,
        )?.name ?? t('modeSelector.expansions.none'),
);
const inspectedExpansion = computed(() =>
    expansionOptions.value.find(
        (item) =>
            item.id ===
            (inspectedExpansionId.value ?? selectedExpansionId.value),
    ),
);
function chooseExpansion(expansion: ExpansionOption) {
    if (props.disabled) return;
    inspectedExpansionId.value = expansion.id;
    if (
        props.modelValue.mode !== 'classic' ||
        !expansion.active ||
        !expansion.available
    )
        return;
    update(
        expansion.id === 'fae-court'
            ? { fae_court: true, expansion: null }
            : { expansion: expansion.id, fae_court: false },
    );
}
function clearExpansion() {
    inspectedExpansionId.value = null;
    update({ expansion: null, fae_court: false });
}
const totals = computed(() => rosterTotals(props.modelValue.roles));
const error = computed(() =>
    customModeError(props.modelValue, props.minPlayers, props.maxPlayers),
);
const choices = [
    {
        id: 'classic',
        title: t('modeSelector.classic.name'),
        detail: t('modeSelector.classic.hint'),
    },
    {
        id: 'hard',
        title: t('modeSelector.hard.name'),
        detail: t('modeSelector.hard.hint'),
    },
    {
        id: 'chaos',
        title: t('modeSelector.chaos.name'),
        detail: t('modeSelector.chaos.hint'),
    },
    {
        id: 'paranoia',
        title: t('modeSelector.paranoia.name'),
        detail: t('modeSelector.paranoia.hint'),
    },
    {
        id: 'custom',
        title: t('modeSelector.custom.name'),
        detail: t('modeSelector.custom.hint'),
    },
] as const;
const sides = [
    { id: 'town', name: t('modeSelector.town'), ids: townRoleIds },
    { id: 'cult', name: t('modeSelector.cult'), ids: cultRoleIds },
];
function update(change: Partial<ModeSetup>) {
    if (props.disabled) return;
    if (change.mode && change.mode !== 'classic') {
        change.fae_court = false;
        change.expansion = null;
    }
    if (change.fae_court) change.expansion = null;
    if (change.expansion) change.fae_court = false;
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
        <TabsRoot v-model="selectedTab">
            <TabsList
                class="selector-tabs"
                :aria-label="t('modeSelector.tabs.label')"
            >
                <TabsTrigger value="modes" :disabled="disabled">
                    <strong>{{ t('modeSelector.tabs.modes') }}</strong>
                    <small>{{
                        choices.find((choice) => choice.id === modelValue.mode)
                            ?.title
                    }}</small>
                </TabsTrigger>
                <TabsTrigger value="expansions" :disabled="disabled">
                    <strong>{{ t('modeSelector.tabs.expansions') }}</strong>
                    <small :class="{ 'has-expansion': selectedExpansionId }">{{
                        selectedExpansionName
                    }}</small>
                </TabsTrigger>
            </TabsList>
            <TabsContent value="modes" class="selector-pane">
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
                                    ? t(
                                          'modeSelector.classic.illusions_description',
                                      )
                                    : t(
                                          'modeSelector.classic.original_description',
                                      )
                            }}
                        </p>
                    </template>
                    <template v-else-if="modelValue.mode === 'hard'">
                        <p class="mode-kicker">
                            {{ t('modeSelector.hard.eyebrow') }}
                        </p>
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
                                    chaos_variant: (
                                        $event.target as HTMLSelectElement
                                    ).value as ModeSetup['chaos_variant'],
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
                                t('modeSelector.custom.side_label', {
                                    side: side.name,
                                })
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
                                    enabled:
                                        modelValue.roles[role] !== undefined,
                                }"
                            >
                                <label :for="`${id}-${role}`"
                                    ><input
                                        :id="`${id}-${role}`"
                                        type="checkbox"
                                        :checked="
                                            modelValue.roles[role] !== undefined
                                        "
                                        @change="
                                            toggle(
                                                role,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    /><span
                                        ><strong>{{
                                            roles[role]?.name.replace(
                                                /^The /,
                                                '',
                                            )
                                        }}</strong
                                        ><small>{{
                                            roles[role]?.subtitle.split(
                                                ' · ',
                                            )[1]
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
                        <p v-if="error" class="form-error" role="status">
                            {{ error }}
                        </p>
                        <p v-else class="mode-footnote">
                            {{
                                t('modeSelector.custom.ready', {
                                    total: totals.total,
                                })
                            }}
                        </p>
                    </template>
                </div>
            </TabsContent>
            <TabsContent value="expansions" class="selector-pane">
                <div class="expansion-intro">
                    <p>{{ t('modeSelector.expansions.hint') }}</p>
                    <a
                        href="/settings/profile#store"
                        target="_blank"
                        rel="noopener"
                        >{{ t('modeSelector.expansions.store') }}</a
                    >
                </div>
                <div
                    v-if="modelValue.mode !== 'classic'"
                    class="expansion-mode-notice"
                >
                    <p>{{ t('modeSelector.expansions.classic_required') }}</p>
                    <button type="button" @click="update({ mode: 'classic' })">
                        {{ t('modeSelector.expansions.switch_classic') }}
                    </button>
                </div>
                <div
                    class="expansion-choices"
                    role="group"
                    :aria-label="t('modeSelector.tabs.expansions')"
                >
                    <button
                        type="button"
                        class="expansion-choice expansion-choice--none"
                        :class="{ selected: !selectedExpansionId }"
                        :aria-pressed="!selectedExpansionId"
                        @click="clearExpansion"
                    >
                        <span class="expansion-symbol" aria-hidden="true"
                            >&mdash;</span
                        >
                        <span class="expansion-choice-copy"
                            ><strong>{{
                                t('modeSelector.expansions.none')
                            }}</strong
                            ><small>{{
                                t('modeSelector.expansions.base_game')
                            }}</small></span
                        >
                        <Check
                            v-if="!selectedExpansionId"
                            :size="16"
                            aria-hidden="true"
                        />
                        <Circle v-else :size="16" aria-hidden="true" />
                    </button>
                    <button
                        v-for="expansion in expansionOptions"
                        :key="expansion.id"
                        type="button"
                        class="expansion-choice"
                        :class="{
                            selected: selectedExpansionId === expansion.id,
                            inspected: inspectedExpansion?.id === expansion.id,
                        }"
                        :style="{
                            '--faction-color':
                                factionStyle[expansion.id]?.color,
                        }"
                        :aria-pressed="selectedExpansionId === expansion.id"
                        :aria-controls="`${id}-expansion-detail`"
                        @click="chooseExpansion(expansion)"
                    >
                        <span class="expansion-symbol" aria-hidden="true">{{
                            factionStyle[expansion.id]?.symbol
                        }}</span>
                        <span class="expansion-choice-copy">
                            <strong>{{ expansion.name }}</strong>
                            <small>{{
                                selectedExpansionId === expansion.id
                                    ? t('modeSelector.expansions.selected')
                                    : !expansion.active
                                      ? t(
                                            'modeSelector.expansions.inactive_label',
                                        )
                                      : !expansion.available
                                        ? t(
                                              'modeSelector.expansions.owner_needed',
                                          )
                                        : t('modeSelector.expansions.players', {
                                              min: expansion.min_players,
                                              max: maxPlayers,
                                          })
                            }}</small>
                        </span>
                        <Check
                            v-if="selectedExpansionId === expansion.id"
                            :size="16"
                            aria-hidden="true"
                        />
                        <LockKeyhole
                            v-else-if="
                                !expansion.active || !expansion.available
                            "
                            :size="15"
                            aria-hidden="true"
                        />
                        <Circle v-else :size="16" aria-hidden="true" />
                    </button>
                </div>
                <p v-if="!expansionOptions.length" class="expansion-help">
                    {{ t('modeSelector.expansions.empty') }}
                </p>
                <p
                    v-else-if="!expansionOptions.some((item) => item.available)"
                    class="expansion-help"
                >
                    {{
                        t(
                            props.expansionCatalog === undefined
                                ? 'modeSelector.expansions.creation_hint'
                                : 'modeSelector.expansions.unlock_hint',
                        )
                    }}
                </p>
                <section
                    v-if="inspectedExpansion"
                    :id="`${id}-expansion-detail`"
                    class="expansion-detail"
                    :style="{
                        '--faction-color':
                            factionStyle[inspectedExpansion.id]?.color,
                    }"
                    :aria-labelledby="`${id}-expansion-title`"
                    aria-live="polite"
                >
                    <div class="expansion-detail-heading">
                        <h3 :id="`${id}-expansion-title`">
                            {{ inspectedExpansion.name }}
                        </h3>
                        <span>{{
                            t('modeSelector.expansions.players', {
                                min: inspectedExpansion.min_players,
                                max: maxPlayers,
                            })
                        }}</span>
                    </div>
                    <p>{{ inspectedExpansion.description }}</p>
                    <p>{{ inspectedExpansion.instructions }}</p>
                    <p
                        v-if="!inspectedExpansion.active"
                        class="expansion-status"
                    >
                        {{ t('modeSelector.expansions.inactive_hint') }}
                    </p>
                    <p
                        v-else-if="!inspectedExpansion.available"
                        class="expansion-status"
                    >
                        {{ t('modeSelector.expansions.locked_hint') }}
                    </p>
                    <p v-else class="expansion-status">
                        {{ t('modeSelector.expansions.shared_hint') }}
                    </p>
                    <button
                        v-if="selectedExpansionId === inspectedExpansion.id"
                        type="button"
                        class="expansion-remove"
                        @click="clearExpansion"
                    >
                        {{ t('modeSelector.expansions.remove') }}
                    </button>
                </section>
            </TabsContent>
        </TabsRoot>
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
.selector-tabs {
    position: sticky;
    top: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
    margin-bottom: 16px;
    padding: 8px 0;
    border-bottom: 1px solid var(--line);
    background: #182b29;
}
.selector-tabs button {
    display: grid;
    gap: 4px;
    min-width: 0;
    min-height: 60px;
    padding: 10px 12px;
    border: 1px solid transparent;
    border-radius: 3px;
    color: var(--muted);
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
}
.selector-tabs button[data-state='active'] {
    border-color: #8fac8e70;
    color: var(--cream);
    background: #8fac8e1c;
}
.selector-tabs strong {
    font-size: 13px;
    font-weight: 600;
}
.selector-tabs small {
    overflow: hidden;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.selector-tabs .has-expansion {
    color: var(--green);
}
.selector-pane {
    outline-offset: 4px;
}
.expansion-intro {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}
.expansion-intro p {
    margin: 0;
}
.expansion-intro a {
    flex-shrink: 0;
    color: var(--green);
    text-decoration: underline;
    text-underline-offset: 3px;
}
.expansion-choices {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.expansion-choice {
    --faction-color: var(--green);
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    min-height: 82px;
    padding: 11px 9px;
    border: 1px solid var(--line);
    border-radius: 3px;
    background: rgb(0 0 0 / 12%);
    color: var(--muted);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s,
        background 0.15s;
}
.expansion-choice:hover,
.expansion-choice.inspected {
    border-color: var(--faction-color);
}
.expansion-choice.selected {
    border-color: var(--faction-color);
    background: #8fac8e1c;
}
.expansion-symbol {
    flex-shrink: 0;
    width: 18px;
    color: var(--faction-color);
    font-size: 23px;
    text-align: center;
}
.expansion-choice-copy {
    display: grid;
    gap: 5px;
    min-width: 0;
}
.expansion-choice strong {
    color: var(--cream);
    font-family: 'Fraunces', Georgia, serif;
    font-size: 15px;
    font-weight: 400;
    line-height: 1.2;
}
.expansion-choice small {
    font-size: 10px;
    line-height: 1.3;
}
.expansion-choice > svg {
    position: absolute;
    top: 7px;
    right: 7px;
    color: var(--faction-color);
}
.expansion-choice-copy {
    padding-block: 8px 0;
}
.expansion-help {
    margin: 12px 0 0;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
}
.expansion-mode-notice {
    margin-bottom: 14px;
    padding: 12px;
    border-left: 2px solid var(--green);
    background: #8fac8e0d;
    color: var(--cream);
    font-size: 12px;
    line-height: 1.6;
}
.expansion-mode-notice p {
    margin: 0 0 8px;
}
.expansion-mode-notice button,
.expansion-remove {
    min-height: 36px;
    padding: 7px 10px;
    border: 1px solid var(--line);
    border-radius: 3px;
    background: transparent;
    color: var(--green);
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}
.expansion-mode-notice button:hover,
.expansion-remove:hover {
    border-color: var(--green);
    background: #8fac8e1c;
}
.expansion-detail {
    margin-top: 18px;
    padding: 16px 0 0 14px;
    border-top: 1px solid var(--line);
    border-left: 2px solid var(--faction-color, var(--green));
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}
.expansion-detail-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 4px 12px;
    margin-bottom: 10px;
}
.expansion-detail-heading h3 {
    color: var(--faction-color, var(--cream));
    font-family: 'Fraunces', Georgia, serif;
    font-size: 21px;
    font-weight: 400;
}
.expansion-detail-heading span {
    font-size: 10px;
}
.expansion-detail p {
    margin: 0 0 10px;
}
.expansion-detail .expansion-status {
    color: var(--cream);
    font-size: 11px;
}
.mode-selector button:focus-visible {
    outline: 2px solid var(--cream);
    outline-offset: 2px;
}
.mode-selector button:disabled {
    cursor: default;
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
    padding: 11px;
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
    font-size: 19px;
    font-weight: 400;
}
.mode-choice small {
    grid-column: 1 / -1;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
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
    .mode-choice,
    .expansion-choice {
        transition: none;
    }
}
</style>
