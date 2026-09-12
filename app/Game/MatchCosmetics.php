<?php

namespace App\Game;

use Illuminate\Support\Str;

/** Cosmetic snapshots never participate in game rules or phase deadlines. */
class MatchCosmetics
{
    /** @param array<string, mixed> $player */
    public static function choice(array $player, string $slot): string
    {
        $allowed = match ($slot) {
            'table' => ['classic', 'founders_oak', 'moonlit', 'harvest'],
            'banishment' => ['classic', 'gilded_vortex', 'lunar_rift', 'ember_spiral'],
            'celebration' => ['classic', 'crownfall', 'moonrise', 'lantern_festival'],
            default => ['classic'],
        };
        $value = $player['customization'][$slot] ?? 'classic';

        return in_array($value, $allowed, true) ? $value : 'classic';
    }

    /** @param array<string, mixed> $state */
    public static function banish(array &$state, string $playerId): void
    {
        self::append($state, 'banishment', $playerId);
    }

    /** @param array<string, mixed> $state */
    public static function celebrate(array &$state): void
    {
        if (! in_array($state['winner'] ?? null, ['town', 'cult'], true)) {
            return;
        }
        foreach ($state['cosmetic_events'] ?? [] as $event) {
            if ($event['kind'] === 'celebration') {
                return;
            }
        }
        // Every member of the winning faction gets exactly one entry, alive or dead.
        $winners = array_keys(array_filter($state['players'], fn (array $player): bool => ($player['alignment'] ?? null) === $state['winner']));
        if ($winners !== []) {
            self::append($state, 'celebration', $winners[random_int(0, count($winners) - 1)]);
        }
    }

    /** @param array<string, mixed> $state */
    private static function append(array &$state, string $kind, string $playerId): void
    {
        $state['cosmetic_events'][] = [
            'id' => (string) Str::uuid(), 'kind' => $kind, 'player_id' => $playerId,
            'effect' => self::choice($state['players'][$playerId], $kind),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function view(array $state): array
    {
        return [
            'table' => $state['cosmetic_table'] ?? self::choice($state['players'][$state['host_id']] ?? [], 'table'),
            'events' => array_values(array_filter($state['cosmetic_events'] ?? [], fn (array $event): bool => $event['kind'] === 'banishment' || $state['phase'] === 'finished')),
        ];
    }
}
