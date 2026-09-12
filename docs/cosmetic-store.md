# Cosmetic store and Crowns

The store lives on **Profile** at `/settings/profile#store`. Verified players earn virtual **Crowns** and can spend them on permanent titles, accent colors, and portrait backdrops. Purchases unlock the normal Progression wardrobe; players choose when to equip them. Existing characters, achievements, free cosmetics, roles, and game modes retain their existing access rules.

## Earning and spending

- A qualifying completed match awards **1 Crown per starting player**, plus **5 for winning with 1–6 players** or **10 for winning with 7+ players**. A 10-player match pays 10 Crowns for participation or 20 for a win. The archived starting roster determines the amount, including guests and eliminated players. XP rewards remain unchanged.
- Eligibility follows account progression: a verified account linked to the seat, at least one submitted night action and one submitted vote. Explicit abstention counts. Eliminated players can still qualify. Tutorial practice, inactive seats, guests, unverified accounts, and incomplete matches earn nothing.
- Crowns arrive when the server archives the completed match, once per account per match. Previous match receipts are not backfilled. Older receipts without currency continue to render.
- Crowns and purchased cosmetics persist across seasons. Crowns are in-game currency with no cash value or withdrawal feature.
- The initial six cosmetics cost 100–250 Crowns. Definitions, prices, and match earnings live in `config/store.php`; the browser never supplies a trusted price or balance.

`POST /account/store/purchase` accepts `{ "item_id": "title-night-market" }` and returns the refreshed progression payload. Authentication, email verification, CSRF protection, request throttling, and private response headers apply. Unknown products and insufficient funds return validation errors. Repeating a successful purchase returns the current state without another debit, including when the remaining balance is zero.

## Shared Wi-Fi and crown farming

Public and private rooms allow multiple people on the same internet connection. The server no longer collects or checks room IP fingerprints. Each account has one seat per room and can receive only one reward per match. Every eligible verified account can earn its own reward, even on shared Wi-Fi. Email verification does not prove one person owns only one account.

The account-level crown guard in `config/store.php` tracks qualifying matches lasting **under 120 seconds**. The **fourth such match awarded within a rolling 15-minute window** starts a **15-minute crown cooldown**, and that match pays no Crowns. The first three still pay normally. Duration comes from server-recorded start and finish timestamps; the rolling window and cooldown use the server award-processing time. This avoids trusting client clocks and makes the cooldown consistent across rooms, sessions, and workers. Delayed processing of several short matches can also trigger the guard.

The cooldown affects only new match Crowns. Joining, rematches, XP, achievements, and spending existing Crowns continue. Matches completed during the cooldown do not extend it or get paid retroactively. At expiry, the rapid-match history resets and earning resumes. Ineligible or incomplete matches do not count. A retry returns the original receipt without another credit or another cooldown strike. The shop shows the remaining wait and match receipts explain withheld Crowns without accusing the player of cheating.

The profile row lock serializes cooldown decisions across rooms. Profile changes and reward receipts commit atomically, and only positive Crown grants create currency transactions. Cooldowns do not modify existing balances. They deter rapid farming; they are not complete multi-account or bot detection.

`TRUSTED_PROXIES` still supports accurate client addresses for existing request rate limits behind explicit trusted proxy IPs/CIDRs. It is not used to restrict shared-Wi-Fi players or their rewards.

## Persistence

`player_profiles.coins` holds the available balance; `coins_earned` tracks lifetime match earnings. `store_purchases` records permanent ownership and the price paid. `coin_transactions` records each credit/debit, its resulting balance, and a unique source reference. Account deletion cascades to all of these records.

Match credits and purchases use the same locked profile row as progression and customization. Balance changes, receipts, and entitlements commit together. Database uniqueness on account/product and account/transaction reference protects against duplicate grants. Cosmetics are only added to the wardrobe after ownership is verified on the server; public room appearance never includes balance or purchase history.

Keep product IDs stable and retain definitions for previously sold cosmetics so owners can continue to equip them. A future rotating storefront should separate whether an item is offered from whether an owner can use it.

## Optional paid additions

The page labels microtransactions, cosmetic season passes, and a supporter subscription as **Coming later**. There is no paid checkout, recurring billing, paid currency, or active premium pass in this release. The existing quarterly progression track remains available to everyone.

Future payment integration should grant cosmetic entitlements from verified payment events, with its own unique transaction references and refund handling. A season pass can add an optional cosmetic reward track while keeping the existing free track. Any subscription benefits should remain cosmetic. Purchases must never improve roles, abilities, matchmaking, win chances, XP gain, or Crown earnings.

## Deployment and checks

Run `php artisan migrate`, rebuild with `npm run build`, and restart long-running game and queue workers. New installations apply the store migration with the other migrations. The crown cooldown migration adds nullable state to player profiles. Run migrations before starting the updated workers. There is no new scheduler task or environment secret.

Feature coverage in `CosmeticStoreTest`, `ProgressionTest`, `CrownCooldownTest`, and `RoomNetworkTest` checks eligibility, real match awards, retries, rollback, insufficient funds, server pricing, account isolation, permanent ownership, wardrobe validation, and account deletion. The standard SQLite feature suite checks transaction behavior and retry semantics; it does not prove production MySQL row-lock behavior.
