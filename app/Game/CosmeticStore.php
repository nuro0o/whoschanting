<?php

namespace App\Game;

use App\Models\PlayerProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CosmeticStore
{
    /** @return list<array{id: string, category: string, cosmetic_id: string, name: string, description: string, price: int}> */
    private function items(): array
    {
        return config('store.items');
    }

    /** @return list<string> */
    public function owned(int $userId): array
    {
        return array_values(DB::table('store_purchases')->where('user_id', $userId)->pluck('item_id')
            ->map(fn (string $id): string => $id)->all());
    }

    /** @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function view(int $userId, array $profile): array
    {
        $owned = $this->owned($userId);
        $balance = (int) $profile['coins'];
        $cooldown = isset($profile['crown_cooldown_until']) ? CarbonImmutable::parse($profile['crown_cooldown_until']) : null;

        return [
            'currency' => config('store.currency'), 'balance' => $balance, 'lifetime_earned' => (int) $profile['coins_earned'],
            'crown_cooldown_until' => $cooldown?->isFuture() ? $cooldown->toISOString() : null,
            'rewards' => [
                'per_player' => (int) config('store.coins_per_player'),
                'small_game_max_players' => (int) config('store.small_game_max_players'),
                'small_game_win' => (int) config('store.small_game_win_coins'),
                'large_game_win' => (int) config('store.large_game_win_coins'),
            ],
            'items' => array_map(fn (array $item): array => [...$item,
                'owned' => in_array($item['id'], $owned, true), 'affordable' => $balance >= $item['price'],
            ], $this->items()),
            'recent_transactions' => DB::table('coin_transactions')->where('user_id', $userId)->latest('id')->limit(10)
                ->get(['id', 'kind', 'amount', 'balance_after', 'item_id', 'created_at'])->map(fn (object $entry): array => [
                    'id' => (int) $entry->id, 'kind' => $entry->kind, 'amount' => (int) $entry->amount,
                    'balance_after' => (int) $entry->balance_after, 'item_id' => $entry->item_id,
                    'created_at' => CarbonImmutable::parse($entry->created_at, config('app.timezone'))->toISOString(),
                ])->all(),
        ];
    }

    /** Called only within the match transaction with this profile locked. */
    public function rewardLocked(PlayerProfile $profile, string $matchId, bool $won, int $playerCount): int
    {
        $winBonus = $playerCount <= (int) config('store.small_game_max_players')
            ? (int) config('store.small_game_win_coins') : (int) config('store.large_game_win_coins');
        $amount = $playerCount * (int) config('store.coins_per_player') + ($won ? $winBonus : 0);
        $profile->coins += $amount;
        $profile->coins_earned += $amount;
        $this->record($profile, 'match_reward', 'match:'.$matchId, $amount);

        return $amount;
    }

    /** Called only within a transaction with this profile locked, shared with match awards and customization. */
    public function purchaseLocked(PlayerProfile $profile, string $itemId): void
    {
        $item = collect($this->items())->firstWhere('id', $itemId);
        if ($item === null) {
            throw ValidationException::withMessages(['item_id' => 'This cosmetic is not available in the store.']);
        }
        // A retry (or another device) sees the same permanent entitlement and never pays twice.
        if (DB::table('store_purchases')->where('user_id', $profile->user_id)->where('item_id', $itemId)->exists()) {
            return;
        }
        if ($profile->coins < $item['price']) {
            throw ValidationException::withMessages(['item_id' => 'You need more Crowns to unlock this cosmetic. Play a match to earn more.']);
        }
        $profile->coins -= $item['price'];
        $profile->save();
        DB::table('store_purchases')->insert(['user_id' => $profile->user_id, 'item_id' => $itemId, 'price' => $item['price'],
            'created_at' => now(), 'updated_at' => now()]);
        $this->record($profile, 'purchase', 'purchase:'.$itemId, -$item['price'], $itemId);
    }

    private function record(PlayerProfile $profile, string $kind, string $reference, int $amount, ?string $itemId = null): void
    {
        DB::table('coin_transactions')->insert(['user_id' => $profile->user_id, 'kind' => $kind, 'reference' => $reference,
            'item_id' => $itemId, 'amount' => $amount, 'balance_after' => $profile->coins, 'created_at' => now(), 'updated_at' => now()]);
    }
}
