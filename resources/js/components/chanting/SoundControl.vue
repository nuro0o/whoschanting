<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Volume2, VolumeX } from '@lucide/vue';
const props = defineProps<{
    phase: string;
    phaseId: number;
    submitted: boolean;
}>();
const enabled = ref(false);
const activated = ref(false);
let context: AudioContext | undefined;
let disposed = false;
function sound(kind: 'phase' | 'seal') {
    if (
        !enabled.value ||
        !activated.value ||
        !context ||
        context.state !== 'running' ||
        document.hidden
    )
        return;
    const time = context.currentTime;
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = kind === 'seal' ? 'triangle' : 'sine';
    const frequency =
        kind === 'seal' ? 360 : props.phase === 'night' ? 174 : 523;
    oscillator.frequency.setValueAtTime(frequency, time);
    oscillator.frequency.exponentialRampToValueAtTime(
        frequency * (kind === 'seal' ? 0.5 : 1.5),
        time + 0.25,
    );
    gain.gain.setValueAtTime(0, time);
    gain.gain.linearRampToValueAtTime(0.055, time + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.001, time + 0.55);
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start(time);
    oscillator.stop(time + 0.6);
    oscillator.onended = () => {
        oscillator.disconnect();
        gain.disconnect();
    };
    if (kind === 'seal') {
        const buffer = context.createBuffer(
            1,
            Math.floor(context.sampleRate * 0.18),
            context.sampleRate,
        );
        const samples = buffer.getChannelData(0);
        for (let i = 0; i < samples.length; i++)
            samples[i] = (Math.random() * 2 - 1) * (1 - i / samples.length);
        const paper = context.createBufferSource();
        const filter = context.createBiquadFilter();
        const paperGain = context.createGain();
        filter.type = 'lowpass';
        filter.frequency.value = 1800;
        paperGain.gain.value = 0.055;
        paper.buffer = buffer;
        paper.connect(filter);
        filter.connect(paperGain);
        paperGain.connect(context.destination);
        paper.start(time);
        paper.onended = () => {
            paper.disconnect();
            filter.disconnect();
            paperGain.disconnect();
        };
    }
}
async function toggle() {
    if (enabled.value && activated.value) {
        enabled.value = false;
    } else {
        try {
            context ??= new AudioContext();
            await context.resume();
            if (disposed) return;
            enabled.value = true;
            activated.value = true;
            sound('phase');
        } catch {
            enabled.value = false;
        }
    }
    try {
        localStorage.setItem('chanting-sound', enabled.value ? 'on' : 'off');
    } catch {
        /* Optional preference storage. */
    }
}
onMounted(() => {
    try {
        enabled.value = localStorage.getItem('chanting-sound') === 'on';
    } catch {
        /* Sound remains off. */
    }
});
watch(
    () => props.phaseId,
    () => sound('phase'),
);
watch(
    () => props.submitted,
    (next, previous) => {
        if (next && !previous) sound('seal');
    },
);
onBeforeUnmount(() => {
    disposed = true;
    void context?.close().catch(() => {});
});
</script>
<template>
    <button
        class="sound-control"
        :aria-pressed="enabled && activated"
        @click="toggle"
    >
        <Volume2 v-if="enabled && activated" :size="15" /><VolumeX
            v-else
            :size="15"
        />
        {{
            enabled && activated
                ? 'Sound on'
                : enabled
                  ? 'Enable sound'
                  : 'Sound off'
        }}
    </button>
</template>
