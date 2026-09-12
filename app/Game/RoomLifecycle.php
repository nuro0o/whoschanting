<?php

namespace App\Game;

use App\Models\GameRoom;
use Carbon\CarbonImmutable;

/** Connection leases are separate from participation: polling never clears AFK. */
class RoomLifecycle
{
    /** @param array<string, mixed> $s */
    public function connect(array &$s, string $id, string $client): void
    {
        $p = &$s['players'][$id];
        unset($p['connections']['pending']);
        $p['connections'][$client] = now()->addSeconds(config('game.presence.timeout_seconds'))->toISOString();
        $p['connected'] = true;
        $p['in_room'] = true;
    }

    /** @param array<string, mixed> $s */
    public function disconnect(array &$s, string $id, string $client): void
    {
        // A short lease lets refresh/back-forward navigation attach its replacement tab.
        if (isset($s['players'][$id]['connections'][$client])) {
            $s['players'][$id]['connections'][$client] = now()->addSeconds(config('game.presence.navigation_grace_seconds'))->toISOString();
        }
    }

    /** @param array<string, mixed> $s */
    public function leave(array &$s, string $id): void
    {
        $s['players'][$id]['connections'] = [];
        $s['players'][$id]['connected'] = false;
    }

    /** @param array<string, mixed> $s */
    public function activity(array &$s, string $id): void
    {
        $s['players'][$id]['activity_phase_id'] = $s['phase_id'];
        $s['players'][$id]['missed_phases'] = 0;
        $s['players'][$id]['afk'] = false;
        $s['players'][$id]['afk_prompt_deadline'] = null;
    }

    /** Count only phases in which the player could participate.
     * @param  array<string, mixed>  $s
     */
    public function completedPhase(array &$s): void
    {
        if (! in_array($s['phase'], ['night', 'discussion', 'voting'], true)) {
            return;
        }
        foreach ($s['players'] as &$p) {
            if (! isset($p['connections']) || ! $p['alive'] || ($p['afk'] ?? false)
                || in_array($p['curse']['type'] ?? null, ['puzzle', 'mist'], true)) {
                continue;
            }
            if (($p['activity_phase_id'] ?? null) === $s['phase_id']) {
                $p['missed_phases'] = 0;

                continue;
            }
            $p['missed_phases'] = ($p['missed_phases'] ?? 0) + 1;
            if ($p['missed_phases'] >= config('game.presence.missed_phases') && ! isset($p['afk_prompt_deadline'])) {
                $p['afk_prompt_deadline'] = now()->addSeconds(config('game.presence.prompt_seconds'))->toISOString();
            }
        }
        unset($p);
    }

    /** @param array<string, mixed> $s */
    public function maintain(GameRoom $room, array &$s): void
    {
        if ($s['phase'] === 'closed') {
            return;
        }
        foreach ($s['players'] as &$p) {
            // Migrated rooms get leases when maintenance first visits them.
            if (! isset($p['connections']) && $room->maintenance_at !== null) {
                $p['connections'] = ['pending' => now()->addSeconds(config('game.presence.timeout_seconds'))->toISOString()];
            }
            if (isset($p['connections'])) {
                $p['connections'] = array_filter($p['connections'], fn (string $expiry): bool => CarbonImmutable::parse($expiry)->isFuture());
                $p['connected'] = $p['connections'] !== [];
            }
            if ($p['alive'] && isset($p['afk_prompt_deadline']) && CarbonImmutable::parse($p['afk_prompt_deadline'])->isPast()) {
                $p['afk'] = true;
                $p['afk_prompt_deadline'] = null;
            }
        }
        unset($p);

        if (! in_array($s['phase'], ['lobby', 'finished'], true)) {
            $attending = array_filter($s['players'], fn (array $p): bool => ($p['connected'] ?? true) && ! ($p['afk'] ?? false));
            if ($attending !== []) {
                unset($s['abandoned_deadline']);
            } elseif (! isset($s['abandoned_deadline'])) {
                $s['abandoned_deadline'] = now()->addSeconds(config('game.presence.abandoned_seconds'))->toISOString();
            } elseif (CarbonImmutable::parse($s['abandoned_deadline'])->isPast()) {
                $this->close($room, $s, 'The room closed after everyone was away for five minutes.');
            }
        } else {
            unset($s['abandoned_deadline']);
            $removed = false;
            foreach ($s['players'] as $id => $p) {
                if (($p['connected'] ?? true) && ! ($p['afk'] ?? false)) {
                    continue;
                }
                if (($p['in_room'] ?? true) === false) {
                    continue;
                }
                $removed = true;
                if ($s['phase'] === 'lobby') {
                    unset($s['players'][$id]);
                } else {
                    // Preserve the finished roster for recaps and rewards until rematch.
                    $s['players'][$id]['in_room'] = false;
                }
            }
            $present = array_filter($s['players'], fn (array $p): bool => ($p['in_room'] ?? true));
            if ($present === []) {
                $this->close($room, $s, 'Everyone has left the room.');

                return;
            }
            if (! isset($present[$s['host_id']])) {
                $s['host_id'] = array_key_first($present);
                $s['log'][] = $present[$s['host_id']]['name'].' is now the host.';
            }
            if ($removed && $s['phase'] === 'lobby') {
                foreach ($s['players'] as &$p) {
                    $p['ready'] = false;
                }
                unset($p);
            }
        }
    }

    /** @param array<string, mixed> $s */
    public function close(GameRoom $room, array &$s, string $reason): void
    {
        $s['phase'] = 'closed';
        $s['phase_id']++;
        $s['closed_reason'] = $reason;
        $room->deadline = null;
        $room->maintenance_at = null;
    }

    /** @param array<string, mixed> $s */
    public function schedule(GameRoom $room, array $s): void
    {
        $times = [];
        if ($s['phase'] !== 'closed') {
            if (isset($s['abandoned_deadline'])) {
                $times[] = $s['abandoned_deadline'];
            }
            foreach ($s['players'] as $p) {
                if (($p['in_room'] ?? true) === false) {
                    continue;
                }
                array_push($times, ...array_values($p['connections'] ?? []));
                if ($p['alive'] && isset($p['afk_prompt_deadline'])) {
                    $times[] = $p['afk_prompt_deadline'];
                }
            }
        }
        $room->maintenance_at = $times === [] ? null : CarbonImmutable::parse(min($times));
    }
}
