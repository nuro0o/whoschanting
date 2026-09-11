# Who’s Chanting?

A private-room social deduction game built with Laravel 13, Vue 3, TypeScript, Reverb + Echo, MySQL, and a responsive village card table. No account is required: guests use an encrypted browser session, while verified accounts can keep their seat and progression across devices.

## Run locally

Requirements: PHP 8.3+, Composer, Node 22.12+ (or 24+), and MySQL 8. Docker is optional.

1. Run `composer install` and `npm install`.
2. Copy `.env.example` to `.env` if it does not already exist, then run `php artisan key:generate`.
3. Use your MySQL server or `docker compose up -d mysql`. The supplied **local-only** container exposes port **3307**, database `whoschanting`, user `chanting`, password `chanting-local`. Set those values and `DB_CONNECTION=mysql` in `.env`. Production needs separate credentials.
4. Set `REVERB_APP_SECRET` to a random secret (for example generate one with `php -r "echo bin2hex(random_bytes(32));"`). Keep this server-side; only `REVERB_APP_KEY` belongs in `VITE_*` variables.
5. Run `php artisan migrate`, then `npm run build` (or keep `npm run dev` running during frontend development).
6. Run these four processes in separate terminals:

```sh
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work --sleep=1 --tries=3
php artisan schedule:work
```

Open <http://127.0.0.1:8000>. Create a room, send its code or invite link to friends, and have everyone mark themselves ready. Use separate browsers/profiles to test multiple seats; tabs in one browser deliberately share a seat. Reopening the room in the same browser recovers it. Guest seats require the original session; clearing cookies, session expiry, or changing browsers loses access. Verified account seats can be recovered by signing in again. The template keeps active sessions for seven days.

For phones on a local network, serve on `0.0.0.0`, set `APP_URL` and the Reverb/Vite host to the machine’s LAN address, add that hostname to `REVERB_ALLOWED_ORIGINS`, and rebuild. Friends must reach both HTTP and WebSocket ports. Do not expose development servers to the internet.

## Account progression

Verified accounts have a **Progression** page at `/progression`, linked from the dashboard and account navigation. It includes lifetime XP and levels, quarterly seasons, eight permanent achievements, recent match rewards, and a wardrobe for titles, portrait frames, accent colors and character preferences. Cosmetics never change roles or abilities. Levels and equipped cosmetics appear in rooms; wardrobe changes apply when joining a lobby or starting a match.

Rewards are granted by the server when a complete match is archived. A player must have submitted at least one night action and one vote (explicit abstention counts). Eligible players earn 80 XP, plus 40 for winning and 20 for submitting every night action and vote available while alive. Eliminated players can still qualify. Guests, unverified accounts, incomplete archives and inactive seats earn no XP. Each account earns once per match, including across reconnects or retries. Matches completed before this feature do not receive retroactive rewards.

Lifetime level thresholds are 0, 250, 750, 1,500 and 2,500 XP for levels 1 through 5, with increasing requirements thereafter. Seasons follow UTC calendar quarters and use the match completion time. A new quarter starts a fresh season record automatically; lifetime XP, achievements, historical season records and earned cosmetics remain. Season tiers begin at 0, 250, 750, 1,500 and 3,000 XP. Rewards and unlock requirements can be adjusted in `config/progression.php`.

Creating or joining a lobby while verified links the seat to the account. The same account recovers that seat across browsers and cannot occupy a second seat in the same room. Existing guest seats can be linked by explicitly rejoining the lobby after signing in; a match already underway cannot be claimed. Account character preferences persist across sessions and devices. Guest seat recovery still depends on the original encrypted browser session.

Run `php artisan migrate` on existing installations to add the profile, season and match reward tables, then rebuild the frontend. No extra scheduled job is needed for season rollover. Account deletion also deletes that account's progression and reward records.

## Characters and presentation

Accounts must verify their email before entering the game or choosing a character. Guests can still play without registering. Registration queues a signed, expiring verification email; resend is rate limited. After the first successful verification, a separate welcome email introduces character choice and room play. Both emails include embedded artwork and plain-text alternatives. Changing an account's email revokes verification and queues a new link. Profile editing remains available so an unverified user can correct their address.

Run `php artisan migrate` on existing installations to add welcome-email delivery tracking, and keep `php artisan queue:work --tries=3` running. Configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and a provider-approved `MAIL_FROM_ADDRESS` for inbox delivery. Set `APP_URL` to the public HTTPS site so signed links point to the correct host. The default `MAIL_MAILER=log` is for local previews and does **not** deliver email. After deployment, refresh cached configuration and restart queue workers. The welcome job retries failures and skips accounts that already received their welcome.

Illustrated villagers are available as cosmetic portraits. Guests receive a random character; signed-in players can select any character when creating or joining a room, and change it in the lobby. Account choices are saved across browser sessions and devices. Portraits stay with the seat through reconnects and rematches and never indicate a role or team. Older rooms receive a stable fallback portrait.

The room has persistent **Play / My role / Chat / Help** navigation, with a compact bottom bar on phones and chat beside play on desktop. **Your turn** keeps each phase's instructions, controls, and submitted status together. Select an eligible seat to choose a target, then confirm using the action controls; seat selection never submits an action. A dismissible selection hint can be reopened in Help. Chat has unread indicators; private investigation indicators appear only when secrets are revealed and clear when viewed. The role view shows the objective, ability, shared mission, and teammates explicitly. Help provides current-phase guidance, rules, and a separate glossary without leaving the room. The table retains public ritual progress, phase lighting, candles, and sealed cards. Roles turn face-up only after victory. Reduced-motion preferences suppress decorative motion.

Optional procedural audio adds coastal ambience and short cues for phases, sealed actions, ritual progress, and victory. Effects and ambience have separate saved volume controls. Sound requires a user gesture, even when an enabled preference was saved previously. Background tabs are silent, and discussion and voting ambience is quieter. A gentle deadline reminder is limited to players who still need to act. Audio cues use public game events and the player's own submission; they never encode a secret role or target.

Recorded music lives in `public/assets/Music/`. Add files named `day1.mp3`, `day2.mp3`, `night1.mp3`, `night2.mp3`, and so on (positive numbers; gaps are fine). MP3, OGG, WAV, M4A, AAC and Opus filenames are recognized; playback depends on browser codec support. Tracks are discovered on each room page load, so upload new files and refresh the room to include them—no code/configuration change or frontend rebuild is needed. Keep the capital `M` in `Music` on case-sensitive servers.

Night uses the night playlist; the lobby, role reveal, discussion and voting use the day playlist. Each playlist shuffles through every track before repeating, avoids immediate repeats when there are multiple tracks, and repeats a single available track. Daytime phase changes keep the current song playing. Music stops after victory, pauses when sound is off or the tab is hidden, and stays softer throughout discussion and voting. The saved **Music** volume is independent of effects and ambience. Missing or unsupported recordings are skipped for that page session; an empty playlist is silent.

## 3D ritual table

The room's **3D / Simple** selector chooses between a Three.js scene and the illustrated CSS table. The choice is remembered on that browser. The 3D scene adds a wooden table, brass inlay, candles, drifting motes, illuminated carvings and an emerging summoning. Player portraits, targeting, sealed actions and the ritual counter remain accessible HTML controls above the scene.

The scene follows public ritual progress across all room sizes and modes. Cracks, mist and tentacles grow as the ritual advances. Filling the ritual still leaves the village its final discussion and vote; full emergence is reserved for a confirmed Cult victory. A Town victory seals the summoning, and a new gathering clears the previous ritual. Visual effects never reveal hidden roles, teammates or actions and add no sound.

Three.js loads only when 3D is selected. Browsers without WebGL2, or whose graphics context fails, retain the Simple table and normal gameplay. Rendering pauses when the table is offscreen or the tab is hidden; reduced-motion preferences show a static scene that updates with game state. Pixel density and animation rate are capped to limit graphics work. No extra backend service, database migration or 3D asset download is needed; install dependencies and rebuild the frontend as usual.

## First-match rules (provisional)

- **3–10 players**: 3–4 players have **1 cultist**, 5–6 have **2**, 7–8 have **3**, and 9–10 have **4**. In the Classic roster, exactly one cultist is the Veilweaver. A Dreamweaver replaces one Acolyte at 7+ players; remaining cultists are Acolytes. There is always one Oracle. A Warden joins at 5+ players, a Lamplighter at 7+, a Medium at 8+, and a Bellkeeper at 10, each replacing a Townsperson; remaining town seats stay Townspeople. Cultists know all their teammates. In 5–10-player matches they receive the same randomly selected mission. **Small Gathering (3–4 players)** gives the lone Veilweaver **A voice below**: each submitted chant advances the ritual by one step, even when investigated. The lobby previews the current roster and ritual goal before players ready up.
- **25-second reveal → 45-second night → 90-second discussion → 45-second vote**, repeating night/discussion/vote. Reveal, night, and voting finish early when all living players submit. Missing night actions forfeit; missing votes abstain. During discussion, living players can mark themselves Ready for voting. Everyone can see this status; voting starts early when all living players are ready, or when the timer ends.
- Cultists can chant; the Dreamweaver can instead spend their ability to disrupt a player. The Veilweaver can also veil another living player, reversing their apparent alignment for the Oracle that night. Investigations resolve after all veils, independent of submission order. Your actual role and mission are never altered. Oracle results are private and should be treated as fallible evidence.
- **Concord:** all surviving cultists must chant; advance the ritual by one step for each. **Shadows:** advance one step per chanting cultist who was not investigated. **Patience:** advance one step per chanting cultist if the preceding vote did not banish a cultist (first night qualifies). A surviving lone cultist can still score.
- The shared ritual has these progress goals: 3 / 4 / 6 / 8 / 9 / 10 / 11 / 12 for 3 / 4 / 5 / 6 / 7 / 8 / 9 / 10 players. With every cultist scoring, this takes three nights for 3, 5, 7, 9, or 10 players, and four for 4, 6, or 8 players. Reaching or exceeding the goal at dawn grants one final discussion and vote. A red summoning warning appears in the room and any active curse modal. These thresholds remain provisional as the expanded rosters are playtested.
- One vote per living player; no self-votes. A unique plurality banishes its target. Abstention is an option that competes in the tally, and ties mean no banishment. A banished player’s allegiance stays publicly hidden until victory; the Medium can privately learn it.
- Town wins immediately when all cultists are banished, including on the final vote after the ritual fills. If any cultist survives that final vote, the cult wins immediately: a tie, abstention, missed votes, banishing a town player, or banishing only one of several remaining cultists cannot delay the summoning. The cult also wins if no townspeople remain, or when **exactly one cultist and one town player remain**. This two-player ending prevents tied-vote stalemates, including Shadows versus an Oracle. There is no broader parity victory (two cultists versus two town players continues) and no night kill. The final ballot is included in the match recap.
- Public text chat is available in the lobby, discussion, voting, and after victory. Banished players watch silently until the match ends. The host can then start a rematch, preserving seats and resetting all roles, missions, actions, messages, readiness, and investigation results.

Tune the player limits, cultist counts by room size, special role thresholds (`town_roles_min_players` and `cult_roles_min_players`), phase lengths, missions, and ritual goals in `config/game.php`. Explicit `ritual_goals_by_player_count` entries override the multiplier. Bump `rules_version` when adjusting balance so statistics remain comparable. Starting a match snapshots its rules version, player count, phase lengths, mission, and goal; configuration changes apply to the next gathering. New matches use `spatial-curses-v1`, adding 3D curse puzzles and an optional way to break misdirection while retaining the existing modes and roles. Existing matches retain their assigned roles; the new roster applies on the next start or rematch. The final-vote rule and two-player ending also apply to ongoing matches; finished matches remain finished. Self-veiling is not enabled.

## Eldritch curses

The **Medium** can contact one banished player at night, once per match, to privately learn their true alignment at dawn. Veils do not affect this reading. The **Dreamweaver** can once replace chanting with a disruption of another living player's night action; ordinary nights are targetless chants, without curses. Disruptions resolve first and stop the target's other ability or chant, including Warden protection and the bell. A target who submitted receives a private failure result without the Dreamweaver's identity. A disrupted Oracle does not investigate for Shadows scoring, and choosing disruption breaks Concord's requirement that every living cultist chant. Simultaneous Dreamweaver disruptions resolve together and cannot undo one another. The Lamplighter still detects submitted targeting attempts, including disrupted actions.

The **Bellkeeper** can ring once per match to prevent one eligible ritual step that night. Existing progress cannot decrease. The bell applies before curse difficulty is determined and can prevent the ritual from reaching its final-vote threshold. The private result reports whether a step was prevented; the final recap reconciles the blocked contribution with net progress.

All three roles can save their ability by keeping watch (Medium/Bellkeeper) or chanting (Dreamweaver). Confirming use spends it immediately, even if subsequently disrupted or if the bell has nothing to prevent. A missed, invalid, duplicate, or stale submission cannot spend an unused ability. Refreshes preserve use status; rematches reset it. Ability use, results, and disruptions are private until the final recap. No database migration is needed for these roles.

The **Warden** protects another living player from all new curses that night, or skips protection. Consecutive nights cannot protect the same player; a skipped or missed night breaks the streak. Protection does not remove existing curses, stop veils, or affect chanting. Misdirection chooses only legal protection targets, and the actual recipient determines the next night's restriction. The Warden's private result records whom they protected, without revealing whether a curse was attempted.

The **Lamplighter** watches another living player and privately learns at dawn whether anyone else targeted them. Their own watch does not count. Oracle investigations, Warden protection, and cult targets count, including blocked curses; chanting without a target does not. Results report only presence or absence, never visitor identities, counts, roles, or abilities. Veils cannot change these results, and misdirection uses the actual targets. Missed actions produce no result. This role is independent of the Lamplighter cosmetic character. Both roles appear in the shared home/room role guide; their actions and blocked curses are explained in the final recap.

Veilweaver and Acolyte night targets also inflict an eldritch curse at dawn: the Veilweaver veils and curses, while Acolytes may curse a target while chanting. A victim receives at most one curse each dawn. Veils still reverse Oracle readings for that night. Curses never change the victim's actual role or allegiance.

Ritual progress determines the tier after the night's chants resolve: level 1 below one-third, level 2 from one-third, and level 3 from two-thirds. Veilweavers and Acolytes choose a random puzzle or mind mist alongside their target while still chanting. Misdirection becomes selectable once the ritual is already at level 3 when submitting; progress earned that night unlocks it for the next night. Puzzle and mist difficulty uses the tier after chants resolve. Requests and already submitted actions without a curse choice default to a random puzzle. Multiple curses on one victim do not stack; the first effective submitted curse takes hold.

- **Puzzle curse:** opens a locked modal with a fresh 3D ritual. Rotate concentric rune rings until their notches face the north beacon (3 / 4 / 5 rings), or wake stone towers from shortest to tallest (4 / 5 / 6 towers). Orbit the scene to inspect the mechanism; keyboard controls offer the same actions.
- **Mind mist:** opens a locked 3D lantern challenge, blurs the village scenery, and garbles incoming chat for the victim. Light numbered lanterns in ascending order among word decoys (6 / 8 / 10 numbered lanterns by level). Each selection thins the mist. The phase, timer, and instructions stay readable.
- **Misdirection:** redirects the next targeted vote or night action to a random different legal player (another banished player for the Medium, otherwise a living player), excluding the affected player and the original choice. The victim can first open an optional rune-ring puzzle to remove it. Closing that puzzle keeps the curse and progress; room actions remain available. The victim learns the actual redirected target when submitting. Abstaining does not consume it.

Puzzle and mist modals cannot be dismissed with Escape or a backdrop click. The server blocks chat, discussion readiness, night actions, and votes (including abstention) until the curse is solved or expires; the match clock keeps running. There is no artificial waiting period. Curses persist through the following night and expire at the next dawn; solving a curse removes it sooner. Banishment, victory, and rematches clear them. Multiple cultists targeting the same victim do not stack curses. Challenges and option layouts persist across refreshes; answers are validated on the server and never sent to clients. Incorrect answers can be edited and retried. Fresh layouts and opaque option IDs prevent fixed answer memorization. The scene is lazy loaded and releases its graphics resources when closed. If WebGL is unavailable, equivalent labeled controls keep every puzzle solvable. Older saved text challenges remain supported. Stronger curses were introduced under rules version `eldritch-curses-v2`; `final-ritual-vote-v1` retains them and adds the last voting round.

## Recaps and balance tracking

Run `php artisan migrate` to add `game_matches` before playing with this update. After victory, **What really happened** reveals each recorded night action, investigation reading and veil, ritual contribution, and ballot. Missed votes are distinguished from deliberate abstentions. No recap data is sent to players while a match is active, including banished players.

Completed matches are archived in the same transaction as victory, with a unique match ID. Starting a rematch clears the room's recap but preserves the archive. Records include the starting player count, rules version, mission, winner and reason, nights, duration, ritual goal/progress, and missed night actions/votes. Night misses include every living role, including a Townsperson failing to keep watch. The archive excludes session identity hashes and chat. It is server-side only; there is no public history endpoint.

Use `php artisan game:stats` (or `php artisan game:stats --json`) to compare completed matches grouped by rules version, starting player count, and mission. Reports include sample counts, cult win rates, mean nights/duration, and missed submissions. Abandoned matches are not included. Matches already underway at the update retain their mission/goal and are marked as partial tracking, grouped separately from complete records. Their earlier actions and full duration cannot be recovered.

## Authority and hidden information

`app/Game/MatchEngine.php` owns every rule. Room state is persisted as a JSON aggregate in a MySQL row. Reads that can resolve deadlines and all mutations take the same `SELECT ... FOR UPDATE` lock. Night/vote submissions are keyed by player; ritual progress awards are keyed by night and player. A phase ID rejects stale submissions, and a revision keeps clients from accepting older responses.

The API builds an explicit personalized allowlist. The authoritative model hides its state when serialized. While a match is active, other players’ roles, the selected mission, night targets, ballots, awards, and private investigations are never included in another player’s response. Only cultists see their teammates and shared mission. Final roles and the recorded recap are public to room members after victory; seat identity hashes are never exposed. Responses use `Cache-Control: private, no-store`.

The session-based broadcast authorization endpoint validates both the encrypted session seat and exact room channel. Reverb events contain **only a revision**, never game state. Client events are disabled. Echo invalidates the current view; authenticated HTTP fetches recover it. Periodic polling and reconnect/visibility refresh also recover missed events. Queue delays or Reverb downtime therefore do not prevent play.

`game:tick` resolves due phases every second via Laravel’s scheduler, even with every browser closed. A state read also resolves an overdue phase under the same lock. After a server outage it advances one phase at a time and grants a fresh deadline; it does not fast-forward absent players through an entire match.

CSRF protection, session blocking, input validation, and request throttles apply to room endpoints. Session identities are generated by the server; clients cannot select a seat ID. The design is intended for private invited groups, not public matchmaking or competitive anti-collusion enforcement.

## Checks

```sh
npm run check
npm run types:check
npm run test:audio
npm run build
composer test
```

`npm run test:audio` covers cue deduplication, deadline reminders, preference validation, independent volume controls, audio lifecycle/cleanup, shuffled music playback, phase switching, missing tracks, and autoplay rejection with mocked browser audio. It runs as part of `composer ci:check`. Browser checks should also cover first-click activation, mute, background-tab suspension, mobile sound settings, and keyboard seat selection. `MusicLibraryTest` checks automatic discovery of new numbered recordings.

PHPUnit uses a separate in-memory SQLite database by default. `tests/Feature/GameTest.php` covers hidden information, guest recovery, channel authorization, abilities in either direction, mission scoring, duplicate/stale actions, deadlines, ties and abstentions, both winners, dead-player restrictions, and rematches. MySQL should also be exercised before production because SQLite does not implement row-level locks. To run the suite on MySQL, create an **empty dedicated test database** and set process environment variables `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_URL` (empty) before running `php artisan test`; never point RefreshDatabase tests at real game data.

## Deploy at whoschanting.app

The frontend build runs Artisan through Wayfinder. If the server uses a versioned PHP executable, select the same executable used for Composer and migrations: `WAYFINDER_COMMAND="php8.5 artisan wayfinder:generate" npm run build`. Export this variable in the deployment shell or set it inline as shown; adding it only to Laravel's `.env` does not configure this build command. To see an underlying generation error directly, run `php8.5 artisan wayfinder:generate --with-form`.

Domain registration/DNS, hosting, TLS, and production credentials are external setup. Serve Laravel’s `public/` directory over HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://whoschanting.app`, `SESSION_SECURE_COOKIE=true`, and use production MySQL. Set Reverb’s public hostname and HTTPS port, restrict `REVERB_ALLOWED_ORIGINS` to the site hostname, and proxy WebSockets to Reverb. Rebuild frontend assets after changing `VITE_REVERB_*`.

Supervise the queue worker and Reverb. Run Laravel’s scheduler continuously (or `schedule:run` every minute, which runs the sub-minute tick). Run `php artisan migrate --force`, cache configuration, and restart workers on deploy. Multi-server deployments also need shared session/cache infrastructure and Reverb scaling configuration.

Not included in the first milestone: account-based cross-device seat recovery, host transfer/kicking, public matchmaking, moderation tools, automated room retention, or a larger role roster. Private rooms should be played with trusted friends. The default poll fallback works without Reverb, but production should run all services above.

### Illusions roster

The host can choose **Illusions** in the lobby (5+ players); changing rosters clears readiness for everyone. Classic remains available. Illusions retains the Oracle and substitutes Phantasm for Veilweaver, adds Counterfeiter as the second cult seat, substitutes Exorcist for Warden, and substitutes Oathkeeper for Medium at 8+. Lamplighter and Dreamweaver join at 7+, Bellkeeper at 10+. Remaining seats use Townsperson/Acolyte as before. The selected roster persists into rematches and is recorded in recaps. No database migration is needed.

- **Phantasm:** once per match, gives up chanting to haunt another living player through the following discussion and conceal that player's outgoing visits from the Lamplighter that night. Other visible visitors still count. Disruption prevents both effects. The private decoy is selected without consulting allegiance; false faces, wandering shadows and quiet directional humming convey no true alignment. Visual effects remain with sound off, become static under reduced motion, and never change names, controls, results or the victim's true role. The haunting ends before voting or immediately on exorcism. It does not inflict a regular curse.
- **Counterfeiter:** once per match, gives up chanting to set another living player's apparent Oracle alignment to town or cult for that night. This overrides a veil. A forgery does not alter true roles or Medium results, and is spent even when nobody reads the target. Oracle results contain no flag identifying a forgery; the final recap explains it. Disruption prevents the forgery. It does not inflict a regular curse.
- **Exorcist:** once per match during discussion, clears another living player's current curse and Phantasm haunting. The action spends the ability even if no affliction was present, avoiding a presence-detection exploit. It neither rewrites old readings nor grants future immunity. A blocking curse must be solved before the Exorcist can act. Cleansing does not consume the separate discussion-ready action.
- **Oathkeeper:** once per discussion, publicly and irrevocably promises to vote for another living player. Matching the actual ballot grants immunity to new curses for the next night only. Misdirection, abstention and missed ballots can break the oath. This does not prevent haunting, forgery or disruption. Oaths are public; votes and earned protection remain private until the recap.

The two Cult abilities break Concord's all-chant requirement. Invalid or missed actions do not spend abilities; submitted abilities remain spent if disrupted. Rematches clear abilities, hauntings, oaths and protection. Tune the alternate thresholds with `illusion_town_roles_min_players` and `illusion_cult_roles_min_players` in `config/game.php`.

### Modes and custom rosters

Room creation includes a **Modes** tab. The chosen settings are saved immediately with the room. In the lobby, only the host can apply different settings; doing so clears everyone's ready status. Settings cannot change after the match starts, and rematches retain the chosen mode. Presets scale to the current room size; the lobby shows the actual expected roster. A Chaos deal is drawn once at match start and never rerolled by polling.

- **Classic:** the original 3?10-player setup remains the default, with the existing Illusions variant available from 5 players.
- **Hard (5+):** keeps Oracle/Veilweaver, adds Tracker/Counterfeiter at 5, Exorcist/Phantasm at 7, Herbalist at 8, Dreamweaver at 9 and Oathkeeper at 10. Spare seats are Townspeople or Acolytes.
- **Chaos ? Wildcards (5+):** randomly deals any implemented roles within the normal faction counts, with duplicates possible and no guaranteed Oracle. The total cast is public once dealt; identities remain secret.
- **Chaos ? Maelstrom (5+):** uses the Wildcards deal and draws one public event at each night's start. **Hall of mirrors** reverses base Oracle readings, then veils apply, then forgeries override them. **Sanctuary** blocks all new curses but leaves other actions intact. **Eclipse** conceals all visits from Lamplighters and Trackers. The event remains visible through the following day and is included in the final recap. Events can repeat.
- **Custom:** choose active roles and exact counts, with one copy when a role is enabled. The default custom draft is Oracle1, Veilweaver1, Townsperson1. There must be at least one Town and one Cult, and 3?10 total roles. The configured total sets room capacity and must exactly match the player count to start; no role fillers are silently added. Normal ritual goals and victory conditions still apply. Custom counts are not otherwise balanced automatically.

**Tracker (Town):** targets another living player each night and privately learns the name of that player's submitted visit target, or that no outgoing visit was visible. It observes attempted visits even if their abilities are disrupted. Phantasm concealment and Eclipse hide them. The Tracker learns no visitor role or ability. A disrupted Tracker receives only the disruption notice.

**Herbalist (Town):** once per match, makes a targetless night action that protects all living players against new curses that dawn. It does not clear old curses or stop veils, forgeries, hauntings, disruptions or ritual progress. Committing the ability spends it even on a quiet night or when disrupted. Keeping watch saves it.

Duplicate roles have independent actions, private results and ability budgets. In Custom/Chaos, overlapping effects resolve in public seat order: the last effective Counterfeiter's forgery wins for a shared target; multiple veils on the same target still reverse once; only the first effective curse takes hold. Each Bellkeeper can prevent one eligible new ritual step. Classic retains its previous curse ordering. Mode settings and each Maelstrom event are archived in the existing recap JSON, without a database migration.
