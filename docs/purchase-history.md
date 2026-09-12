# Purchases, confirmations and use

`/account/purchases` shows only the signed-in account's orders, with pagination, item snapshots, payment totals, distinct matches used, first-use date, and refund guidance. Unverified accounts can still read their own purchases. Stripe secret identifiers and another account's orders are not included in page props. Refund help remains available for used, expired and legacy purchases.

## Consent and refunds

Paid checkout requires two unchecked acceptances: terms, and a separate immediate-supply consent and acknowledgment with the current `PurchasePolicy::VERSION`. Their exact text, versions and server timestamp are saved. Update `config/legal.php`'s terms version and separate display date when changing terms. Previously paid orders never acquire consent retrospectively.

The digital-content consent explains the statutory exception when supply begins with the necessary prior consent, acknowledgment and required confirmation. The store additionally offers a **14-day refund policy for purchases used in no more than two distinct games**. Supplying content does not by itself count as match usage for this additional policy. Used content can still qualify for statutory remedies. A usage record never causes an automatic refund rejection or a fraud finding.

The server records a pack once per match when an ordinary cosmetic is displayed at match start, its owner's table is used as host, or an equipped animation is actually triggered. Previews, wardrobe changes, lobbies, a non-host's table, and unused animations do not count. Ownership provenance is frozen privately at match start: a refunded order's existing match cannot be attributed to a later repurchase. Guests do not consume another player's purchase. The Fae Court expansion is counted for the single purchase enabling its match (host first, otherwise a seated owner), rather than every owner at the table. Legacy matches without provenance are not backfilled.

## Receipt delivery

The Purchases page shows **Request refund** only when the server reports a paid order within the 14-day window with at most two distinct match uses (0, 1 or 2). Three or more uses, expired windows and non-paid orders retain Email support. The public withdrawal form remains available for statutory claims; it does not automatically issue refunds. The more generous two-game store policy also applies to existing purchases, without rewriting their accepted terms. New receipts save the two-game policy; older queued confirmations keep their original wording.

Successful verified Stripe payment reconciliation atomically saves the purchase confirmation and enqueues a database job once. The HTML and plain-text email include item names, final Stripe total when available, reference, payment date, saved consent and policy, operator details and refund/support links. Legacy totals fall back to a clearly labeled subtotal. The artwork is an existing first-party image. Later product or policy edits do not rewrite the saved receipt.

Run a supervised `php artisan queue:work database --tries=5`, even if game broadcasts use another queue. Leave `DB_QUEUE_CONNECTION` unset so queue insertion participates in the application's database transaction. Configure production mail delivery and monitor failed jobs.

`receipt_queued_at` and `receipt_sent_at` are separate: the latter is set only after the mailer accepts the send. Neither establishes customer receipt or timely legal confirmation. Content can unlock before asynchronous email delivery, so **do not assume the withdrawal exception is established merely because a box was checked or a game was played**. Review confirmation timing and delivery failures alongside consent. The UI sends used purchases to support review rather than declaring legal rights lost. The usual mail crash/retry boundary can produce a duplicate receipt but never a duplicate order or charge.

## Repeated refunded purchases

After two refunded orders for the same account and pack that have not already been reviewed, another checkout for that pack requires support review before contacting Stripe. This does not remove refund rights, affect other packs, or label the customer fraudulent. Inspect a purchase with:

```sh
php artisan purchases:review ORDER_UUID
```

The output includes consent, email status, use count and prior refunds. A matching checkout email and order reference in the withdrawal form also adds context to the **support-only** acknowledgment. Customer responses do not expose the review. Assess usage at the time the request was made; later use or a later policy change must not retrospectively invalidate a valid withdrawal.

After reviewing, allow repurchase with:

```sh
php artisan purchases:allow-repurchase ORDER_UUID
```

This dates reviewed refunded orders for that account/pack without changing their status or erasing history. Two further unreviewed refunds trigger another review. Refunds remain an operator action in Stripe; signed refund events revoke entitlements.

Use records remain linked to purchases and cascade on account deletion. The control does not infer identity from IP addresses, fingerprint browsers or block account recreation. See [privacy and support](legal-support.md) for retention and operator details.

## Deployment and checks

Run `php artisan migrate --force` for the nullable receipt/review fields and usage table, rebuild assets, refresh cached configuration, and restart long-running game and database queue workers. No historical consent or use is backfilled. Keep the existing scheduler supervised. Confirm a test-mode purchase, receipt delivery, match use and refund on the deployed domain before relying on the live workflow.

Feature tests cover separate consent, account isolation, real match use/rematches, animation triggers, refund/repurchase provenance, receipt snapshots and transactional queue insertion, repeat-refund review and support-only context. Browser checks use actual Vue components and rendered Blade with fixture APIs. Tests do not send live SMTP email, charge Stripe, or prove production database concurrency or legal eligibility.

Legal references: [ACM in-game purchase guidance](https://consument.acm.nl/veilig-online/online-games) and [EU distance-selling guidance](https://europa.eu/youreurope/business/selling-in-eu/selling-goods-services/ecommerce-distance-selling/index_en.htm).
