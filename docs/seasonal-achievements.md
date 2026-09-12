# Seasonal character rewards

The three rewards are complete portraits under `public/assets/chanting/unlocks/seasonal_*.png`. Their catalogue entries are registered in `config/game.php`; unlock associations and public class hints are in `config/progression.php`. The modular creator remains disabled.

`SeasonalAchievements` evaluates authoritative resolved rounds inside the existing account reward transaction. It does not accept progress from HTTP requests. The normal participation rules apply: verified account, completed eligible match, at least one submitted night action and one submitted vote. Guest, incomplete and non-participating matches do not advance these achievements.

Server-only conditions:

- Warden: an effective protection prevents another player's incoming curse. Protection already supplied by sanctuary, an oath or village herbs does not count as a Warden save.
- Cultist (Acolyte or Veilweaver): their own misdirection curse makes a Vigilante fire at a different target. The shot must actually resolve. Ordinary redirected votes/investigations, disrupted shots, unchanged targets and missing/other-caster attribution do not count.
- Oathkeeper: keep at least one promise, with no broken promises, in five consecutive qualifying Oathkeeper games within one season. Other roles leave the streak alone. A qualifying Oathkeeper game with no kept promise or any broken promise resets it. Multiple promises in one game add only one step.

The season is determined by match completion time in UTC using the existing quarterly calendar. `player_seasons.achievement_progress` stores unfinished streaks; a new season starts with no progress. `player_profiles.achievements` stores permanent unlock timestamps. Existing unique match rewards make repeated archive processing idempotent. Earned characters remain available in later seasons and can be worn with any role.

The seasonal API projection deliberately excludes conditions, target counts and progress, even after unlocking. Locked achievement and character cards expose only `?` and a class hint; they do not render the reward image. Earned cards reveal the character and date. Curse source identity is private during play and appears only in completed match records.

Apply `2026_09_12_000002_add_seasonal_progress_to_player_seasons.php` when deploying. It adds one nullable JSON column without modifying existing progression. It was applied to the local development database during implementation; other environments still need their normal migration step.

Coverage lives in `SeasonalAchievementsTest`, the new real-resolution cases in `GameTest`, and the existing progression/unlock suites. Artwork prompts and provenance are in `seasonal-characters-art.md`.
