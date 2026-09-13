<?php

namespace App\Game;

use App\Models\PaidOrder;
use App\Models\User;

class FactionExpansions
{
    public const ADDITIONAL = ['drowned', 'gilded-hand', 'hollow-choir', 'carnival'];

    public static function active(string $id): bool
    {
        return config('factions.'.$id.'.active', false) === true;
    }

    /** @return list<string> */
    public static function activeIds(): array
    {
        $active = [];
        foreach (array_keys(config('factions', [])) as $id) {
            if (is_string($id) && self::active($id)) {
                $active[] = $id;
            }
        }

        return $active;
    }

    /** @param array<string,mixed> $state */
    public static function available(array $state, string $id): bool
    {
        return self::active($id) && in_array($id, self::ownedIds($state), true);
    }

    /** @param array<string,mixed> $state
     * @return list<string>
     */
    private static function ownedIds(array $state): array
    {
        $accounts = array_values(array_filter(array_column($state['players'], 'user_id')));
        if ($accounts === []) {
            return [];
        }

        $ids = PaidOrder::whereIn('user_id', User::whereIn('id', $accounts)->whereNotNull('email_verified_at')->select('id'))
            ->where('status', 'paid')->pluck('bundle_id')->all();

        return array_values(array_filter($ids, 'is_string'));
    }

    /** @param array<string,mixed>|null $rules
     * @return array<string,mixed>
     */
    public static function metadata(string $id, ?array $rules = null): array
    {
        return ['id' => $id, ...array_intersect_key($rules ?? config('factions.'.$id, []),
            array_flip(['name', 'alignment', 'description', 'instructions', 'min_players', 'roles', 'goal']))];
    }

    /** @return list<array<string,mixed>> */
    public static function catalog(): array
    {
        return array_map(fn (string $id): array => self::metadata($id), self::activeIds());
    }

    /** @param array<string,mixed> $state
     * @return list<array<string,mixed>>
     */
    public static function roomCatalog(array $state): array
    {
        $owned = self::ownedIds($state);
        $selected = $state['mode_setup']['expansion'] ?? null;
        $ids = array_values(array_filter(self::ADDITIONAL, fn (string $id): bool => self::active($id) || $selected === $id));

        return array_map(fn (string $id): array => [...self::metadata($id), 'active' => self::active($id),
            'available' => self::active($id) && in_array($id, $owned, true)], $ids);
    }
}
