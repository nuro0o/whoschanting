import { disturbanceLevel } from './ritualDisturbances.ts';
import { WhisperAudio } from './whisperAudio.ts';

export interface WhisperLibrary {
    ambient: string[];
    oneoff: string[];
}

export interface WhisperContext {
    phase: string;
    phaseId: number;
    tokens: number;
    threshold: number;
    seconds: number | null;
    allowed: boolean;
    volume: number;
    eventId: number | null;
}

/** Quiet whisper swells and occasional separate scares, chosen locally. */
export class WhisperAtmosphere {
    private ambient: WhisperAudio;
    private oneoff: WhisperAudio;
    private nextAt = 0;
    private occupiedUntil = 0;
    private serial = 0;
    private seen = 0;
    private phaseId = -1;
    private level = 0;
    private disposed = false;
    private random: () => number;

    constructor(tracks: WhisperLibrary, random: () => number = Math.random) {
        this.ambient = new WhisperAudio(tracks.ambient, 12000, true);
        this.oneoff = new WhisperAudio(tracks.oneoff, 12000, true);
        this.random = random;
    }

    update(context: WhisperContext, now = Date.now()): void {
        if (this.disposed) return;
        const level = disturbanceLevel(context.tokens, context.threshold);
        const fresh = context.eventId !== null && context.eventId > this.seen;
        if (context.eventId !== null)
            this.seen = Math.max(this.seen, context.eventId);
        const allowed =
            context.allowed &&
            Number.isFinite(context.volume) &&
            context.volume > 0 &&
            level > 0 &&
            ['night', 'discussion', 'voting'].includes(context.phase) &&
            context.seconds !== null &&
            context.seconds > 10;
        const changed =
            this.phaseId !== context.phaseId || this.level !== level;
        this.phaseId = context.phaseId;
        this.level = level;
        if (!allowed || changed) {
            this.ambient.update(null, false, 0);
            this.oneoff.update(null, false, 0);
            this.nextAt = 0;
            this.occupiedUntil = 0;
            if (!allowed) return;
        }
        // Volume follows public ritual progress, never a role or target.
        const ambientVolume = context.volume * [0, 0.22, 0.35, 0.5][level];
        if (fresh) {
            this.ambient.update(null, false, 0);
            this.oneoff.update(context.eventId, true, context.volume);
            this.occupiedUntil = now + 12000;
            this.schedule(this.occupiedUntil);
            return;
        }
        this.oneoff.update(null, true, context.volume);
        this.ambient.update(null, now >= this.occupiedUntil, ambientVolume);
        if (!this.nextAt) {
            this.schedule(now);
            return;
        }
        if (now < this.nextAt || now < this.occupiedUntil) return;
        // Returning from sleep never replays a missed whisper.
        if (now - this.nextAt <= 2000) {
            this.oneoff.update(null, false, 0);
            this.ambient.update(++this.serial, true, ambientVolume);
        }
        this.schedule(now + 12000);
    }

    close(): void {
        this.disposed = true;
        this.ambient.close();
        this.oneoff.close();
    }

    private schedule(now: number): void {
        const [minimum, spread] =
            this.level === 3
                ? [16, 12]
                : this.level === 2
                  ? [25, 15]
                  : [35, 20];
        this.nextAt = now + (minimum + this.random() * spread) * 1000;
    }
}
