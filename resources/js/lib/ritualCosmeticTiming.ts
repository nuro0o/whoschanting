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

export const celebrationDetails = {
    crownfall: {
        name: 'Crownfall',
        description:
            'Golden laurels unfurl as an ornate crown assembles above a jade medallion. A shower of coins celebrates the coronation.',
        phases: [
            'Unfurl the laurels',
            'Assemble the crown',
            'Celebrate the reign',
        ],
        duration: 5.6,
    },
    moonrise: {
        name: 'Moonrise',
        description:
            'A pearl moon rises from a silver pool. Lunar phases align in a celestial halo, returning starlight to the water.',
        phases: [
            'Awaken the water',
            'Align the heavens',
            'Shower the starlight',
        ],
        duration: 5.8,
    },
    lantern_festival: {
        name: 'Lantern Festival',
        description:
            'Paper lanterns kindle one by one and rise into a glowing canopy, with drifting autumn leaves and dancing fireflies.',
        phases: [
            'Kindle the wishes',
            'Release the lanterns',
            'Light the canopy',
        ],
        duration: 5.6,
    },
} as const;

export function celebrationDetail(effect: string) {
    return Object.hasOwn(celebrationDetails, effect)
        ? celebrationDetails[effect as keyof typeof celebrationDetails]
        : undefined;
}

/** Renderer and both event consumers share one clock, with a short quiet tail. */
export function cosmeticDuration(
    event: Pick<RitualCosmeticEvent, 'effect' | 'kind'>,
) {
    return event.kind === 'banishment'
        ? (banishmentDetail(event.effect)?.duration ?? 3.2)
        : event.kind === 'celebration'
          ? (celebrationDetail(event.effect)?.duration ?? 3.2)
          : 3.2;
}

export function cosmeticPlaybackMilliseconds(
    event: Pick<RitualCosmeticEvent, 'effect' | 'kind'>,
) {
    return Math.round(cosmeticDuration(event) * 1000) + 200;
}
