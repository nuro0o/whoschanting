# Stories around the table

The village record, predictions, host controls, and role practice build on the existing game rules. They use the room's stored JSON and existing match archives; no database migration is required. Rebuild frontend assets and restart long-running application processes when deploying.

## Public claims and discussion

During each discussion, every living player may publish one role claim with a short statement and one answer to the shared discussion question. Both are public, dated, and immutable for that match. Any role can claim any role or bluff in an answer. These statements are never checked against secret information during play. Optional player references make stories easier to compare. The history survives subsequent rounds and is included in the final archive.

The shared question rotates each day and is identical for both factions. It supplies a starting point for conversation, including for Townspeople without a night ability. Answering grants no powers, information, XP, or voting advantage. It does not consume an ability or mark the player ready for voting.

Existing curse restrictions still apply. A blocking curse prevents posting, and Mind Mist hides the village record until it clears. Banished players can read public statements but cannot add them.

Every living player can agree to extend the current discussion. Unanimous agreement adds 30 seconds to its existing deadline, at most once per discussion. Approvals do not themselves count as readiness. When the extension is granted, previous ready-for-voting statuses reset, so players receive the agreed time unless they all ready up again. An expired phase cannot be extended retroactively.

## Banished predictions

After elimination, a player can seal one prediction of the cultists currently alive and the eventual winning faction. The prediction is final. It stays private until the match ends; no correctness feedback or extra role information is supplied during play.

At the reveal, predictions are compared with the cultists alive at submission time, even if some were later eliminated. The result includes correct picks, incorrect picks, an exact-set match, and winner accuracy. Cult players' predictions are labelled informed because they already know their team. Predictions do not award XP or alter match outcomes.

## Host controls

The current host can transfer control to another occupied seat, including during a match. The new host immediately receives host permissions. In the lobby, the host can remove another seat; all remaining players must ready up again. Removal is not a permanent ban, and no active-match seat can be removed. These actions require confirmation in the UI and are validated by the server.

## The reveal

The final recap has a manually stepped retelling above the detailed round records. It shows archived ritual changes, affected forged readings, prevented steps, disruptions, role claims compared with revealed roles, recorded ballots, and the actual ending. Role mismatches are presented as facts without inferring a player's intent. Legacy recaps with missing records retain their available details.

## Practice and playtesting

The `/tutorial` chooser preserves the original guided Oracle round and adds Counterfeiter, Tracker, and Bellkeeper exercises. Choices change the scripted outcomes. Replay reveals why a forged reading overrides a veil, why an attempted visit is not proof of success, and why a bell only prevents newly earned progress. Practice is local to the page and awards no progression.

After a match, each player may optionally answer “How involved did you feel?” and leave a short note. One response is saved per seat and match. Individual responses stay private; the archive retains the actual role for analysis after a rematch. The operator can inspect aggregate counts with:

```sh
php artisan game:feedback
php artisan game:feedback --rules-version=vigilante-v1
```

The report shows engaged, mixed, and waiting counts by role. Voluntary feedback is not a controlled playtest: compare similar groups, player counts, and rules before adjusting balance. In particular, ask Townsperson players whether the prompts helped them participate and observe whether optional forms crowd out conversation. Real group playtesting is still needed.
