import type { RitualCosmeticEvent } from './ritualSceneState';

export const banishmentDetails = {
    gilded_vortex: {
        name: 'Gilded Vortex',
        description:
            'A golden tribunal lifts the condemned into a crown, then stamps their fate into a molten seal.',
        phases: [
            'Summon the tribunal',
            'Crown the condemned',
            'Seal their fate',
        ],
        duration: 4.8,
    },
    lunar_rift: {
        name: 'Lunar Rift',
        description:
            'An eclipse parts the night. The condemned slips between its crescents, leaving only a constellation.',
        phases: ['Part the eclipse', 'Cross the threshold', 'Close the night'],
        duration: 4.6,
    },
    ember_spiral: {
        name: 'Ember Spiral',
        description:
            'An autumn wreath becomes a twisting pyre, carrying the condemned away in a shower of leaves and embers.',
        phases: ['Kindle the wreath', 'Rise with the fire', 'Return to ash'],
        duration: 5,
    },
} as const;

export function banishmentDetail(effect: string) {
    return Object.hasOwn(banishmentDetails, effect)
        ? banishmentDetails[effect as keyof typeof banishmentDetails]
        : undefined;
}

/** Renderer and both event consumers share one clock, with a short quiet tail. */
export function cosmeticDuration(
    event: Pick<RitualCosmeticEvent, 'effect' | 'kind'>,
) {
    return event.kind === 'banishment'
        ? (banishmentDetail(event.effect)?.duration ?? 3.2)
        : 3.2;
}

export function cosmeticPlaybackMilliseconds(
    event: Pick<RitualCosmeticEvent, 'effect' | 'kind'>,
) {
    return Math.round(cosmeticDuration(event) * 1000) + 200;
}
