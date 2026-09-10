<script setup lang="ts">
import { SlidersHorizontal, Volume2, VolumeX } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { PhaseMusic, type MusicLibrary } from '@/lib/phaseMusic';
import {
    CoastalAudio,
    defaultSoundPreferences,
    parseSoundPreferences,
    SoundCueTracker,
    soundStorageKey,
    type SoundSnapshot,
} from '@/lib/coastalAudio';

const props = defineProps<SoundSnapshot & { music: MusicLibrary }>();
const preferences = ref({ ...defaultSoundPreferences });
const active = ref(false);
const busy = ref(false);
const issue = ref('');
const musicIssue = ref('');
const settings = ref<HTMLDetailsElement>();
const controlId = useId();
const tracker = new SoundCueTracker();
let engine: CoastalAudio | null = null;
let music: PhaseMusic | null = null;
let activated = false;
let disposed = false;
const label = computed(() =>
    active.value
        ? 'Sound on'
        : preferences.value.enabled
          ? 'Enable sound'
          : 'Sound off',
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
    const cues = tracker.update({ ...props }, active.value && !document.hidden);
    engine?.update(preferences.value, props.phase);
    engine?.play(cues);
    syncMusic();
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
    engine.update(preferences.value, props.phase);
    // Start media within the user's gesture, before awaiting AudioContext.resume().
    syncMusic(true);
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
        if (!document.hidden)
            issue.value = 'Sound could not start. Tap to try again.';
    }
}
function toggle() {
    if (active.value) {
        preferences.value.enabled = false;
        activated = false;
        engine?.close();
        save();
    } else void enable();
}
function visibilityChanged() {
    tracker.update({ ...props }, false);
    if (document.hidden) engine?.pause();
    else if (activated && preferences.value.enabled) void enable();
}
function closeSettings(event: Event) {
    if (settings.value && !settings.value.contains(event.target as Node))
        settings.value.open = false;
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
        /* Sound starts off when preferences cannot be read. */
    }
    engine = new CoastalAudio((running) => {
        active.value = running && !document.hidden;
        syncMusic(running);
    });
    music = new PhaseMusic(props.music, (message) => {
        musicIssue.value = message;
    });
    tracker.update({ ...props }, false);
    document.addEventListener('visibilitychange', visibilityChanged);
    document.addEventListener('pointerdown', closeSettings);
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
    },
    { deep: true },
);
onBeforeUnmount(() => {
    disposed = true;
    document.removeEventListener('visibilitychange', visibilityChanged);
    document.removeEventListener('pointerdown', closeSettings);
    engine?.close();
    music?.close();
    music = null;
    engine = null;
});
</script>

<template>
    <div class="coastal-sound">
        <button
            class="sound-control"
            :aria-pressed="active"
            :disabled="busy"
            @click="toggle"
        >
            <Volume2 v-if="active" :size="15" aria-hidden="true" />
            <VolumeX v-else :size="15" aria-hidden="true" />
            {{ busy ? 'Starting sound...' : label }}
        </button>
        <details
            ref="settings"
            class="sound-settings"
            @keydown.esc.stop.prevent="escapeSettings"
        >
            <summary aria-label="Sound settings" title="Sound settings">
                <SlidersHorizontal :size="15" aria-hidden="true" />
            </summary>
            <div class="sound-settings-panel">
                <p class="sound-settings-title">Sounds of the village</p>
                <p class="sound-settings-note">
                    Music, quiet waves, and distant bells.
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
                <button
                    v-if="active"
                    class="sound-preview"
                    @click="engine?.play(['seal'])"
                >
                    Preview effects
                </button>
                <p v-else class="sound-settings-note">
                    Tap the sound button to listen.
                </p>
            </div>
        </details>
        <span v-if="issue || musicIssue" class="sound-issue" role="status">{{
            issue || musicIssue
        }}</span>
    </div>
</template>

<style scoped>
.coastal-sound {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.sound-settings {
    position: relative;
}
.sound-settings summary {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    cursor: pointer;
    color: var(--muted, #aebdb6);
    border: 1px solid transparent;
    border-radius: 8px;
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
    padding: 20px;
    border: 1px solid #70867c;
    border-radius: 12px;
    background: #192b2b;
    color: #eae9d9;
    box-shadow: 0 14px 40px #06131480;
    text-align: left;
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
    height: 20px;
    accent-color: #b8d5b4;
    cursor: pointer;
}
.sound-preview {
    margin-top: 13px;
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
    position: absolute;
    right: 0;
    top: 100%;
    z-index: 61;
    width: 235px;
    padding: 10px;
    border: 1px solid #789386;
    border-radius: 6px;
    background: #192b2b;
    color: #eae9d9;
    font-size: 12px;
}
</style>
