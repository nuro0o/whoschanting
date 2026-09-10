import type { RoomState } from './chanting';

export interface MusicLibrary {
    day: string[];
    night: string[];
}
type Period = keyof MusicLibrary;

/** One recording at a time, with separate shuffle bags for day and night. */
export class PhaseMusic {
    private audio: HTMLAudioElement | null = null;
    private period: Period | null = null;
    private bags: MusicLibrary = { day: [], night: [] };
    private last: Partial<Record<Period, string>> = {};
    private failed = new Set<string>();
    private allowed = false;
    private blocked = false;
    private disposed = false;
    private volume = 0;
    private tracks: MusicLibrary;
    private onIssue: (message: string) => void;

    constructor(tracks: MusicLibrary, onIssue: (message: string) => void) {
        this.tracks = {
            day: [...new Set(tracks.day)],
            night: [...new Set(tracks.night)],
        };
        this.onIssue = onIssue;
    }

    update(phase: RoomState['phase'], volume: number, active: boolean): void {
        if (this.disposed) return;
        const period =
            phase === 'finished' ? null : phase === 'night' ? 'night' : 'day';
        const allowed = active && volume > 0 && period !== null;
        if (allowed && !this.allowed) this.blocked = false;
        this.allowed = allowed;
        this.volume =
            Math.max(0, Math.min(1, volume / 100)) *
            (phase === 'discussion' ? 0.35 : 1);
        if (period !== this.period) {
            this.release();
            this.period = period;
        }
        if (!allowed) {
            this.audio?.pause();
            return;
        }
        if (this.audio) this.audio.volume = this.volume;
        if (this.blocked) return;
        if (!this.audio || this.audio.ended) this.next();
        else if (this.audio.paused) this.play(this.audio);
    }

    close(): void {
        this.disposed = true;
        this.allowed = false;
        this.release();
    }

    private next(): void {
        this.release();
        if (!this.allowed || !this.period) return;
        const period = this.period;
        const available = this.tracks[period].filter(
            (url) => !this.failed.has(url),
        );
        if (!available.length) {
            if (this.tracks[period].length)
                this.onIssue(
                    'Music is unavailable. Effects and ambience are still available.',
                );
            return;
        }
        let bag = this.bags[period].filter((url) => !this.failed.has(url));
        if (!bag.length) {
            bag = [...available];
            for (let i = bag.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [bag[i], bag[j]] = [bag[j], bag[i]];
            }
            // Avoid a repeat across shuffle cycles (a single track repeats normally).
            if (bag.length > 1 && bag[0] === this.last[period]) {
                [bag[0], bag[1]] = [bag[1], bag[0]];
            }
        }
        const url = bag.shift()!;
        this.bags[period] = bag;
        this.last[period] = url;
        const audio = new Audio();
        this.audio = audio;
        audio.preload = 'none';
        audio.volume = this.volume;
        audio.onended = () => {
            if (this.audio === audio) this.next();
        };
        audio.onerror = () => this.fail(audio, url);
        audio.src = url;
        this.play(audio);
    }

    private play(audio: HTMLAudioElement): void {
        void audio
            .play()
            .then(() => {
                if (this.audio === audio && this.allowed) this.onIssue('');
            })
            .catch((error: unknown) => {
                if (this.audio !== audio || !this.allowed) return;
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                )
                    return;
                if (
                    error instanceof DOMException &&
                    error.name === 'NotAllowedError'
                ) {
                    this.blocked = true;
                    this.onIssue(
                        'Music could not start. Turn sound off and on to try again.',
                    );
                    return;
                }
                this.fail(audio, audio.getAttribute('src') ?? '');
            });
    }

    private fail(audio: HTMLAudioElement, url: string): void {
        if (this.audio !== audio) return;
        this.failed.add(url);
        this.next();
    }

    private release(): void {
        if (!this.audio) return;
        const audio = this.audio;
        this.audio = null;
        audio.onended = null;
        audio.onerror = null;
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
    }
}
