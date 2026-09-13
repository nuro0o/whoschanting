# The Fae Court

The first paid faction expansion adds one randomly dealt **Fae Broker** to Classic or Classic Illusions rooms with 7–15 players. It replaces one Townsperson; the Oracle and Cult counts are preserved. Other modes keep their existing rosters. Custom cannot inject the paid role.

Any seated, verified account with a paid `fae-court` order unlocks the expansion for the whole room, including guest hosts and guest players. The host enables it in lobby mode settings, clearing readiness so everyone can review the roster. Ownership is checked when enabling and again when starting. Refunds, disputes and an owner leaving prevent future starts if no other owner remains. A started match keeps its snapshotted rules and bargains through disconnects or refunds. Ownership never influences the random role assignment.

## A night at the Court

The Broker submits either one structured offer or Keep watch. Offers are delivered after night resolution, followed by a 25-second private bargain window before discussion. The window always runs for its full duration, even when there was no offer, it was disrupted, or the recipient already replied. Other players cannot infer a response from phase timing or public readiness.

Only the recipient can accept or decline, once, during that window. The sender is anonymous; there is no private free-text channel. Disruption prevents delivery. A dead recipient, promise target or ballot-report target voids an offer; a Broker's simultaneous death does not undo their otherwise effective night action. Offers count as visits and can be detected by existing investigative roles. An ordinary Oracle reading of a Broker says Fae; a veil makes that reading Cult. A Counterfeiter can still forge Town or Cult. A Broker must solve Misdirection before offering, or Keep watch.

| Bargain            | Gift on acceptance                                                                                                                                         | Promise due that day                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Thorn’s Protection | Immunity to all new curses next night. Other abilities still work. No benefit if the match ends before then.                                               | Vote for the named player. The actual ballot, including redirection, counts.   |
| A Borrowed Voice   | After voting closes, privately learn the actual ballot of a living player named by the Broker in the offer, other than the recipient. No role is revealed. | Explicitly abstain. Missing the deadline does not count.                       |
| Lantern Secret     | Learn whether another player visibly visited the named accusation target that night. Hidden visits stay hidden; the recipient's own visit is excluded.     | Publicly accuse the named player during discussion. The final vote may differ. |

Curses pause during the private response window, so cursed players can answer offers. Unanswered offers expire without a gift. Accepting does not change a role or faction. Players keep accepted gifts even when they break promises; broken promises cause no additional punishment. Promises settle after the vote, before the victory check. A successful partner can earn only one seal. Failed partners can receive another offer on a later night.

A Borrowed Voice reports the resolved ballot after any redirection, distinguishing a vote, explicit abstention and a missing ballot. Only the recipient gets the report, in their Journal, even if they or the named voter are banished by that vote. Neither the Broker nor other players receive the result during the match. Accepted reports are delivered even for broken promises; no report is delivered if the match ends before that day's vote. New Voice offers do not cleanse curses or hauntings and never add voting power. Previously issued Voice offers without a ballot-report target retain their original cleansing gift.

## Shared victories

The Court needs three fulfilled bargains with different partners over at least two rounds, and at least one fulfilled partner must belong to the eventual winning Town or Cult. These thresholds are provisional playtest settings in `config/fae.php`, snapshotted at start. One offer per night means the default three-seal objective takes at least three nights.

Earning seals never ends a match. Existing Town/Cult end conditions determine the main winner. A living Fae prevents the one-Town/one-Cult final-pair shortcut; eliminating all Town, banishing all Cult, or surviving the ritual's final vote still resolves the main outcome. The Court can share either main faction's victory. Banished Fae and banished fulfilled partners still count.

The public sees the seal total. Until the match ends, only the Court and each individual recipient see their relevant bargains, with no sender identity or unaccepted Lantern clue. The recap reveals offers, senders, recipients, promises and outcomes. Unresolved accepted promises become void if the match ends before their vote.

`winner` remains the primary Town/Cult result for existing statistics and predictions. The additive `winners` array records all winning factions in room responses and archived recaps. Shared winners receive the normal win XP, Crowns and seasonal win credit, and participate in celebration selection. Fae wins do not increment Town or Cult win counters. Rewards remain idempotent. Rematches clear bargains, protection, winners and the entitlement snapshot.

## Checkout and deployment

The permanent `fae-court` product uses the existing server-verified one-time Stripe checkout, order status, consent, receipt and refund flows. It grants no cosmetics. The one-time price defaults to **€2.99** and can be configured:

```dotenv
STRIPE_PRICE_FAE_COURT=
FAE_COURT_PRICE_CENTS=299
```

Create a one-time EUR Stripe price matching `FAE_COURT_PRICE_CENTS`, then set its ID in `STRIPE_PRICE_FAE_COURT`. Existing Stripe secret and webhook configuration are also required. Until configured, the store shows Coming soon. Client-supplied prices and ownership are never trusted.

Build frontend assets and restart long-running application processes after deploying. Apply pending migrations for the accompanying purchase receipt/usage system if not already installed; the faction itself uses existing room JSON, order records and recap JSON. The usage system records the expansion at match start against one supplying owner's paid order, not every owner present. Do not remove pending receipt/usage migrations while deploying the shared purchase changes.

For playtesting, watch acceptance rates, how often each gift is useful, Fae co-win frequency, Town/Cult win rates, and whether three seals is achievable before early banishments end the match. The initial restriction to Classic variants keeps these observations comparable.
