# Pack victories and sound brief

Preview the upgraded celebrations from **Profile → Store → pack preview → Preview victory**, or the wardrobe. Each uses the same production in preview and at the live table, with shared timing and an intentional static composition for reduced motion. Recordings and audio playback are not included in this visual upgrade.

## Sound identities

| Pack / celebration | Visual identity | Sound layers to add | Useful search terms |
| --- | --- | --- | --- |
| Founder's Pack / Crownfall | Unfurling laurels, an assembling crown, coronation and gold shower | Warm bronze resonance, small metal clicks as fragments meet, one rounded coronation bell, light falling coin tinkles | `bronze bell single`, `metal pieces assemble`, `ceremonial bell`, `coin scatter`, `short regal flourish` |
| Moonlit Coven / Moonrise | A moon rising from a silver pool, aligning celestial phases, pearl starlight | Soft water bloom, ascending harp or glass harmonics, a clear sustained reveal tone, delicate falling chimes | `water ripple gentle`, `harp glissando ascending`, `glass harmonics`, `celestial chimes`, `shimmer decay` |
| Harvest Festival / Lantern Festival | A wave of lit paper lanterns rising into a warm canopy with leaves and fireflies | Quiet paper unfolding, small wick ignitions, a soft lifting breeze, warm wooden chimes, leaf rustle | `paper rustle`, `candle ignition`, `gentle airy whoosh`, `wooden wind chimes`, `dry leaves rustle` |

Aim for **ceremonial triumph**, **celestial wonder**, and **shared warmth**. Keep the cues intimate enough for players talking after the match. An ascending gesture or resolved tone will feel celebratory without needing a full music bed. Avoid using the banishments' seal stamp, collapsing void or burning pyre as the victory's main sound.

## Cue sheet

All times are seconds from actual celebration playback, after the final banishment's queue slot. These are sound-editing targets, not additional runtime timers. Deliver a single mixed clip per effect.

| Effect | Opening and buildup | Reveal / climax | Tail and total |
| --- | --- | --- | --- |
| Crownfall | 0–1.2: bronze resonance as the dais and laurels appear; 0.7–2.3: little metal clicks gather into a crown | 2.6–3.15: soft downward sweep, resolving into a rounded coronation bell when the crown settles | 3.15–4.8: light coin shower; fade completely by **5.6 s** |
| Moonrise | 0–0.8: water bloom; 0.5–2.6: ascending harp/glass swell follows the rising moon | 1.7–3.2: phase tones align into one clear, sustained reveal | 3.2–5.0: sparse pearl chimes; decay completely by **5.8 s** |
| Lantern Festival | 0.15–1.45: stagger quiet wick ignitions and paper rustles; 1.2–3.0: introduce the lifting breeze | 3.0–4.4: a warm wooden chime phrase opens with the canopy | 3.0–4.9: leaf/firefly detail; fade completely by **5.6 s** |

## Specific clips to audition

These candidates were checked against their source pages; they have not been auditioned or downloaded. They are raw layers to edit, not finished synchronized victory effects.

- **Moonrise:** [Harp Glissando — everythingsounds](https://freesound.org/people/everythingsounds/sounds/594961/). An 8.362-second harp recording with sustain, listed as **CC BY 4.0**. Try an ascending passage for the moon's lift, then trim or reshape its tail to fit 5.8 seconds. Credit the creator and indicate edits.
- **Lantern Festival:** [Paper Rustle — BenjaminNelan](https://freesound.org/people/BenjaminNelan/sounds/353125/). A 1.129-second stereo paper movement, listed as **CC0**. Use quiet, slightly varied snippets for unfolding lanterns.
- **Lantern Festival:** [Wind Chimes 02.wav — Adelantemos](https://freesound.org/people/Adelantemos/sounds/222893/). A longer recording described as deeper pipes struck by a soft wooden hammer, listed as **CC0**. Audition a short, sparse phrase for the canopy opening; keep it below the ignition and reveal cues.
- **Crownfall:** use the metallic/coin search terms above in [Sonniss GameAudioGDC](https://gdc.sonniss.com/gdc-game-audio-bundle/) or [Freesound](https://freesound.org/). Choose a rounded bell and delicate metal detail rather than a loud cash-register effect. No specific Crownfall clip has been selected.

The [Freesound licensing FAQ](https://freesound.org/help/faq/#licenses) explains attribution and commercial-use categories; the [Sonniss bundle license](https://sonniss.com/gdc-bundle-license/) applies to its downloads. Keep source URLs, creator names, license details and download dates with your mix project, as described in the [banishment sound guide](banishment-sounds.md).

## Briefs for original recordings or commissioned sound design

- **Crownfall:** “A 5.6-second miniature royal coronation. Warm antique bronze, precisely assembling metal fragments, one satisfying rounded bell at 3.15 seconds, a delicate rain of gold coins, clean fade. Proud and ceremonial. No voices, cash register or orchestral music bed.”
- **Moonrise:** “A 5.8-second celestial revelation. A silver pool ripples, an ascending harp and glass harmonic gesture lifts a moon, scattered tones align by 3.2 seconds, pearl-like chimes fall softly into silence. Serene wonder. No dark portal, suction or explosive impact.”
- **Lantern Festival:** “A 5.6-second warm paper-lantern release. Quiet paper folds and staggered wick ignitions, a lifting breeze, a gentle wooden-chime phrase as the canopy opens at 3 seconds, leaves settling into silence. Intimate and communal. No roaring fire or crowd voices.”

## Delivery and later integration

Suggested future runtime filenames (placing files here alone does not enable playback):

```text
public/assets/audio/victories/crownfall.mp3
public/assets/audio/victories/moonrise.mp3
public/assets/audio/victories/lantern-festival.mp3
```

Keep 48 kHz WAV masters outside the runtime bundle and export compact MP3 files without leading silence. Fit the entire tail inside each duration. Start around −6 dBFS peak, then balance perceived loudness across the three. Check phone speakers and mono playback.

When recordings are added, use existing Effects/master preferences, audio unlock, mute and background suspension. Start sound when the queued celebration becomes active, not when a poll receives it. Reconcile it with the existing procedural Town/Cult victory cue so two victory stingers do not overlap. Stop on replacement, room change or unmount; tolerate absent files. Preview playback should follow the Preview victory click and saved audio preferences. `ritualCosmeticTiming.ts` is the duration source for visuals, previews and queue slots.
