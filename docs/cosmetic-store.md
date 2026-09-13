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

## Paid bundles and shared table cosmetics

The store also offers three permanent, one-time bundles through Stripe Checkout:

| Bundle           | Price    | Cosmetics                                                                                                       |
| ---------------- | -------- | --------------------------------------------------------------------------------------------------------------- |
| Founder's Pack   | EUR 7.99 | Founder title and frame, gold accent, hall backdrop, oak table, Gilded Vortex banishment, Crownfall celebration |
| Moonlit Coven    | EUR 4.99 | Violet accent, moonlit backdrop and table, Lunar Rift banishment, Moonrise celebration                          |
| Harvest Festival | EUR 4.99 | Amber accent, harvest backdrop and table, Ember Spiral banishment, Lantern Festival celebration                 |

Each collection has a real Three.js preview. Wardrobe selections are independent: mix a table from one bundle with effects from another. The host's equipped table is snapshotted at match start; later wardrobe changes apply on the next gathering. Banishment uses the voted-out player's effect. At victory, the server chooses uniformly once among all winning-faction players, including eliminated winners, and persists that player's equipped celebration. Default effects remain free. A final vote queues the banishment before the victory celebration. Polls and reconnects do not replay historical effects. Reduced-motion settings use static compositions; a WebGL failure leaves the game and text announcements usable.

Paid banishments have distinct multi-stage sequences: Gilded Vortex's golden tribunal, Lunar Rift's eclipse doorway, and Ember Spiral's autumn pyre. The renderer, live event queue and preview share effect durations. See [the banishment sound brief](banishment-sounds.md) for matching sound layers, cue timings, sourcing links and delivery filenames; recordings and playback are not included yet.

Paid victories also have separate productions: Crownfall's gilded coronation, Moonrise's celestial reveal above a reflecting pool, and Lantern Festival's rising canopy of paper lanterns. Preview captions follow the selected effect, and shared durations allow each finale to finish. See [the victory sound brief](victory-sounds.md) for synchronized cue sheets, sample candidates and delivery filenames; custom victory recordings and playback are not included yet.

Bundles do not grant Crowns, XP, roles, abilities, improved matchmaking or win chances. The current Crown catalog, earning rates and quarterly free progression track remain available. Subscriptions, paid Crown top-ups and season passes are not implemented.

## Stripe configuration

Set the following server environment values, also listed in `.env.example`:

```dotenv
STRIPE_SECRET_KEY=your_account_secret_key
STRIPE_WEBHOOK_SECRET=your_whoschanting_endpoint_signing_secret
STRIPE_PRICE_FOUNDERS_PACK=price_...
STRIPE_PRICE_MOONLIT_COVEN=price_...
STRIPE_PRICE_HARVEST_FESTIVAL=price_...
STRIPE_AUTOMATIC_TAX=false
```

Create separate active **one-time EUR Prices** for 799, 499 and 499 cents. These must match `config/payments.php`; checkout checks Stripe's price, currency and billing type before creating a session. Configure tax behavior on those Prices and enable `STRIPE_AUTOMATIC_TAX=true` if Stripe Tax is configured for the account. Stripe Checkout displays the final total. Set `APP_URL` to the correct public HTTPS origin for redirects. The browser receives neither secret keys nor Price IDs. A bundle's purchase button stays unavailable until its Price ID, account key and webhook secret are configured; previews remain available.

Register a dedicated event destination at `https://YOUR_GAME_DOMAIN/stripe/webhook` for:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `charge.refunded`
- `charge.dispute.created`

Use this endpoint's own `whsec_...` signing secret, not Geniousverse's endpoint secret. For local testing, forward those events with Stripe CLI to `http://127.0.0.1:8000/stripe/webhook`, use the CLI's signing secret and test-mode keys/Prices. Complete a test Checkout payment and verify one bundle unlock, retry delivery, delayed confirmation and a full refund before enabling live purchases. See [Stripe fulfillment](https://docs.stripe.com/checkout/fulfillment) and [webhook signing](https://docs.stripe.com/webhooks).

When reusing the Geniousverse Stripe account, deploy the compatibility guard added to `apps/GeniousverseApp/app/Http/Controllers/Billing/StripeBillingWebhookController.php` in the adjacent Geniousverse repository. It verifies the signature and then ignores events tagged `metadata.source_app=whoschanting`, so the existing subscription handler does not reject game checkouts. The game similarly ignores unrelated checkout events. The two applications keep separate users, orders and webhook endpoints; there is no shared subscription or login integration. No Stripe dashboard settings are changed by this implementation. The existing account key can be reused, but each endpoint needs its own signing secret.

## Payment lifecycle

Checkout additionally requires `digital_content_consent` and the current `purchase_policy_version`. Confirmations, match-use records and repeated-refund review are described in [purchase history](purchase-history.md).

`POST /account/store/checkout` accepts a bundle ID, explicit terms acceptance, and the current terms version. A durable UUID order snapshots its Price, amount, currency, cosmetics and exact Checkout parameters, with a separate legal acceptance record. Requests retry with the same Stripe idempotency key; an open session is reused, a processing payment blocks another payment for that bundle, and owned bundles cannot be purchased again. Orders without a recorded session older than 23 hours require support verification instead of risking reuse beyond Stripe's idempotency retention window. See [public policies and support](legal-support.md) for consent and withdrawal handling.

The success redirect does not grant ownership. Signed events and the authenticated `GET /account/store/status?session_id=...` endpoint retrieve the Stripe session and verify its order metadata, payment status, line item and price. Status reads are scoped to the current verified account. The store polls briefly and offers manual refresh for delayed payments. Repeated events preserve one ownership grant and its original paid timestamp.

`paid_orders` contains the entitlement source and payment references; only orders with status `paid` unlock their snapshot cosmetics. Full refunds and disputes revoke those entitlements, and late completion events cannot restore them. Partial refunds preserve the bundle. Dispute resolution currently requires support review; a won dispute does not automatically restore the order. Refunds also sanitize saved paid selections on the next profile read or room appearance snapshot. Active matches retain their original cosmetic snapshots. Retired bundles retain previously purchased cosmetics; keep their renderer definitions and cosmetic IDs stable.

## Deployment and checks

Run `php artisan migrate`, rebuild with `npm run build`, refresh cached configuration and restart long-running game and queue workers. The paid-order migration must run before the updated profile/store or game code is served. There is no new scheduler task. Configure Stripe as above to enable payments.

Feature coverage in `CosmeticStoreTest`, `ProgressionTest`, `CrownCooldownTest`, and `RoomNetworkTest` checks eligibility, real match awards, retries, rollback, insufficient funds, server pricing, account isolation, permanent ownership, wardrobe validation, and account deletion. The standard SQLite feature suite checks transaction behavior and retry semantics; it does not prove production MySQL row-lock behavior.

`StripeCheckoutTest` covers payment verification, ownership, shared-account isolation, duplicates, pending payments, full/partial refunds, disputes and retired bundles using mocked Stripe responses. `MatchCosmeticsTest` runs a match through start, final vote, victory, all viewers and rematch. JavaScript tests cover event deduplication, ordering, bounded Three.js resources and reduced motion. Browser checks use actual Vue components and WebGL with fixture APIs; they do not replace a Stripe test-mode payment.
