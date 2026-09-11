export interface DisturbanceContext {
    phase: string;
    phaseId: number;
    tokens: number;
    threshold: number;
    seconds: number | null;
    allowed: boolean;
    reducedMotion: boolean;
}

export interface RitualDisturbance {
    id: number;
    kind: 'shadow' | 'glyphs' | 'echo' | 'chat' | 'whisper';
    side: 'left' | 'right';
    top: number;
    text: string;
    expiresAt: number;
}

export function disturbanceLevel(tokens: number, threshold: number): number {
    if (
        !Number.isFinite(tokens) ||
        !Number.isFinite(threshold) ||
        threshold <= 0
    )
        return 0;
    const progress = tokens / threshold;
    return progress >= 0.8 ? 3 : progress >= 0.5 ? 2 : progress >= 0.2 ? 1 : 0;
}

const phrases = [
    'the door was never there',
    'you have been here before',
    'the sea remembers',
    'there is no room below this one',
    'something is counting backwards',
    'the walls are listening',
];

/** Each browser rolls its own atmosphere using only public ritual progress. */
export class RitualDisturbanceDirector {
    private nextAt = 0;
    private phaseId = -1;
    private level = 0;
    private serial = 0;
    private previousKind: RitualDisturbance['kind'] | null = null;
    private current: RitualDisturbance | null = null;
    private reducedMotion = false;
    private random: () => number;

    constructor(random: () => number = Math.random) {
        this.random = random;
    }

    update(context: DisturbanceContext, now: number): RitualDisturbance | null {
        const level = disturbanceLevel(context.tokens, context.threshold);
        if (
            !context.allowed ||
            !level ||
            !['night', 'discussion', 'voting'].includes(context.phase) ||
            context.seconds === null ||
            context.seconds <= 10
        ) {
            this.nextAt = 0;
            this.current = null;
            return null;
        }
        if (
            context.phaseId !== this.phaseId ||
            level !== this.level ||
            context.reducedMotion !== this.reducedMotion ||
            !this.nextAt
        ) {
            this.phaseId = context.phaseId;
            this.level = level;
            this.reducedMotion = context.reducedMotion;
            this.current = null;
            this.schedule(now);
            return null;
        }
        if (this.current && now < this.current.expiresAt) return this.current;
        this.current = null;
        if (now < this.nextAt) return null;
        // A delayed/background callback never replays an old scare.
        if (now - this.nextAt > 2000) {
            this.schedule(now);
            return null;
        }
        const pool: RitualDisturbance['kind'][] = context.reducedMotion
            ? ['glyphs', 'echo', 'whisper']
            : level === 1
              ? ['shadow', 'whisper']
              : ['shadow', 'glyphs', 'echo', 'chat', 'whisper'];
        const choices = pool.filter((kind) => kind !== this.previousKind);
        const kind = choices[Math.floor(this.random() * choices.length)];
        this.previousKind = kind;
        this.current = {
            id: ++this.serial,
            kind,
            side: this.random() < 0.5 ? 'left' : 'right',
            top: 22 + this.random() * 42,
            text: phrases[Math.floor(this.random() * phrases.length)],
            expiresAt: now + (kind === 'chat' ? 900 : 3200),
        };
        this.schedule(this.current.expiresAt);
        return this.current;
    }

    private schedule(now: number): void {
        const [minimum, spread] =
            this.level === 3 ? [10, 8] : this.level === 2 ? [18, 12] : [28, 17];
        this.nextAt = now + (minimum + this.random() * spread) * 1000;
    }
}
