<?php

namespace App\Game;

use App\Models\PaidOrder;

class PaidCosmetics
{
    /** @return list<array<string,mixed>> */
    public function bundles(): array
    {
        return config('payments.bundles', []);
    }

    /** @return list<array<string,mixed>> */
    public function view(int $userId): array
    {
        $owned = PaidOrder::where('user_id', $userId)->where('status', 'paid')->pluck('bundle_id')->all();

        return array_map(fn (array $bundle): array => [
            ...array_intersect_key($bundle, array_flip(['id', 'name', 'description', 'amount', 'currency', 'cosmetics'])),
            'owned' => in_array($bundle['id'], $owned, true),
            'available' => filled(config('payments.secret_key')) && filled(config('payments.webhook_secret')) && filled($bundle['price_id']),
        ], $this->bundles());
    }

    /** @return list<array{category:string,id:string,name:string}> */
    public function ownedCosmetics(int $userId): array
    {
        return array_values(PaidOrder::where('user_id', $userId)->where('status', 'paid')->get()
            ->flatMap(fn (PaidOrder $order): array => $order->cosmetics)->all());
    }

    /** @param array<string,list<array<string,mixed>>> $catalog
     * @return array<string,list<array<string,mixed>>>
     */
    public function catalog(int $userId, array $catalog): array
    {
        $owned = collect($this->ownedCosmetics($userId))->keyBy(fn (array $item): string => $item['category'].':'.$item['id']);
        foreach ($this->bundles() as $bundle) {
            foreach ($bundle['cosmetics'] as $item) {
                $unlocked = $owned->has($item['category'].':'.$item['id']);
                $catalog[$item['category']][] = ['id' => $item['id'], 'name' => $item['name'], 'unlocked' => $unlocked,
                    'requirement' => $unlocked ? 'Owned from '.$bundle['name'] : 'Included in '.$bundle['name']];
            }
        }
        // Order snapshots preserve ownership if a bundle is later retired or changed.
        foreach ($owned as $item) {
            if (! collect($catalog[$item['category']] ?? [])->contains('id', $item['id'])) {
                $catalog[$item['category']][] = ['id' => $item['id'], 'name' => $item['name'], 'unlocked' => true, 'requirement' => 'Owned from the village store'];
            }
        }

        return $catalog;
    }

    /** Refunds and disputes must also remove equipped paid appearances on the next snapshot.
     * @param  array<string,mixed>  $equipped
     * @param  array<string,mixed>  $defaults
     * @return array<string,mixed>
     */
    public function sanitize(int $userId, array $equipped, array $defaults): array
    {
        $catalog = $this->catalog($userId, []);
        $historical = PaidOrder::where('user_id', $userId)->get()->flatMap(fn (PaidOrder $order): array => $order->cosmetics);
        foreach (['title' => 'titles', 'frame' => 'frames', 'accent' => 'accents', 'background' => 'backgrounds',
            'table' => 'tables', 'banishment' => 'banishments', 'celebration' => 'celebrations'] as $field => $category) {
            $item = collect($catalog[$category] ?? [])->firstWhere('id', $equipped[$field]);
            $wasPaid = $historical->contains(fn (array $cosmetic): bool => $cosmetic['category'] === $category && $cosmetic['id'] === $equipped[$field]);
            if (($item !== null && ! $item['unlocked']) || ($item === null && $wasPaid)) {
                $equipped[$field] = $defaults[$field];
            }
        }

        return $equipped;
    }
}
