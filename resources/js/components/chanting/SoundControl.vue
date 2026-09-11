<script setup lang="ts">
import { SlidersHorizontal, Volume2, VolumeX } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { PhaseMusic, type MusicLibrary } from '@/lib/phaseMusic';
import {
    WhisperAtmosphere,
    type WhisperLibrary,
} from '@/lib/whisperAtmosphere';
import type { RitualDisturbance } from '@/lib/ritualDisturbances';
import {
    CoastalAudio,
    defaultSoundPreferences,
    parseSoundPreferences,
    SoundCueTracker,
    soundStorageKey,
    type SoundSnapshot,
} from '@/lib/coastalAudio';

const props = defineProps<
    SoundSnapshot & {
        music: MusicLibrary;
        hauntPan?: number | null;
        whispers?: WhisperLibrary;
        ritualThreshold?: number;
        atmosphereAllowed?: boolean;
        disturbance?: RitualDisturbance | null;
    }
>();
const emit = defineEmits<{ disturbances: [enabled: boolean] }>();
const preferences = ref({ ...defaultSoundPreferences });
const active = ref(false);
const busy = ref(false);
const issue = ref('');
const musicIssue = ref('');
const settings = ref<HTMLDetailsElement>();
const settingsOpen = ref(false);
const controlId = useId();
const tracker = new SoundCueTracker();
let engine: CoastalAudio | null = null;
let music: PhaseMusic | null = null;
let whispers: WhisperAtmosphere | null = null;
let whisperTimer: ReturnType<typeof setInterval> | undefined;
let activated = false;
let disposed = false;
const label = computed(() =>
    preferences.value.enabled ? 'Sound on' : 'Sound off',
);

function save() {
    try {
        localStorage.setItem(
            soundStorageKey,
            JSON.stringify(preferences.value),
        );
    } catch {
        /* Optional storage may be blocked by browser privacy settings. */
    }
}
function sync() {
    engine?.setHaunting(props.hauntPan ?? null);
    const cues = tracker.update({ ...props }, active.value && !document.hidden);
    engine?.update(preferences.value, props.phase);
    engine?.play(cues);
    syncMusic();
    syncWhispers();
}
function syncWhispers(running = active.value) {
    whispers?.update({
        eventId:
            props.disturbance?.kind === 'whisper' ? props.disturbance.id : null,
        phase: props.phase,
        phaseId: props.phaseId,
        tokens: props.ritualTokens,
        threshold: props.ritualThreshold ?? 0,
        seconds: props.seconds,
        allowed:
            running &&
            preferences.value.enabled &&
            preferences.value.disturbances &&
            !document.hidden &&
            props.connected &&
            props.alive &&
            (props.atmosphereAllowed ?? true) &&
            !document.activeElement?.closest(
                'input, textarea, [contenteditable="true"]',
            ),
        volume: preferences.value.whispers,
    });
}
function syncMusic(running = active.value) {
    music?.update(
        props.phase,
        preferences.value.music,
        running && !document.hidden,
    );
}
async function enable() {
    if (!engine || busy.value || document.hidden) return;
    busy.value = true;
    issue.value = '';
    tracker.update({ ...props }, false);
    engine.setHaunting(props.hauntPan ?? null);
    engine.update(preferences.value, props.phase);
    // Start media within the user's gesture, before awaiting AudioContext.resume().
    syncMusic(true);
    syncWhispers(true);
    const started = await engine.enable();
    if (disposed) return;
    busy.value = false;
    if (started && !document.hidden) {
        activated = true;
        preferences.value.enabled = true;
        active.value = true;
        save();
    } else {
        syncMusic(false);
        syncWhispers(false);
        if (!document.hidden)
            issue.value =
                'Sound could not start. Turn sound off and on to try again.';
    }
}
function toggle() {
    if (preferences.value.enabled) {
        preferences.value.enabled = false;
        activated = false;
        engine?.close();
        issue.value = '';
        musicIssue.value = '';
        save();
    } else {
        preferences.value.enabled = true;
        save();
        void enable();
    }
}
function startOnInteraction(event: Event) {
    // Let the sound button handle its own gesture, including muting before playback.
    if ((event.target as Element | null)?.closest?.('.sound-control')) return;
    if (preferences.value.enabled && !active.value) void enable();
}
function visibilityChanged() {
    tracker.update({ ...props }, false);
    if (document.hidden) syncWhispers(false);
    if (document.hidden) engine?.pause();
    else if (activated && preferences.value.enabled) void enable();
}
function closeSettings(event: Event) {
    if (
        settings.value?.open &&
        !settings.value.contains(event.target as Node)
    ) {
        const focusWasInside = settings.value.contains(document.activeElement);
        settings.value.open = false;
        if (focusWasInside) settings.value.querySelector('summary')?.focus();
    }
}
function escapeSettings() {
    if (!settings.value?.open) return;
    settings.value.open = false;
    settings.value.querySelector('summary')?.focus();
}

onMounted(() => {
    try {
        preferences.value = parseSoundPreferences(
            localStorage.getItem(soundStorageKey),
            localStorage.getItem('chanting-sound'),
        );
    } catch {
        /* Keep the default when browser privacy settings block storage. */
    }
    engine = new CoastalAudio((running) => {
        active.value = running && !document.hidden;
        syncMusic(running);
        syncWhispers(running);
    });
    music = new PhaseMusic(props.music, (message) => {
        musicIssue.value = message;
    });
    whispers = new WhisperAtmosphere(
        props.whispers ?? { ambient: [], oneoff: [] },
    );
    whisperTimer = setInterval(() => syncWhispers(), 250);
    emit('disturbances', preferences.value.disturbances);
    tracker.update({ ...props }, false);
    document.addEventListener('visibilitychange', visibilityChanged);
    document.addEventListener('pointerdown', closeSettings);
    document.addEventListener('click', startOnInteraction);
    document.addEventListener('keydown', startOnInteraction);
});
watch(
    () => ({ ...props }),
    () => {
        if (engine) sync();
    },
);
watch(
    preferences,
    () => {
        save();
        engine?.update(preferences.value, props.phase);
        syncMusic();
        syncWhispers();
        emit('disturbances', preferences.value.disturbances);
    },
    { deep: true },
);
onBeforeUnmount(() => {
    disposed = true;
    clearInterval(whisperTimer);
    document.removeEventListener('visibilitychange', visibilityChanged);
    document.removeEventListener('pointerdown', closeSettings);
    document.removeEventListener('click', startOnInteraction);
    document.removeEventListener('keydown', startOnInteraction);
    engine?.close();
    music?.close();
    whispers?.close();
    music = null;
    engine = null;
});
</script>

<template>
    <div class="coastal-sound">
        <details
            ref="settings"
            class="sound-settings"
            @toggle="settingsOpen = settings?.open ?? false"
            @keydown.esc.stop.prevent="escapeSettings"
        >
            <summary
                aria-label="Sound and atmosphere settings"
                :aria-expanded="settingsOpen"
                :aria-controls="`${controlId}-panel`"
                title="Sound and atmosphere settings"
            >
                <SlidersHorizontal :size="16" aria-hidden="true" />
                <span>{{ label }}</span>
            </summary>
            <div :id="`${controlId}-panel`" class="sound-settings-panel">
                <button
                    class="sound-control"
                    :aria-pressed="preferences.enabled"
                    :disabled="busy"
                    @click="toggle"
                >
                    <Volume2
                        v-if="preferences.enabled"
                        :size="15"
                        aria-hidden="true"
                    />
                    <VolumeX v-else :size="15" aria-hidden="true" />
                    {{ busy ? 'Starting sound...' : label }}
                </button>
                <p class="sound-settings-title">Sounds of the village</p>
                <p class="sound-settings-note">
                    Music, quiet waves, distant bells, and whispers.
                </p>
                <div class="sound-volume">
                    <label :for="`${controlId}-music`">Music</label>
                    <output :for="`${controlId}-music`"
                        >{{ preferences.music }}%</output
                    >
                    <input
                        :id="`${controlId}-music`"
                        v-model.number="preferences.music"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                    />
                </div>
                <div class="sound-volume">
                    <label :for="`${controlId}-effects`">Effects</label>
                    <output :for="`${controlId}-effects`"
                        >{{ preferences.effects }}%</output
                    >
                    <input
                        :id="`${controlId}-effects`"
                        v-model.number="preferences.effects"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                    />
                </div>
                <div class="sound-volume">
                    <label :for="`${controlId}-ambience`">Ambience</label>
                    <output :for="`${controlId}-ambience`"
                        >{{ preferences.ambience }}%</output
                    >
                    <input
                        :id="`${controlId}-ambience`"
                        v-model.number="preferences.ambience"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                    />
                </div>
                <p class="sound-settings-note">
                    Music and ambience soften during discussion.
                </p>
                <div class="sound-volume">
                    <label :for="`${controlId}-whispers`">Whispers</label>
                    <output :for="`${controlId}-whispers`"
                        >{{ preferences.whispers }}%</output
                    >
                    <input
                        :id="`${controlId}-whispers`"
                        v-model.number="preferences.whispers"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                    />
                </div>
                <label class="disturbance-setting">
                    <input v-model="preferences.disturbances" type="checkbox" />
                    Unsettling atmosphere
                </label>
                <p class="sound-settings-note">
                    Fleeting shadows, strange words, whispers, and chat
                    distortions grow with the ritual. These are not clues about
                    anyone's role.
                </p>
                <button
                    v-if="active"
                    class="sound-preview"
                    @click="engine?.play(['seal'])"
                >
                    Preview effects
                </button>
                <p v-else class="sound-settings-note">
                    {{
                        preferences.enabled
                            ? 'Sound starts when you interact with the game.'
                            : 'Turn sound on to listen.'
                    }}
                </p>
            </div>
        </details>
        <span v-if="issue || musicIssue" class="sound-issue" role="status">{{
            issue || musicIssue
        }}</span>
    </div>
</template>

<style scoped>
.disturbance-setting {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-block: 12px 8px;
    font-size: 12px;
}
.disturbance-setting input {
    accent-color: var(--green);
}
.coastal-sound {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}
.sound-settings {
    position: relative;
}
.sound-settings summary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 44px;
    min-height: 44px;
    padding: 10px 12px;
    font-size: 12px;
    cursor: pointer;
    color: var(--muted, #aebdb6);
    border: 1px solid #53695d;
    border-radius: 4px;
    background: #203330;
    list-style: none;
}
.sound-settings summary::-webkit-details-marker {
    display: none;
}
.sound-settings summary:hover,
.sound-settings[open] summary {
    color: #eae9d9;
    border-color: #79978c66;
    background: #ffffff08;
}
.sound-settings summary:focus-visible,
.sound-volume input:focus-visible,
.sound-preview:focus-visible {
    outline: 2px solid #b8d5b4;
    outline-offset: 4px;
}
.sound-settings-panel {
    position: absolute;
    z-index: 60;
    top: calc(100% + 10px);
    right: 0;
    width: min(270px, calc(100vw - 32px));
    max-height: min(640px, calc(100dvh - 130px));
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 20px;
    border: 1px solid #70867c;
    border-radius: 6px;
    background: #192b2b;
    color: #eae9d9;
    box-shadow: 0 14px 40px #06131480;
    text-align: left;
}
.sound-settings-panel .sound-control {
    width: 100%;
    min-height: 44px;
    margin-bottom: 16px;
    font-size: 13px;
}
.sound-settings-title {
    margin: 0 0 7px;
    font-family: 'Fraunces', Georgia, serif;
    font-size: 17px;
}
.sound-settings-note {
    margin: 0;
    color: #b5c5bc;
    font-size: 12px;
    line-height: 1.5;
}
.sound-volume {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 7px;
    margin: 19px 0;
    font-size: 12px;
}
.sound-volume output {
    color: #bfceb5;
    font-variant-numeric: tabular-nums;
}
.sound-volume input {
    grid-column: 1 / -1;
    width: 100%;
    min-height: 44px;
    accent-color: #b8d5b4;
    cursor: pointer;
}
.sound-preview {
    margin-top: 13px;
    min-height: 44px;
    padding: 7px 11px;
    border: 1px solid #789386;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
}
.sound-preview:hover {
    background: #ffffff0a;
}
.sound-issue {
    width: 235px;
    padding: 10px;
    border: 1px solid #789386;
    border-radius: 6px;
    background: #192b2b;
    color: #eae9d9;
    font-size: 12px;
}
@media (max-width: 900px) {
    .sound-settings[open] {
        width: min(280px, calc(100vw - 84px));
    }
    .sound-settings summary {
        width: fit-content;
        margin-left: auto;
    }
    .sound-settings-panel {
        position: static;
        width: 100%;
        margin-top: 8px;
        padding: 16px;
        box-shadow: none;
    }
}
</style>
