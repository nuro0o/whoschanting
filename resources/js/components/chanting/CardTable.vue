<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, Flame, LockKeyhole, RotateCcw } from '@lucide/vue';
import { roles, type Player, type RoomState } from '@/lib/chanting';
import CharacterPortrait from './CharacterPortrait.vue';
import RitualTableScene from './RitualTableScene.vue';
import { ritualSceneState } from '@/lib/ritualSceneState';

// The table uses public seats and ritual progress. A selection and wax seal are
// local to this browser; final roles are read only from public player fields.
const props = defineProps<{
    players: Player[];
    phase: RoomState['phase'];
    phaseId: number;
    meId: string;
    submitted: boolean;
    actionSerial: number;
    ritualTokens: number;
    ritualThreshold: number;
    winner: 'town' | 'cult' | null;
    selectedTarget: string | null;
    canSelect: boolean;
    eligibleTargetIds?: string[];
    hauntedSeatId?: string | null;
}>();
const emit = defineEmits<{ select: [id: string] }>();
const scene = ref<InstanceType<typeof RitualTableScene>>();
const projectedSeats = ref<{ left: string; top: string }[]>([]);
const visualMode = ref<'3d' | 'simple'>('simple');
const sceneReady = ref(false);
const sceneUnavailable = ref(false);
const sceneState = computed(() =>
    ritualSceneState({
        phase: props.phase,
        tokens: props.ritualTokens,
        threshold: props.ritualThreshold,
        winner: props.winner,
    }),
);
const sceneSeats = computed(() =>
    props.players.map((player, index) => {
        const position = seatStyle(index);
        return {
            x: Number.parseFloat(position.left) / 100,
            y: Number.parseFloat(position.top) / 100,
            alive: player.alive,
        };
    }),
);
function chooseVisuals(mode: '3d' | 'simple') {
    sceneUnavailable.value = false;
    visualMode.value = mode;
    try {
        localStorage.setItem('chanting-table-visuals', mode);
    } catch {
        /* Storage may be unavailable in private browsers. */
    }
}
function unavailable() {
    sceneUnavailable.value = true;
    sceneReady.value = false;
    visualMode.value = 'simple';
}
onMounted(() => {
    try {
        visualMode.value =
            localStorage.getItem('chanting-table-visuals') === 'simple'
                ? 'simple'
                : '3d';
    } catch {
        visualMode.value = '3d';
    }
});
const phaseChanging = ref(false);
const newlyLit = ref<number[]>([]);
const extinguished = ref<string[]>([]);
const sealRecent = ref(false);
const timers = new Set<ReturnType<typeof setTimeout>>();
function later(callback: () => void, delay: number) {
    const timer = setTimeout(() => {
        timers.delete(timer);
        callback();
    }, delay);
    timers.add(timer);
}
const crowded = computed(() => props.players.length > 6);
function seatStyle(index: number) {
    const count = props.players.length;
    if (crowded.value) {
        const topCount = Math.ceil(count / 2);
        const rowCount = index < topCount ? topCount : count - topCount;
        const col = index < topCount ? index : count - index - 1;
        return {
            left: `${10 + (col * 80) / Math.max(1, rowCount - 1)}%`,
            top: index < topCount ? '20%' : '80%',
        };
    }
    if (count === 3)
        return [
            { left: '50%', top: '17%' },
            { left: '81%', top: '76%' },
            { left: '19%', top: '76%' },
        ][index];
    const angle = (index / Math.max(1, count)) * Math.PI * 2 - Math.PI / 2;
    return {
        left: `${50 + Math.cos(angle) * 36}%`,
        top: `${50 + Math.sin(angle) * 34}%`,
    };
}
function selectable(player: Player) {
    return (
        props.canSelect &&
        player.id !== props.meId &&
        (props.eligibleTargetIds
            ? props.eligibleTargetIds.includes(player.id)
            : player.alive)
    );
}
function seatLabel(player: Player) {
    const identity = `${player.name}${player.id === props.meId ? ', you' : ''}`;
    if (props.phase === 'finished' && player.role)
        return `${identity}, ${roles[player.role]?.name ?? player.role}${player.alive ? '' : ', banished'}`;
    return `${identity}${!player.alive ? ', banished' : ''}${selectable(player) ? ', select as target' : ''}`;
}
const caption = computed(() =>
    props.canSelect
        ? 'Choose a seat, then confirm your choice.'
        : {
              lobby: 'A place for every alibi.',
              reveal: 'Your secret awaits in your private role card.',
              night: 'Cards down. Secrets kept.',
              discussion: 'Stories on the table.',
              voting: 'One choice. Sealed until the count.',
              finished: 'Every secret has a face.',
          }[props.phase],
);
const ritualLabel = computed(() =>
    props.phase === 'lobby'
        ? 'Ritual goal'
        : props.phase === 'finished'
          ? 'Steps achieved'
          : 'Ritual steps',
);
watch(
    () => props.phaseId,
    (next, previous) => {
        if (next === previous) return;
        phaseChanging.value = true;
        later(() => {
            phaseChanging.value = false;
        }, 900);
    },
);
watch(
    () => props.ritualTokens,
    (next, previous) => {
        newlyLit.value =
            next > previous
                ? Array.from(
                      { length: next - previous },
                      (_, i) => previous + i + 1,
                  )
                : [];
        later(() => {
            newlyLit.value = [];
        }, 1000);
    },
);
watch(
    () =>
        props.players.map((player) => ({ id: player.id, alive: player.alive })),
    (next, previous) => {
        const ids = next
            .filter(
                (player) =>
                    !player.alive &&
                    previous.some((old) => old.id === player.id && old.alive),
            )
            .map((player) => player.id);
        if (!ids.length) return;
        extinguished.value = ids;
        later(() => {
            extinguished.value = [];
        }, 1400);
    },
);
watch(
    () => props.actionSerial,
    (next, previous) => {
        if (next <= previous) return;
        sealRecent.value = true;
        later(() => {
            sealRecent.value = false;
        }, 1200);
    },
);
onBeforeUnmount(() => {
    timers.forEach(clearTimeout);
});
</script>

<template>
    <section
        class="table-panel table-panel-immersive"
        :class="{
            'phase-arriving': phaseChanging,
        }"
        :data-phase="phase"
        :data-winner="winner"
        aria-label="The village card table"
    >
        <div class="table-topline">
            <span class="eyebrow">THE VILLAGE TABLE</span>
            <div class="table-visuals" role="group" aria-label="Table visuals">
                <span>View</span>
                <button
                    type="button"
                    :aria-pressed="visualMode === '3d'"
                    @click="chooseVisuals('3d')"
                >
                    3D
                </button>
                <button
                    type="button"
                    :aria-pressed="visualMode === 'simple'"
                    @click="chooseVisuals('simple')"
                >
                    Simple
                </button>
                <button
                    v-if="sceneReady"
                    type="button"
                    class="table-reset"
                    @click="scene?.resetView()"
                >
                    <RotateCcw :size="13" /> Reset view
                </button>
            </div>
        </div>
        <p v-if="sceneUnavailable" class="table-visual-notice" role="status">
            3D is unavailable right now. The simple table is ready to play.
        </p>
        <div
            class="card-table"
            :class="{
                'is-night': phase === 'night',
                'is-small': players.length <= 4,
                'is-crowded': crowded,
                'is-haunted': !!hauntedSeatId,
                'is-three': sceneReady,
            }"
        >
            <RitualTableScene
                ref="scene"
                :enabled="visualMode === '3d'"
                interactive
                :state="sceneState"
                :seats="sceneSeats"
                @positions="projectedSeats = $event"
                @ready="sceneReady = $event"
                @unavailable="unavailable"
            />
            <div class="table-surface" aria-hidden="true">
                <div class="table-inlay"></div>
            </div>
            <div class="table-atmosphere" aria-hidden="true"></div>
            <div
                v-if="hauntedSeatId"
                class="phantom-shadow"
                aria-hidden="true"
            ></div>
            <div
                v-if="!sceneReady"
                class="table-ritual"
                :class="{ 'ritual-awakening': newlyLit.length > 0 }"
                role="img"
                :aria-label="`${ritualTokens} of ${ritualThreshold} ritual steps${phase === 'finished' ? ' achieved' : ''}`"
            >
                <div class="ritual-ring" aria-hidden="true">
                    <span
                        v-for="step in ritualThreshold"
                        :key="step"
                        class="ritual-rune"
                        :class="{
                            'is-lit': step <= ritualTokens,
                            'is-new': newlyLit.includes(step),
                        }"
                        :style="{
                            transform: `rotate(${((step - 1) / ritualThreshold) * 360}deg) translateY(calc(var(--ring-size) / -2))`,
                        }"
                        >ᛟ</span
                    >
                </div>
                <span class="ritual-center-icon" aria-hidden="true"
                    ><Flame :size="19"
                /></span>
                <strong
                    >{{
                        phase === 'lobby'
                            ? ritualThreshold || '—'
                            : ritualTokens
                    }}<small v-if="phase !== 'lobby'">
                        / {{ ritualThreshold }}</small
                    ></strong
                >
                <span class="ritual-center-label">{{ ritualLabel }}</span>
            </div>
            <component
                :is="selectable(player) ? 'button' : 'div'"
                v-for="(player, index) in players"
                :key="player.id"
                class="table-seat"
                :class="{
                    'is-me': player.id === meId,
                    'is-banished': !player.alive,
                    'is-selectable': selectable(player),
                    'is-selected': selectedTarget === player.id && canSelect,
                    'is-extinguishing': extinguished.includes(player.id),
                    'is-face-up': phase === 'finished',
                    'phantom-seat': player.id === hauntedSeatId,
                }"
                :style="
                    sceneReady
                        ? (projectedSeats[index] ?? seatStyle(index))
                        : seatStyle(index)
                "
                :type="selectable(player) ? 'button' : undefined"
                :role="selectable(player) ? undefined : 'group'"
                :aria-label="seatLabel(player)"
                :aria-pressed="
                    selectable(player)
                        ? selectedTarget === player.id
                        : undefined
                "
                @click="selectable(player) && emit('select', player.id)"
            >
                <span class="seat-card" aria-hidden="true"></span>
                <CharacterPortrait
                    :character="player.character"
                    :frame="player.customization?.frame"
                    :accent="player.customization?.accent"
                    decorative
                />
                <span
                    v-if="player.id === hauntedSeatId"
                    class="phantom-face"
                    aria-hidden="true"
                    ><i></i><i></i
                ></span>
                <span class="seat-candle" aria-hidden="true"><i></i></span>
                <span
                    v-if="
                        player.id === meId &&
                        (sealRecent ||
                            (submitted && ['night', 'voting'].includes(phase)))
                    "
                    class="seat-seal"
                    :class="{ 'seal-arriving': sealRecent }"
                    role="img"
                    aria-label="Your action is sealed"
                    ><LockKeyhole :size="12"
                /></span>
                <span
                    v-if="selectedTarget === player.id && canSelect"
                    class="seat-selected-mark"
                    aria-hidden="true"
                    ><Check :size="13"
                /></span>
                <span class="seat-name" :title="player.name"
                    >{{ player.name
                    }}<small v-if="player.id === meId">You</small></span
                >
                <span
                    v-if="phase === 'finished' && player.role"
                    class="seat-final-role"
                    :class="{ 'is-cult': player.alignment === 'cult' }"
                    >{{ roles[player.role]?.name ?? player.role }}</span
                >
                <span v-else-if="!player.alive" class="seat-banished"
                    >Banished</span
                >
                <span
                    v-else-if="
                        (phase === 'discussion' && player.discussion_ready) ||
                        (phase === 'lobby' && player.ready)
                    "
                    class="seat-ready"
                    >Ready</span
                >
            </component>
        </div>
        <div v-if="sceneReady" class="table-summoning-status">
            <Flame :size="15" aria-hidden="true" />
            <span
                ><strong
                    >{{
                        phase === 'lobby'
                            ? ritualThreshold || '—'
                            : ritualTokens
                    }}<small v-if="phase !== 'lobby'">
                        / {{ ritualThreshold }}</small
                    ></strong
                >
                {{ ritualLabel }}</span
            >
            <span class="summoning-state">{{
                sceneState.calmed
                    ? 'The seal holds'
                    : phase === 'finished' && winner === 'cult'
                      ? 'The deep has risen'
                      : sceneState.progress === 1
                        ? 'One final vote remains'
                        : sceneState.emergence > 0
                          ? 'Something stirs below'
                          : sceneState.progress > 0
                            ? 'The circle awakens'
                            : 'The circle lies quiet'
            }}</span>
        </div>
        <p class="table-caption">{{ caption }}</p>
        <p v-if="sceneReady" class="table-camera-hint">
            Drag the table to orbit · Scroll to zoom
            <span
                >Focus the table: arrow keys to orbit · + / − to zoom · Home to
                reset</span
            >
        </p>
        <div
            class="table-compact-roster"
            role="group"
            aria-label="Village seating list"
        >
            <p>
                AROUND THE TABLE <span>{{ players.length }} villagers</span>
            </p>
            <div>
                <component
                    :is="selectable(player) ? 'button' : 'div'"
                    v-for="player in players"
                    :key="player.id"
                    :type="selectable(player) ? 'button' : undefined"
                    :aria-label="seatLabel(player)"
                    :aria-pressed="
                        selectable(player)
                            ? selectedTarget === player.id
                            : undefined
                    "
                    @click="selectable(player) && emit('select', player.id)"
                >
                    <CharacterPortrait
                        :character="player.character"
                        :frame="player.customization?.frame"
                        :accent="player.customization?.accent"
                        decorative
                    />
                    <span
                        ><strong :title="player.name">{{ player.name }}</strong
                        ><small>{{
                            phase === 'finished' && player.role
                                ? (roles[player.role]?.name ?? player.role)
                                : !player.alive
                                  ? 'Banished'
                                  : player.id === meId
                                    ? 'You'
                                    : selectedTarget === player.id && canSelect
                                      ? 'Selected'
                                      : 'Villager'
                        }}</small></span
                    >
                </component>
            </div>
        </div>
        <details class="table-seating-list">
            <summary>
                Seating list <span>{{ players.length }} villagers</span>
            </summary>
            <div>
                <component
                    :is="selectable(player) ? 'button' : 'span'"
                    v-for="player in players"
                    :key="player.id"
                    :type="selectable(player) ? 'button' : undefined"
                    :aria-label="seatLabel(player)"
                    :aria-pressed="
                        selectable(player)
                            ? selectedTarget === player.id
                            : undefined
                    "
                    @click="selectable(player) && emit('select', player.id)"
                >
                    {{ player.name
                    }}<small>{{
                        player.id === meId
                            ? 'You'
                            : !player.alive
                              ? 'Banished'
                              : ''
                    }}</small>
                </component>
            </div>
        </details>
    </section>
</template>

<style scoped>
.table-compact-roster {
    display: none;
}
@media (max-width: 900px) {
    .table-panel.table-panel-immersive .card-table {
        min-height: 100px;
    }
    .table-panel-immersive .table-caption {
        display: none;
    }
    .table-panel-immersive .summoning-state {
        display: none;
    }
    .table-panel-immersive .card-table .table-seat {
        display: none;
    }
    .table-panel-immersive .table-seating-list {
        display: none;
    }
    .table-panel-immersive .table-topline {
        padding: 10px 12px 0;
    }
    .table-panel-immersive .table-camera-hint {
        padding: 2px 10px 7px;
        font-size: 10px;
    }
    .table-panel-immersive .table-camera-hint span {
        display: none;
    }
    .table-compact-roster {
        display: block;
        flex-shrink: 0;
        border-top: 1px solid #afb48b30;
        padding: 8px 10px;
    }
    .table-compact-roster > p {
        margin: 0 0 6px;
        font-size: 9px;
        letter-spacing: 1px;
        color: #d8d7b9;
    }
    .table-compact-roster > p > span {
        float: right;
        letter-spacing: 0;
        color: #aabcaa;
    }
    .table-compact-roster > div {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
        max-height: 82px;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .table-compact-roster > div > * {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        padding: 3px 5px;
        border: 1px solid #52614b;
        background: #182a2a;
        color: #e1dfc2;
        text-align: left;
        font: inherit;
    }
    .table-compact-roster :deep(.character-portrait) {
        width: 25px;
        flex: 0 0 25px;
    }
    .table-compact-roster > div > * > span {
        display: block;
        min-width: 0;
    }
    .table-compact-roster strong {
        display: block;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: 10px;
        font-weight: 500;
    }
    .table-compact-roster small {
        display: block;
        color: #aabcaa;
        font-size: 8px;
    }
    .table-compact-roster button {
        cursor: pointer;
    }
    .table-compact-roster button:hover,
    .table-compact-roster button[aria-pressed='true'] {
        background: #364e3b;
        border-color: #b7c78b;
    }
    .table-compact-roster :focus-visible {
        outline: 2px solid #dec784;
        outline-offset: -2px;
    }
}
.table-visuals .table-reset {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 6px;
    border-color: #afa67d70;
}
.table-panel-immersive {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.table-panel-immersive .card-table {
    flex: 1;
    min-height: 180px;
    height: auto;
    width: 100%;
    max-width: none;
}
.table-panel-immersive .table-seat {
    z-index: 1;
}
@media (max-width: 900px) {
    .table-panel-immersive .card-table .table-seat {
        width: 76px;
    }
    .table-panel-immersive .card-table .table-seat .character-portrait {
        width: 42px;
    }
    .table-panel-immersive .card-table .table-seat .seat-card {
        width: 42px;
        height: 50px;
    }
    .table-panel-immersive .card-table .table-seat .seat-name {
        max-width: 76px;
        font-size: 10px;
    }
    .table-panel-immersive .card-table.is-crowded .table-seat {
        width: 54px;
    }
    .table-panel-immersive
        .card-table.is-crowded
        .table-seat
        .character-portrait {
        width: 32px;
    }
    .table-panel-immersive .card-table.is-crowded .table-seat .seat-card {
        width: 33px;
        height: 42px;
    }
    .table-panel-immersive .card-table.is-crowded .seat-name {
        max-width: 54px;
        font-size: 9px;
    }
}
.table-panel-immersive .table-topline {
    flex-wrap: wrap;
    gap: 8px;
}
.table-panel-immersive .table-caption {
    margin: 0;
    padding: 4px 12px;
}
.table-panel-immersive .table-summoning-status {
    flex-wrap: wrap;
}
.table-camera-hint {
    margin: 0;
    padding: 6px 12px 12px;
    color: #b8c3b4;
    font-size: 11px;
    text-align: center;
}
.table-camera-hint span {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    color: #9eafa4;
}
.table-seating-list {
    border-top: 1px solid #afb48b30;
    font-size: 11px;
}
.table-seating-list summary {
    padding: 10px 16px;
    cursor: pointer;
    color: #d8d7b9;
}
.table-seating-list summary span {
    float: right;
    color: #9eafa4;
}
.table-seating-list > div {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 12px 12px;
    max-height: 100px;
    overflow-y: auto;
}
.table-seating-list button,
.table-seating-list > div > span {
    padding: 6px 9px;
    background: #182a2a;
    border: 1px solid #52614b;
    color: #e1dfc2;
    font: inherit;
}
.table-seating-list button {
    cursor: pointer;
}
.table-seating-list button:hover,
.table-seating-list button[aria-pressed='true'] {
    background: #364e3b;
    border-color: #b7c78b;
}
.table-seating-list small {
    margin-left: 5px;
    color: #aabcaa;
}
.table-seating-list :focus-visible {
    outline: 2px solid #dec784;
    outline-offset: -2px;
}
@media (max-width: 760px) {
    .table-camera-hint span {
        display: none;
    }
    .table-panel-immersive .table-topline {
        padding: 10px 12px 0;
    }
    .table-camera-hint {
        padding-bottom: 7px;
        font-size: 10px;
    }
}
.table-visuals {
    display: flex;
    align-items: center;
    gap: 3px;
    color: #b8c3b4;
    font-size: 10px;
}
.table-visuals > span {
    margin-right: 5px;
}
.table-visuals button {
    padding: 5px 9px;
    color: #c4cbbb;
    border: 1px solid transparent;
    border-radius: 3px;
    font: inherit;
    cursor: pointer;
    background: transparent;
}
.table-visuals button[aria-pressed='true'] {
    background: #bfba8a18;
    border-color: #afa67d70;
    color: #f1e7c4;
}
.table-visuals button:hover {
    background: #c4c2a52b;
}
.table-visuals button:focus-visible {
    outline: 2px solid #dec784;
    outline-offset: 2px;
}
.table-visual-notice {
    padding: 8px 18px 0;
    margin: 0;
    color: #c5cbbb;
    font-size: 11px;
}
.is-three .table-surface,
.is-three .table-atmosphere {
    visibility: hidden;
}
.is-three .seat-candle {
    display: none;
}
.table-summoning-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #c3cbaa;
    padding: 3px 14px 7px;
    font-size: 10px;
}
.table-summoning-status strong {
    color: #efe8ca;
    font-size: 17px;
    font-family: 'Fraunces', Georgia, serif;
    margin-right: 5px;
}
.table-summoning-status small {
    color: #b8c1ae;
    font-size: 12px;
}
.summoning-state {
    border-left: 1px solid #a6b69a40;
    padding-left: 9px;
    color: #b4c5b7;
}
@media (max-width: 420px) {
    .table-summoning-status {
        font-size: 9px;
        gap: 5px;
    }
    .summoning-state {
        padding-left: 6px;
    }
}
.phantom-face {
    --phantom-size: 57px;
    pointer-events: none;
    position: absolute;
    top: 2px;
    left: 50%;
    width: var(--phantom-size);
    height: var(--phantom-size);
    transform: translateX(-50%);
    border-radius: 48% 48% 20% 20%;
    background: radial-gradient(
        ellipse at 50% 40%,
        #080b10 27%,
        #343245 30%,
        #10151f 65%
    );
    box-shadow: 0 0 16px #9180bb50;
    display: flex;
    justify-content: center;
    gap: 9px;
    padding-top: calc(var(--phantom-size) * 0.43);
    opacity: 0.75;
    animation: false-face 9s ease-in-out infinite;
}
.phantom-face i {
    width: 4px;
    height: 2px;
    background: #d2abc9;
    box-shadow: 0 0 5px #d2abc9;
}
.phantom-shadow {
    pointer-events: none;
    position: absolute;
    top: 27%;
    left: 35%;
    width: 42px;
    height: 110px;
    background: linear-gradient(#55516b60, #080b1030);
    border-radius: 50% 50% 20% 20%;
    filter: blur(5px);
    opacity: 0.35;
    animation: wandering-shadow 15s ease-in-out infinite alternate;
}
@keyframes false-face {
    0%,
    100% {
        opacity: 0.1;
    }
    45%,
    70% {
        opacity: 0.9;
    }
}
@keyframes wandering-shadow {
    to {
        transform: translate(95px, -15px) skew(-10deg);
        opacity: 0.1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .phantom-face,
    .phantom-shadow {
        animation: none;
    }
}
.is-crowded .phantom-face {
    --phantom-size: 48px;
}
@media (max-width: 760px) {
    .phantom-face {
        --phantom-size: 45px;
    }
    .is-crowded .phantom-face {
        --phantom-size: 35px;
    }
}
</style>
