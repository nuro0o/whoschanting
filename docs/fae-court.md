# The Fae Court

The first paid faction expansion deals a **Fae Broker** and **Fae Collector** in Classic or Classic Illusions rooms with 7 to 15 players. The Broker replaces one Townsperson. The Collector replaces an Acolyte, or the Dreamweaver in 7-8-player Illusions rooms with no spare Acolyte. The Oracle remains. Other modes cannot deal either paid role.

Any seated, verified account with a paid `fae-court` order unlocks the expansion for the whole room, including guest hosts and guest players. The host enables it in lobby mode settings, clearing readiness so everyone can review the roster. Ownership is checked when enabling and again when starting. Refunds, disputes and an owner leaving prevent future starts if no other owner remains. A started match keeps its snapshotted rules and bargains through disconnects or refunds. Ownership never influences the random role assignment.

## A night at the Court

The Broker submits either one structured offer or Keep watch. Offers are delivered after night resolution, followed by a 25-second private bargain window before discussion. The window always runs for its full duration, even when there was no offer, it was disrupted, or the recipient already replied. Other players cannot infer a response from phase timing or public readiness.

Only the recipient can accept or decline, once, during that window. The sender is anonymous; there is no private free-text channel. Disruption prevents delivery. A dead recipient, promise target or ballot-report target voids an offer; a Broker's simultaneous death does not undo their otherwise effective night action. Offers count as visits and can be detected by existing investigative roles. An ordinary Oracle reading of a Broker says Fae; a veil makes that reading Cult. A Counterfeiter can still forge Town or Cult. A Broker must solve Misdirection before offering, or Keep watch.

| Bargain            | Gift                                                                                                                                                       | Promise due that day                                                                                          |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Thorn’s Protection | Immunity to all new curses next night. Other abilities still work. No benefit if the match ends before then.                                               | Vote for the named player. The actual ballot, including redirection, counts.                                  |
| A Borrowed Voice   | After voting closes, privately learn the actual ballot of a living player named by the Broker in the offer, other than the recipient. No role is revealed. | Explicitly abstain. Missing the deadline does not count.                                                      |
| Moonlit Passage    | Only after keeping the promised vote, hide the recipient's outgoing visit from the Lamplighter and Tracker next night. Other abilities still affect them.  | Vote for the named player. They do not need to be banished. The actual ballot, including redirection, counts. |

Curses pause during the private response window, so cursed players can answer offers. Unanswered offers expire without a gift. Accepting does not change a role or faction. Thorn’s Protection and A Borrowed Voice keep their gifts even if a promise is broken. Moonlit Passage requires the promised vote: a different vote, abstention, missing ballot or declined offer earns neither the passage nor a seal. Promises settle after the vote, before the victory check. A successful partner can earn only one seal. Failed partners can receive another offer on a later night.

Moonlit Passage conceals only next night's outgoing visit. It does not protect from role readings, curses, attacks or disruption, and does not help if the match ends before that night. The recipient privately sees when the passage is earned and active. Offers never reveal the Broker's identity during play. Normal investigations can still identify the Broker, and the final recap reveals roles and bargain senders. Previously submitted Lantern Secret offers retain their original visit clue and public-accusation promise; new offers use Moonlit Passage instead.

A Borrowed Voice reports the resolved ballot after any redirection, distinguishing a vote, explicit abstention and a missing ballot. Only the recipient gets the report, in their Journal, even if they or the named voter are banished by that vote. Neither the Broker nor other players receive the result during the match. Accepted reports are delivered even for broken promises; no report is delivered if the match ends before that day's vote. New Voice offers do not cleanse curses or hauntings and never add voting power. Previously issued Voice offers without a ballot-report target retain their original cleansing gift.

## The Collector

The two Fae privately know each other and share bargain history. Once per match, the Collector chooses a declined, expired or broken bargain from an earlier night and offers it to a different living outsider. Its gift and promise are copied by the server; the Collector cannot change either. The original named promise/ballot target must still be alive and cannot become the new recipient. Legacy Lantern and cleansing Voice offers cannot be renewed.

Confirmation spends the renewal, including if disruption prevents it or a simultaneous death voids it. Invalid submissions do not spend it. No renewal is consumed by keeping watch. A broker's death does not prevent renewing their failed offers. Each recipient may receive only one Court offer that night, and a previously fulfilled partner cannot earn another seal. Recipients see an ordinary anonymous offer, without the source bargain or sender. Visits remain detectable and ordinary investigations can identify either Fae. Rematches restore the renewal; existing running one-Broker games retain their dealt roles.

## Shared victories

The Court needs three fulfilled bargains with different partners over at least two rounds, and at least one fulfilled partner must belong to the eventual winning Town or Cult. These thresholds are provisional playtest settings in `config/fae.php`, snapshotted at start. The Broker offers once per night; the Collector can add one renewal per match. The default three-seal objective can therefore be completed over two nights, but still needs three different outsiders, including someone on the winning side.

Earning seals never ends a match. Existing Town/Cult end conditions determine the main winner. A living Fae prevents the one-Town/one-Cult final-pair shortcut; eliminating all Town, banishing all Cult, or surviving the ritual's final vote still resolves the main outcome. The Court can share either main faction's victory. Banished Fae and banished fulfilled partners still count.

The public sees the seal total. Until the match ends, only the Court and each individual recipient see their relevant bargains, with no sender identity or private ballot reports disclosed to others. The recap reveals offers, senders, recipients, promises and outcomes. Unresolved accepted promises become void if the match ends before their vote.

`winner` remains the primary Town/Cult result for existing statistics and predictions. The additive `winners` array records all winning factions in room responses and archived recaps. Shared winners receive the normal win XP, Crowns and seasonal win credit, and participate in celebration selection. Fae wins do not increment Town or Cult win counters. Rewards remain idempotent. Rematches clear bargains, protection, winners and the entitlement snapshot.

## Checkout and deployment

The permanent `fae-court` product uses the existing server-verified one-time Stripe checkout, order status, consent, receipt and refund flows. It grants no cosmetics. The one-time price defaults to **€2.99** and can be configured:

```dotenv
STRIPE_PRICE_FAE_COURT=
FAE_COURT_PRICE_CENTS=299
FAE_COURT_ACTIVE=false
```

Create a one-time EUR Stripe price matching `FAE_COURT_PRICE_CENTS`, then set its ID in `STRIPE_PRICE_FAE_COURT`. Existing Stripe secret and webhook configuration are also required. Set `FAE_COURT_ACTIVE=true` when ready to release. While false, the faction is hidden from the welcome guide and store, checkout is blocked, and no new room can enable or start it. Previously configured lobbies can turn it off. Already-started matches, purchase records and payment/refund reconciliation continue normally. If active but Stripe is not configured, the store shows Coming soon. Client-supplied prices and ownership are never trusted.

Activation flags live in `config/factions.php`. Every paid faction must have its own explicit active flag; unknown faction products are inactive by default. Cosmetic packs and the original Town/Cult factions are unaffected. After changing production environment flags, rebuild Laravel's config cache with `php artisan config:cache` and restart long-running application processes. Local uncached configuration reads the environment on the next request; reload the page to refresh its catalog.

Build frontend assets and restart long-running application processes after deploying. Apply pending migrations for the accompanying purchase receipt/usage system if not already installed; the faction itself uses existing room JSON, order records and recap JSON. The usage system records the expansion at match start against one supplying owner's paid order, not every owner present. Do not remove pending receipt/usage migrations while deploying the shared purchase changes.

For playtesting, watch acceptance rates, how often each gift is useful, Fae co-win frequency, Town/Cult win rates, and whether three seals is achievable before early banishments end the match. The initial restriction to Classic variants keeps these observations comparable.
