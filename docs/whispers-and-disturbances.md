# Whispers and ritual disturbances

The browser adds occasional decorative disturbances after the public ritual reaches 20%: peripheral shadows and whispered phrases first, then strange symbols, impossible text and brief chat distortions from 50%. Frequency increases again at 80%. Each client rolls independently, so players can experience different things at the same table. No role, allegiance, target, player name or message is used to choose an effect.

Visual events last at most 3.2 seconds (chat distortion lasts 0.9 seconds). They never write to chat history, change messages, reveal roles, affect votes or intercept clicks. They pause during typing, blocking curses, private-role/help views, disconnection, background tabs, and the final ten seconds of a phase. Lobby, reveal, finished matches and banished spectators stay quiet. Reduced motion uses static symbols and phrases instead of shadows or animated chat distortion.

## Add whisper recordings

Put regular whisper recordings directly in `public/assets/Sounds/Whispers/`. They become quiet background swells as public ritual progress increases. Put occasional creepy sounds in `public/assets/Sounds/Whispers/oneoff/`; these play when the local disturbance director randomly selects a whisper event. The pools stay separate, and a one-off interrupts any background whisper.

Original filenames are supported. MP3, OGG, WAV, M4A, AAC and Opus filenames are recognized; browser codec support varies. Preserve the folder capitalization on case-sensitive servers.

The server discovers recordings each time the room page loads. Refresh after adding files; adding recordings needs no rebuild or `.env` change. An empty folder is silent and causes no missing-file requests. The visual disturbances work without recordings.

Whisper recordings play for up to twelve seconds, with a short fade in and a fade out at the limit. Shorter recordings end naturally. Audio can continue after its decorative text fades, but stops immediately when sound or atmosphere is disabled, the phase changes, a text field is focused, or the scene becomes ineligible. Prepare clips with audible content near the start. Different consecutive recordings are chosen when available.

Background swells wait 35–55 seconds at 20% ritual, 25–40 seconds at 50%, and 16–28 seconds at 80%. A twelve-second playback allowance is added before the next gap. Background volume rises gently with those levels and stays below the one-off volume. One-offs use the visual director's separate random timing and postpone the next background swell. All timing is local to the player and independent of roles.

Whispers use their own saved volume slider in **Sound and atmosphere settings** and respect the main mute control. **Unsettling atmosphere** disables these visuals and whisper events together; ordinary music, effects and role curses keep their existing settings.

## Sources to audition

- [Ghostly Whispering by qubodup on Freesound](https://freesound.org/people/qubodup/sounds/194628/): processed whispering voices; the page lists CC0. Download requires a Freesound login.
- [Whisper #2 on BigSoundBank](https://bigsoundbank.com/whisper-2-s3256.html): a quiet, close whisper; the page lists CC0 and offers downloads without an account.
- [Pixabay whisper sound effects](https://pixabay.com/sound-effects/search/whisper/): a broader collection to browse and audition; check the selected recording's license.

The recordings already placed in both folders are discovered automatically. Keep the chosen source and license alongside your project credits when you add audio.
