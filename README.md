# Who’s Chanting?

A private-room social deduction game built with Laravel 13, Vue 3, TypeScript, Reverb + Echo, MySQL, and a lazy-loaded Phaser card table. No account is required: an encrypted Laravel session owns your seat.

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

Open <http://127.0.0.1:8000>. Create a room, send its code or invite link to friends, and have everyone mark themselves ready. Use separate browsers/profiles to test multiple seats; tabs in one browser deliberately share a seat. Reopening the room in the same browser recovers it. Clearing cookies, session expiry, or changing browsers does not recover a seat. The template keeps active sessions for seven days.

For phones on a local network, serve on `0.0.0.0`, set `APP_URL` and the Reverb/Vite host to the machine’s LAN address, add that hostname to `REVERB_ALLOWED_ORIGINS`, and rebuild. Friends must reach both HTTP and WebSocket ports. Do not expose development servers to the internet.

## Characters and presentation

Accounts must verify their email before entering the game or choosing a character. Guests can still play without registering. Registration queues a signed, expiring verification email; resend is rate limited. After the first successful verification, a separate welcome email introduces character choice and room play. Both emails include embedded artwork and plain-text alternatives. Changing an account's email revokes verification and queues a new link. Profile editing remains available so an unverified user can correct their address.

Run `php artisan migrate` on existing installations to add welcome-email delivery tracking, and keep `php artisan queue:work --tries=3` running. Configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and a provider-approved `MAIL_FROM_ADDRESS` for inbox delivery. Set `APP_URL` to the public HTTPS site so signed links point to the correct host. The default `MAIL_MAILER=log` is for local previews and does **not** deliver email. After deployment, refresh cached configuration and restart queue workers. The welcome job retries failures and skips accounts that already received their welcome.

Eight illustrated villagers are available as cosmetic portraits. Guests receive a random character; signed-in players can select any character when creating or joining a room, and change it in the lobby. Account choices are remembered within the browser session. Portraits stay with the seat through reconnects and rematches and never indicate a role or team. Older rooms receive a stable fallback portrait.

The game includes a Phaser card table, an in-game glossary, and optional synthesized sound effects. Sound is enabled with a user gesture and can be muted. Reduced-motion preferences are respected; HTML controls remain available independently of the canvas.

## First-match rules (provisional)

- **3–10 players**: 3–4 players have **1 cultist**, 5–6 have **2**, 7–8 have **3**, and 9–10 have **4**. Exactly one cultist is the Veilweaver; any remaining cultists are Acolytes. There is always one Oracle, with the remaining town seats assigned to Townspeople. Cultists know all their teammates. In 5–10-player matches they receive the same randomly selected mission. **Small Gathering (3–4 players)** gives the lone Veilweaver **A voice below**: each submitted chant advances the ritual by one step, even when investigated. The lobby previews the current roster and ritual goal before players ready up.
- **25-second reveal → 45-second night → 90-second discussion → 45-second vote**, repeating night/discussion/vote. Reveal, night, and voting finish early when all living players submit. Missing night actions forfeit; missing votes abstain. During discussion, living players can mark themselves Ready for voting. Everyone can see this status; voting starts early when all living players are ready, or when the timer ends.
- Each cultist’s night action chants. The Veilweaver can also veil another living player, reversing their apparent alignment for the Oracle that night. Investigations resolve after all veils, independent of submission order. Your actual role and mission are never altered. Oracle results are private and should be treated as fallible evidence.
- **Concord:** all surviving cultists must chant; advance the ritual by one step for each. **Shadows:** advance one step per chanting cultist who was not investigated. **Patience:** advance one step per chanting cultist if the preceding vote did not banish a cultist (first night qualifies). A surviving lone cultist can still score.
- The shared ritual has these progress goals: 3 / 4 / 6 / 8 / 9 / 10 / 11 / 12 for 3 / 4 / 5 / 6 / 7 / 8 / 9 / 10 players. With every cultist scoring, this takes three nights for 3, 5, 7, 9, or 10 players, and four for 4, 6, or 8 players. Victory resolves at dawn before another vote. These thresholds remain provisional as the expanded rosters are playtested.
- One vote per living player; no self-votes. A unique plurality banishes its target. Abstention is an option that competes in the tally, and ties mean no banishment. A banished player’s allegiance stays hidden until victory.
- Town wins immediately when all cultists are banished. Cult wins on completing the ritual, if no townspeople remain, or when **exactly one cultist and one town player remain**. This two-player ending prevents tied-vote stalemates, including Shadows versus an Oracle. There is no broader parity victory (two cultists versus two town players continues) and no night kill.
- Public text chat is available in the lobby, discussion, voting, and after victory. Banished players watch silently until the match ends. The host can then start a rematch, preserving seats and resetting all roles, missions, actions, messages, readiness, and investigation results.

Tune the player limits, cultist counts by room size, phase lengths, missions, and ritual goals in `config/game.php`. Explicit `ritual_goals_by_player_count` entries override the multiplier. Bump `rules_version` when adjusting balance so statistics remain comparable. Starting a match snapshots its rules version, player count, phase lengths, mission, and goal; configuration changes apply to the next gathering. The two-player ending also resolves existing matches. Self-veiling is not enabled.

## Eldritch curses

Cult night targets now also inflict an eldritch curse at dawn: the Veilweaver veils and curses, while Acolytes may curse a target while chanting. A victim receives at most one curse each dawn. Veils still reverse Oracle readings for that night. Curses never change the victim's actual role or allegiance.

Ritual progress determines the tier after the night's chants resolve: level 1 below one-third, level 2 from one-third, and level 3 from two-thirds. Levels 1–2 choose equally between a puzzle curse and mind mist; level 3 adds misdirection as a third equally likely outcome. Puzzle and mist difficulty grows with the tier.

- **Puzzle curse:** one of six freshly generated challenges (cipher, ordering, missing number, offerings arithmetic, odd sigil, or reversed runes). Blocks targeted votes and night actions until solved; untargeted actions and abstentions remain available.
- **Mind mist:** blurs the village scenery and garbles incoming chat for the victim. Selecting numbered anchors in ascending order clears it. The controls, timer, and clues stay readable.
- **Misdirection:** redirects the next targeted vote or night action to a random different living player, excluding the affected player and the original choice. The victim learns the actual target when submitting. Abstaining does not consume it.

Curses persist through the following night and expire at the next dawn; solving puzzle/mist removes them sooner. Banishment, victory, and rematches clear them. Multiple cultists targeting the same victim do not stack curses. Challenges and option layouts persist across refreshes; answers are validated on the server and never sent to clients. Random content prevents fixed answer memorization, though the six puzzle mechanics can be learned.

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
npm run build
composer test
```

PHPUnit uses a separate in-memory SQLite database by default. `tests/Feature/GameTest.php` covers hidden information, guest recovery, channel authorization, abilities in either direction, mission scoring, duplicate/stale actions, deadlines, ties and abstentions, both winners, dead-player restrictions, and rematches. MySQL should also be exercised before production because SQLite does not implement row-level locks. To run the suite on MySQL, create an **empty dedicated test database** and set process environment variables `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_URL` (empty) before running `php artisan test`; never point RefreshDatabase tests at real game data.

## Deploy at whoschanting.app

The frontend build runs Artisan through Wayfinder. If the server uses a versioned PHP executable, select the same executable used for Composer and migrations: `WAYFINDER_COMMAND="php8.5 artisan wayfinder:generate" npm run build`. Export this variable in the deployment shell or set it inline as shown; adding it only to Laravel's `.env` does not configure this build command. To see an underlying generation error directly, run `php8.5 artisan wayfinder:generate --with-form`.

Domain registration/DNS, hosting, TLS, and production credentials are external setup. Serve Laravel’s `public/` directory over HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://whoschanting.app`, `SESSION_SECURE_COOKIE=true`, and use production MySQL. Set Reverb’s public hostname and HTTPS port, restrict `REVERB_ALLOWED_ORIGINS` to the site hostname, and proxy WebSockets to Reverb. Rebuild frontend assets after changing `VITE_REVERB_*`.

Supervise the queue worker and Reverb. Run Laravel’s scheduler continuously (or `schedule:run` every minute, which runs the sub-minute tick). Run `php artisan migrate --force`, cache configuration, and restart workers on deploy. Multi-server deployments also need shared session/cache infrastructure and Reverb scaling configuration.

Not included in the first milestone: account-based cross-device seat recovery, host transfer/kicking, public matchmaking, moderation tools, automated room retention, or a larger role roster. Private rooms should be played with trusted friends. The default poll fallback works without Reverb, but production should run all services above.
