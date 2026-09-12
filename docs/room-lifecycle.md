# Leaving rooms and AFK

The room owner is called **Host** throughout the interface. Every player can use
**Leave room**. The host also has **End room**, with confirmation that it affects
everyone. Ending an unfinished match does not create a winner or award rewards.

In the lobby, leaving removes the seat and resets readiness. The next present
player in seat order becomes host. An empty lobby closes and disappears from
the public room browser.

During a match, leaving preserves the seat, role, and host until the match ends.
The same account or guest session can return using the room code. At match end,
departed and AFK seats are marked as left and hosting passes to a present player.
The finished roster remains visible for the recap; left seats are removed when
the host opens a rematch.

## Participation

Two consecutive missed participation phases trigger **Are you still here?**,
with 15 seconds to answer. Night submissions, votes (including deliberate
abstention), discussion readiness, chat, public statements, role actions, and
curse solving count as activity. Quiet discussion does not accumulate misses
across a submitted night action or vote. Banished players and players blocked
by puzzle/mist curses are exempt from missed-phase checks.

After the prompt expires, the server marks the player AFK. Phase completion
stops waiting for their submissions. Missing actions stay absent: no random
votes, chants, targets, or spent abilities are manufactured. A Last Words phase
also skips when every accused player is AFK. Returning players can press
**I'm here** or submit a valid action to resume before the current phase ends.
Heartbeats and state polling alone never clear AFK.

If everyone is disconnected or AFK for five minutes, the abandoned room closes
without a winner or rewards. Someone returning and acknowledging activity
within that window keeps it open.

## Connections and maintenance

Each mounted room screen uses its own random client ID and sends a heartbeat
every 10 seconds. Connection leases expire after 45 seconds without a heartbeat.
Closing/navigating away sends a best-effort keepalive request with a five-second
grace period. This allows a refresh or replacement tab to reconnect without
losing the seat. A second open tab preserves presence. Explicit **Leave room**
acts immediately for the whole player, including any other tabs.

Presence and phase resolution share the room's database row lock. Indexed
`maintenance_at` deadlines are processed by the existing once-per-second
`game:tick` scheduler even when nobody is polling. Ordinary heartbeat renewals
do not broadcast room updates. Presence leases and private prompt deadlines
are excluded from other players' state views.

Run the database migration before serving the updated app, and keep
`php artisan schedule:work` (or the deployment scheduler) running. Restart any
long-running scheduler workers after deploying. Existing rooms are scheduled
for presence initialization by the migration. Thresholds are configurable in
`config/game.php` under `presence`.
