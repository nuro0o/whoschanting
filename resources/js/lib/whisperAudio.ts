/** Short recordings follow local disturbances, never a player's role or seat. */
export class WhisperAudio {
    private audio: HTMLAudioElement | null = null;
    private timer: ReturnType<typeof setInterval> | undefined;
    private seen = 0;
    private last = '';
    private failed = new Set<string>();
    private volume = 0;
    private disposed = false;
    private tracks: string[];
    private duration: number;
    private sustain: boolean;

    constructor(tracks: string[], duration = 3000, sustain = false) {
        this.tracks = [...new Set(tracks)];
        this.duration = duration;
        this.sustain = sustain;
    }

    update(eventId: number | null, active: boolean, volume: number): void {
        if (this.disposed) return;
        const fresh = eventId !== null && eventId > this.seen;
        if (eventId !== null) this.seen = Math.max(this.seen, eventId);
        this.volume = Number.isFinite(volume)
            ? (Math.max(0, Math.min(100, volume)) / 100) * 0.4
            : 0;
        if (!active || !this.volume || (eventId === null && !this.sustain)) {
            this.stop();
            return;
        }
        if (!fresh) return;
        this.stop();
        let available = this.tracks.filter((url) => !this.failed.has(url));
        if (available.length > 1)
            available = available.filter((url) => url !== this.last);
        if (!available.length) return;
        const url = available[Math.floor(Math.random() * available.length)];
        this.last = url;
        const audio = new Audio();
        this.audio = audio;
        audio.preload = 'none';
        audio.volume = 0;
        audio.src = url;
        let started: number | null = null;
        this.timer = setInterval(() => {
            if (started === null) return;
            const elapsed = Date.now() - started;
            if (elapsed >= this.duration) this.stop();
            else
                audio.volume =
                    this.volume *
                    Math.max(
                        0,
                        Math.min(
                            1,
                            elapsed / 350,
                            (this.duration - elapsed) / 650,
                        ),
                    );
        }, 50);
        audio.onended = () => {
            if (this.audio === audio) this.stop();
        };
        audio.onerror = () => {
            if (this.audio !== audio) return;
            this.failed.add(url);
            this.stop();
        };
        void audio
            .play()
            .then(() => {
                if (this.audio !== audio) audio.pause();
                else started = Date.now();
            })
            .catch((error: unknown) => {
                if (this.audio !== audio) return;
                if (
                    !(
                        error instanceof DOMException &&
                        ['NotAllowedError', 'AbortError'].includes(error.name)
                    )
                )
                    this.failed.add(url);
                this.stop();
            });
    }

    close(): void {
        this.disposed = true;
        this.stop();
    }

    private stop(): void {
        clearInterval(this.timer);
        this.timer = undefined;
        const audio = this.audio;
        this.audio = null;
        if (!audio) return;
        audio.onended = null;
        audio.onerror = null;
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
    }
}
