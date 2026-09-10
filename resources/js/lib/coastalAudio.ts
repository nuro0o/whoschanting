import type { RoomState } from './chanting';

export interface SoundPreferences {
    enabled: boolean;
    effects: number;
    ambience: number;
}

export const soundStorageKey = 'chanting-audio-v1';
export const defaultSoundPreferences: SoundPreferences = {
    enabled: false,
    effects: 45,
    ambience: 25,
};

export function parseSoundPreferences(
    stored: string | null,
    legacy: string | null = null,
): SoundPreferences {
    const fallback = { ...defaultSoundPreferences, enabled: legacy === 'on' };
    if (!stored) return fallback;
    try {
        const value: unknown = JSON.parse(stored);
        if (!value || typeof value !== 'object') return fallback;
        const data = value as Record<string, unknown>;
        const volume = (input: unknown, initial: number) =>
            typeof input === 'number' && Number.isFinite(input)
                ? Math.round(Math.max(0, Math.min(100, input)))
                : initial;
        return {
            enabled:
                typeof data.enabled === 'boolean'
                    ? data.enabled
                    : fallback.enabled,
            effects: volume(data.effects, fallback.effects),
            ambience: volume(data.ambience, fallback.ambience),
        };
    } catch {
        return fallback;
    }
}

// Only public state and local submission status enter the sound system.
export interface SoundSnapshot {
    phase: RoomState['phase'];
    phaseId: number;
    submitted: boolean;
    actionSerial: number;
    seconds: number | null;
    ritualTokens: number;
    winner: 'town' | 'cult' | null;
    alive: boolean;
    connected: boolean;
}

export type SoundCue =
    | 'night'
    | 'dawn'
    | 'vote'
    | 'reveal'
    | 'seal'
    | 'ritual'
    | 'town'
    | 'cult'
    | 'reminder';

export class SoundCueTracker {
    private previous: SoundSnapshot | null = null;
    private remindedPhase: number | null = null;

    update(next: SoundSnapshot, audible: boolean): SoundCue[] {
        const previous = this.previous;
        this.previous = { ...next };
        if (!previous) return [];
        const phaseChanged = next.phaseId !== previous.phaseId;
        if (phaseChanged) this.remindedPhase = null;
        const crossedDeadline =
            !phaseChanged &&
            previous.seconds !== null &&
            previous.seconds > 10 &&
            next.seconds !== null &&
            next.seconds <= 10 &&
            next.seconds > 0;
        const reminder = crossedDeadline && this.remindedPhase !== next.phaseId;
        // Consume even inaudible events, so enabling sound never plays history.
        if (crossedDeadline) this.remindedPhase = next.phaseId;
        if (!audible || !next.connected || !previous.connected) return [];

        const cues: SoundCue[] = [];
        if (next.actionSerial > previous.actionSerial) cues.push('seal');
        if (
            next.phase === 'finished' &&
            next.winner &&
            previous.winner !== next.winner
        ) {
            // Victory replaces the final ritual swell and ordinary phase cue.
            cues.push(next.winner);
            return cues;
        }
        if (next.ritualTokens > previous.ritualTokens) cues.push('ritual');
        else if (phaseChanged) {
            const phases: Partial<Record<SoundSnapshot['phase'], SoundCue>> = {
                night: 'night',
                discussion: 'dawn',
                voting: 'vote',
                reveal: 'reveal',
            };
            const cue = phases[next.phase];
            if (cue) cues.push(cue);
        }
        if (
            !cues.length &&
            reminder &&
            next.alive &&
            !next.submitted &&
            (next.phase === 'night' || next.phase === 'voting')
        )
            cues.push('reminder');
        return cues;
    }
}

/** Quiet procedural coastal audio. No recordings, private roles, or network requests. */
export class CoastalAudio {
    private context: AudioContext | null = null;
    private effects: GainNode | null = null;
    private ambience: GainNode | null = null;
    private seaFilter: BiquadFilterNode | null = null;
    private master: GainNode | null = null;
    private noise: AudioBuffer | null = null;
    private sources = new Set<() => void>();
    private ambientSources = new Set<() => void>();
    private creakTimer: ReturnType<typeof setTimeout> | null = null;
    private generation = 0;
    private playbackAllowed = false;
    private phase: SoundSnapshot['phase'] = 'lobby';
    private preferences = { ...defaultSoundPreferences };
    private onState: (active: boolean) => void;

    constructor(onState: (active: boolean) => void) {
        this.onState = onState;
    }

    get active(): boolean {
        return this.playbackAllowed && this.context?.state === 'running';
    }

    async enable(): Promise<boolean> {
        const generation = ++this.generation;
        try {
            if (!this.context || this.context.state === 'closed') {
                this.context = new AudioContext();
                this.master = this.context.createGain();
                this.master.gain.value = 0.5;
                this.master.connect(this.context.destination);
                this.effects = this.context.createGain();
                this.ambience = this.context.createGain();
                this.effects.connect(this.master);
                this.ambience.connect(this.master);
                this.noise = this.makeNoise(this.context);
                this.context.onstatechange = () => {
                    if (!this.active) this.stopSources();
                    this.onState(this.active);
                };
            }
            await this.context.resume();
            if (generation !== this.generation) return false;
            this.playbackAllowed = true;
            this.update(this.preferences, this.phase);
            this.onState(this.active);
            return this.active;
        } catch {
            this.close();
            return false;
        }
    }

    update(preferences: SoundPreferences, phase: SoundSnapshot['phase']): void {
        this.preferences = { ...preferences };
        this.phase = phase;
        if (!this.context || !this.active || !this.effects || !this.ambience)
            return;
        const now = this.context.currentTime;
        this.effects.gain.setTargetAtTime(preferences.effects / 100, now, 0.08);
        // Leave plenty of room for friends speaking around the table.
        const discussion = phase === 'discussion' ? 0.28 : 1;
        this.ambience.gain.setTargetAtTime(
            (preferences.ambience / 100) * discussion,
            now,
            0.5,
        );
        this.seaFilter?.frequency.setTargetAtTime(
            phase === 'night' ? 650 : 1050,
            now,
            1.2,
        );
        if (preferences.ambience === 0 || phase === 'finished')
            this.stopAmbience();
        else if (!this.ambientSources.size) this.startAmbience();
    }

    play(cues: SoundCue[]): void {
        if (!this.active || !this.context || this.preferences.effects === 0)
            return;
        cues.forEach((cue, index) =>
            this.cue(cue, this.context!.currentTime + index * 0.42),
        );
    }

    pause(): void {
        ++this.generation;
        this.playbackAllowed = false;
        this.stopSources();
        this.onState(false);
        void this.context?.suspend().catch(() => {});
    }

    close(): void {
        ++this.generation;
        this.playbackAllowed = false;
        this.stopSources();
        if (this.context) {
            this.context.onstatechange = null;
            void this.context.close().catch(() => {});
        }
        this.effects?.disconnect();
        this.ambience?.disconnect();
        this.master?.disconnect();
        this.context = null;
        this.effects = null;
        this.ambience = null;
        this.master = null;
        this.noise = null;
        this.onState(false);
    }

    private makeNoise(context: AudioContext): AudioBuffer {
        const buffer = context.createBuffer(
            1,
            context.sampleRate * 4,
            context.sampleRate,
        );
        const samples = buffer.getChannelData(0);
        let brown = 0;
        for (let i = 0; i < samples.length; i++) {
            brown = (brown + Math.random() * 0.04 - 0.02) / 1.02;
            samples[i] = brown * 3.5;
        }
        // Smooth both loop boundaries to zero to avoid a click every four seconds.
        const edge = Math.floor(context.sampleRate * 0.025);
        for (let i = 0; i < edge; i++) {
            const taper = (1 - Math.cos((Math.PI * i) / edge)) / 2;
            samples[i] *= taper;
            samples[samples.length - 1 - i] *= taper;
        }
        return buffer;
    }

    private track(
        source: AudioScheduledSourceNode,
        nodes: AudioNode[],
        ambient = false,
    ): void {
        const clean = () => {
            source.onended = null;
            try {
                source.stop();
            } catch {
                /* A finished source is already stopped. */
            }
            source.disconnect();
            nodes.forEach((node) => node.disconnect());
            this.sources.delete(clean);
            this.ambientSources.delete(clean);
        };
        this.sources.add(clean);
        if (ambient) this.ambientSources.add(clean);
        source.onended = clean;
    }

    private stopAmbience(): void {
        if (this.creakTimer !== null) clearTimeout(this.creakTimer);
        this.creakTimer = null;
        [...this.ambientSources].forEach((clean) => clean());
        this.seaFilter = null;
    }

    private stopSources(): void {
        this.stopAmbience();
        [...this.sources].forEach((clean) => clean());
    }

    private startAmbience(): void {
        const context = this.context!;
        const sea = context.createBufferSource();
        const filter = context.createBiquadFilter();
        this.seaFilter = filter;
        const gain = context.createGain();
        filter.type = 'lowpass';
        filter.frequency.value = this.phase === 'night' ? 650 : 1050;
        sea.buffer = this.noise;
        sea.loop = true;
        sea.connect(filter);
        filter.connect(gain);
        gain.connect(this.ambience!);
        gain.gain.setValueAtTime(0, context.currentTime);
        gain.gain.linearRampToValueAtTime(0.34, context.currentTime + 2);
        const tide = context.createOscillator();
        const depth = context.createGain();
        tide.frequency.value = 0.085;
        depth.gain.value = 0.12;
        tide.connect(depth);
        depth.connect(gain.gain);
        this.track(sea, [filter, gain], true);
        this.track(tide, [depth], true);
        sea.start();
        tide.start();
        this.scheduleCreak();
    }

    private scheduleCreak(): void {
        this.creakTimer = setTimeout(
            () => {
                this.creakTimer = null;
                if (!this.active || !this.ambientSources.size) return;
                // Sparse and quieter during discussion; no player state influences ambience.
                this.tone(
                    115,
                    this.context!.currentTime,
                    0.65,
                    0.028,
                    'triangle',
                    this.ambience!,
                    86,
                    true,
                );
                this.scheduleCreak();
            },
            16000 + Math.random() * 12000,
        );
    }

    private tone(
        frequency: number,
        time: number,
        duration: number,
        level: number,
        type: OscillatorType = 'sine',
        destination = this.effects!,
        endFrequency = frequency,
        ambient = false,
    ): void {
        const oscillator = this.context!.createOscillator();
        const gain = this.context!.createGain();
        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, time);
        oscillator.frequency.exponentialRampToValueAtTime(
            endFrequency,
            time + duration,
        );
        gain.gain.setValueAtTime(0, time);
        gain.gain.linearRampToValueAtTime(
            level,
            time + Math.min(0.025, duration / 4),
        );
        gain.gain.exponentialRampToValueAtTime(0.0001, time + duration);
        oscillator.connect(gain);
        gain.connect(destination);
        this.track(oscillator, [gain], ambient);
        oscillator.start(time);
        oscillator.stop(time + duration + 0.03);
    }

    private breath(
        time: number,
        duration: number,
        level: number,
        frequency: number,
    ): void {
        const source = this.context!.createBufferSource();
        const filter = this.context!.createBiquadFilter();
        const gain = this.context!.createGain();
        source.buffer = this.noise;
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(frequency, time);
        filter.frequency.linearRampToValueAtTime(
            frequency * 0.6,
            time + duration,
        );
        filter.Q.value = 0.7;
        gain.gain.setValueAtTime(0, time);
        gain.gain.linearRampToValueAtTime(level, time + duration * 0.25);
        gain.gain.exponentialRampToValueAtTime(0.0001, time + duration);
        source.connect(filter);
        filter.connect(gain);
        gain.connect(this.effects!);
        this.track(source, [filter, gain]);
        source.start(time);
        source.stop(time + duration + 0.03);
    }

    private cue(cue: SoundCue, time: number): void {
        switch (cue) {
            case 'night':
                this.tone(220, time, 1.8, 0.11);
                this.tone(441, time, 1.3, 0.028);
                this.breath(time, 1.5, 0.3, 450);
                break;
            case 'dawn':
                this.tone(660, time, 0.9, 0.075);
                this.tone(880, time + 0.18, 0.8, 0.035);
                this.tone(
                    1400,
                    time + 0.5,
                    0.13,
                    0.018,
                    'sine',
                    this.effects!,
                    1900,
                );
                break;
            case 'vote':
                this.tone(180, time, 0.16, 0.16, 'triangle', this.effects!, 75);
                this.tone(
                    160,
                    time + 0.2,
                    0.13,
                    0.1,
                    'triangle',
                    this.effects!,
                    65,
                );
                break;
            case 'reveal':
                this.breath(time, 0.45, 0.8, 1800);
                break;
            case 'seal':
                this.breath(time, 0.18, 0.8, 1600);
                this.tone(
                    230,
                    time + 0.12,
                    0.12,
                    0.1,
                    'triangle',
                    this.effects!,
                    90,
                );
                break;
            case 'ritual':
                this.breath(time, 1.4, 1.1, 1300);
                this.tone(110, time + 0.15, 1.2, 0.06);
                break;
            case 'town':
                [330, 440, 550, 660].forEach((note, i) =>
                    this.tone(note, time + i * 0.18, 1.5, 0.065),
                );
                break;
            case 'cult':
                [220, 165, 110].forEach((note, i) =>
                    this.tone(note, time + i * 0.22, 1.8, 0.075),
                );
                this.breath(time, 2, 0.6, 700);
                break;
            case 'reminder':
                this.tone(560, time, 0.55, 0.055);
                break;
        }
    }
}
