# Pack banishments and sound brief

Each paid banishment has its own choreography in the shared store-preview/live-table renderer. Preview them from **Profile → Store → pack preview → Preview banishment**, or from the wardrobe. Sound files are recommendations to source next: this upgrade does not download recordings or add playback.

## Three sound identities

| Pack / effect | Visual identity | Recommended sound layers | Search terms |
| --- | --- | --- | --- |
| Founder's Pack / Gilded Vortex | Golden tribunal, orbiting armillary cage, suspended crown, sealing impact | Low bronze bell, restrained mechanical ratchet, swirling coins, one heavy metal seal, descending chimes | `bronze bell single hit`, `clockwork winding`, `coins spinning`, `metal stamp impact`, `descending chimes` |
| Moonlit Coven / Lunar Rift | Vertical eclipse doorway, split crescents, sideways disappearance, closing slit | Reversed wine-glass tone, bowed glass, airy inward suction, tiny crystalline closure, distant shimmer | `reverse glass swell`, `bowed glass`, `magic portal suction`, `crystal chime`, `ethereal shimmer` |
| Harvest Festival / Ember Spiral | Leaf ring ignition, twisting fire ribbons, rising pawn, ember scatter and falling ash | Dry leaves gathering, soft fire whoosh, low wood crackle, ember ticks, settling leaf rustle | `dry leaves swirl`, `fire ignition whoosh`, `small fire crackle`, `embers`, `leaves falling` |

The goal is three recognizable signatures: **weight and metal**, **absence and glass**, **warmth and organic movement**. Favor designed foley over a shared cinematic boom. Avoid voices, screams, dramatic music beds, or sharp repeated impacts that compete with friends talking.

## Cue sheet

Seconds are measured from the start of the effect. These are editing targets tied to the visual choreography, not separately scheduled audio events. Render one complete file for each effect so internal layers remain synchronized.

| Effect | Opening | Main motion | Climax | Tail / total |
| --- | --- | --- | --- | --- |
| Gilded Vortex | 0–0.9: muted bronze strike and ratchet as seals assemble | 0.9–2.6: accelerate a restrained coin orbit with the lift | Around 3.25: one heavy seal stamp; briefly thin the sound just before it | Descending gold chimes decay fully by **4.8 s** |
| Lunar Rift | 0–1.1: reversed glass rises as the doorway opens | 1.1–2.9: hollow suction follows the inward pull | 2.9–3.55: narrow the swell into a short crystalline closure | Sparse airy shimmer disappears by **4.6 s** |
| Ember Spiral | 0–1.0: dry leaves sweep together, then a soft ignition | 1.0–2.7: rising fire whoosh with a little wood crackle | Around 3.0: a soft outward breath and scattered ember ticks | Falling ash/leaf rustle fades fully by **5.0 s** |

## Where to look

- **[Sonniss GameAudioGDC bundles](https://gdc.sonniss.com/gdc-game-audio-bundle/)**: useful raw material for layered game effects. Its [bundle license](https://sonniss.com/gdc-bundle-license/) permits commercial project use and modification. Keep the applicable license notice with your sourced assets; do not redistribute the source library as a sound pack.
- **[Freesound](https://freesound.org/)**: search for the individual foley layers above. Filter for **CC0** for the simplest reuse, or **CC BY** with the required creator credit. Avoid noncommercial-only clips for this paid game. The [Freesound licensing FAQ](https://freesound.org/help/faq/#licenses) explains the differences.

These are sourcing recommendations, not claims that particular recordings have been auditioned. Save each selected clip's title, creator, original URL, license and download date alongside the mix project.

## Recording or commissioning your own

- **Gilded Vortex:** “A 4.8-second miniature occult tribunal. Muted bronze bell, precise clockwork assembly, accelerating gold coin orbit, a single weighty sealing stamp at 3.25 seconds, descending delicate chimes. Antique and ceremonial. No music or voices.”
- **Lunar Rift:** “A 4.6-second eclipse portal closing around a miniature figure. Reversed resonant glass opens into a hollow airy void, inward suction builds, a fine crystalline slit closes between 2.9 and 3.55 seconds, distant starlight decays. Intimate and uncanny. No explosion, music or voices.”
- **Ember Spiral:** “A 5-second magical autumn pyre. Dry leaves gather, a soft flame ignites, a warm twisting whoosh rises with gentle crackle, embers scatter at 3 seconds, ash and leaves settle into silence. Organic and warm. No roaring inferno, music or voices.”

## Delivery and later integration

Suggested future runtime files (not loaded automatically):

```text
public/assets/audio/banishments/gilded-vortex.mp3
public/assets/audio/banishments/lunar-rift.mp3
public/assets/audio/banishments/ember-spiral.mp3
```

Keep a 48 kHz WAV master outside the runtime bundle; export compact MP3 files with no leading silence and the complete tail inside the stated duration. Start with peaks at about −6 dBFS and match perceived volume across all three. Keep low-frequency weight audible on phone speakers and stereo movement subtle enough to survive mono playback.

When clips are ready, route them through the existing Effects/master preferences and audio-unlock lifecycle in `coastalAudio.ts` / `SoundControl.vue`. Trigger a clip when its cosmetic starts in the table queue, not when a poll first receives the event; the final banishment must finish before the victory cue. Stop playback on mute, room change and component disposal. Missing files should leave the visuals working. Preview audio should require the preview click and honor saved sound settings. Use the shared cosmetic duration metadata as the timing source if choreography changes.
