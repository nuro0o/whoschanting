# Room expansions

Each pack is an independent paid room unlock. One seated, verified account with a paid order supplies the expansion for everyone. The host selects it; roles are randomly dealt regardless of ownership. Ownership and release status are checked again when starting. The supplying order is recorded as used once; other owners' purchases are untouched. Refunds, owner disconnections and later configuration changes do not remove an expansion from an already running match. Rematches require a valid owner again and clear all objectives and limited abilities.

## Release switches and pricing

All five switches default to `false`, and are currently false in the local `.env` and `.env.example`:

```dotenv
FAE_COURT_ACTIVE=false
DROWNED_ACTIVE=false
GILDED_HAND_ACTIVE=false
HOLLOW_CHOIR_ACTIVE=false
CARNIVAL_ACTIVE=false
```

Inactive packs are omitted from the welcome guide, store and new lobby options. Forged checkout/start requests are rejected. Existing selected lobbies show an unavailable selection that the host can remove. Previous purchases remain recorded; pending payments can still reconcile safely after a switch is disabled.

For a release, configure the matching `STRIPE_PRICE_<PACK>` one-time EUR price, set `<PACK>_PRICE_CENTS` to its amount, then set `<PACK>_ACTIVE=true`. Pack prefixes are `FAE_COURT`, `DROWNED`, `GILDED_HAND`, `HOLLOW_CHOIR` and `CARNIVAL`. Fae stays at **299 cents**. The four larger packs have **499-cent draft defaults**, individually editable before release. Stripe price IDs remain empty until configured. Existing payment verification rejects price/currency mismatches.

After changing production environment settings, rebuild Laravel's configuration cache (`php artisan config:cache`) and restart long-running workers. These server-side switches do not require a frontend rebuild.

## Room rules

Choose one expansion alongside Town and Cult in Classic or Classic Illusions. Fae needs 7–15 players; Drowned, Gilded Hand and Carnival need 9–15; Hollow Choir needs 11–15. Custom, Hard, Chaos and Paranoia do not deal paid roles. Every pack supplies two roles. Fae replaces one Townsperson and one Acolyte (or the Dreamweaver in smaller Illusions rooms). Drowned, Gilded Hand and Carnival replace one Townsperson and one Acolyte. Choir replaces two Townspeople, preserving the Cult's chanting capacity. The Oracle remains.

Town/Cult still determine when the match ends. An expansion can **share either faction's victory**, with normal shared-win rewards and recaps. Completing an expansion objective does not end the match early. Objectives and rules are snapshotted at start. Thresholds and roster substitutions live in `config/factions.php`; these are initial balance settings that still need group playtesting.

The existing [Fae rules](fae-court.md) remain: anonymous voluntary bargains, independent seals and a hidden Broker. Moonlit Passage requires the promised actual ballot before granting concealed outgoing visits the following night.

## The Drowned

- **Tidecaller:** secretly marks one unmarked living outsider each night.
- **Ferryman:** moves an existing mark to another unmarked living outsider. Both endpoints count as visits.
- **Counterplay:** every outsider can spend their normal night action to sound for a mark or cleanse a living player, including themselves. Sounding privately reports the final mark status. Cleansing removes a mark and prevents new/moved marks that night. Exorcism also removes an existing mark, without future protection.
- **Tide:** after every third vote, three living marked outsiders secure the Drowned's co-win. A banished marked player is excluded. Marks do not change allegiance or eliminate anyone.

Cleansings resolve first, new marks second, ferries third, soundings last. A new mark at a ferry's intended destination makes that move fail; submission order does not change this. Only Drowned players receive marked identities. Everyone sees aggregate progress and the tide announcement. Once earned, the shared victory remains secured even if marks or Drowned members later disappear.

## The Gilded Hand

- **Lifter:** names a holder and relic to steal; an incorrect holder produces no transfer.
- **Appraiser:** privately locates one relic after transfers resolve.
- Three relics start with distinct outsiders: Silver Key, Glass Eye and Sun Coin. Any holder may hand one relic to another living player instead of their ordinary ability or chant.
- Conflicting valid moves of the same relic all fail. Other moves resolve independently. Transfers notify only the old and new holders; theft does not disclose the thief to the victim.
- Departing holders drop relics to random living outsiders, preferring outsiders over remaining Gilded members. Relics cannot remain permanently inaccessible on a dead holder while living players remain.
- **Victory:** all three relics must be held by living Gilded members when Town or Cult wins. Earlier possession does not secure victory.

Players see their own inventory; Gilded teammates also see their shared inventory. Only the Appraiser's private reports expose outside holders before the recap. Aggregate collection progress is public. Targeted steals and handoffs leave ordinary visits.

## The Hollow Choir

- **Cantor:** siphons one newly earned ritual step if the Cult earns at least two that night.
- **Resonant:** privately checks whether someone effectively chanted, or once per match amplifies an effective Cantor siphon by one when at least three steps were earned.
- The Cult always retains at least one newly earned step. Existing progress is never stolen. Bell prevention applies before siphoning. Disruption can stop either Choir action.
- Resonance is spent on submission, even if disrupted or no echo can be gathered.
- **Victory:** three echoes secure a shared win. The Cult must survive and continue chanting long enough to feed the Choir.

Siphoning is announced as an aggregate count without names; listening reveals no allegiance. Choir players privately know each other.

## The Carnival

Night choices commit the act for that day's discussion and vote. Players can keep watch instead. Unsubmitted or disrupted plans do not score.

- **Harlequin, Taunt:** another player publicly accuses them; they submit a ballot and survive the vote.
- **Harlequin, Tie:** they actually vote for one of at least two named candidates tied at the highest tally, and nobody is banished. Abstention must not lead those candidates.
- **Augur, Foretell:** their sealed named target is banished that day. They cannot change the prediction during discussion.
- **Victory:** complete all three different acts across at least two days. Repeating a completed act earns nothing. The act choice and named prediction are private; completed act types and shared progress are public.

Actual ballots, including Misdirection's effect, determine vote-based objectives. Predictions never force anyone's ballot. Earned acts remain if a Carnival member is subsequently banished.

## Privacy and validation

All expansion actions use the ordinary action deadline, identity checks, curse blocking and disruption pipeline. An expansion/counterplay action replaces the actor's normal night action: no free investigation, chant or second ability. A targeted expansion action requires breaking Misdirection first; choices without targets remain usable. Irrelevant targets, relic IDs, secondary targets and attempts to combine abilities are rejected.

Private teammates, marks, inventories, reports and sealed plans are sent only to their authorized viewer; the frontend's reveal control also hides them on a shared screen. Public events contain no hidden faction identities. The completed-match recap includes the full expansion event history and final relic locations. Match archives and account rewards support the additional winning alignments without duplicating rewards.
