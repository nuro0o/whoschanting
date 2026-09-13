<?php

namespace App\Game;

use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaidPackUsage
{
    private const SLOTS = ['title' => 'titles', 'frame' => 'frames', 'accent' => 'accents', 'background' => 'backgrounds', 'table' => 'tables', 'banishment' => 'banishments', 'celebration' => 'celebrations'];

    /** Snapshot provenance at match start so an old match never charges usage to a later repurchase.
     * @param  array<string,mixed>  $state
     */
    public function start(array &$state): void
    {
        $state['paid_cosmetic_orders'] = [];
        foreach ($state['players'] as $playerId => $player) {
            if (! isset($player['user_id'])) {
                continue;
            }
            $orders = PaidOrder::where('user_id', $player['user_id'])->where('status', 'paid')->orderBy('id')->lockForUpdate()->get();
            foreach (self::SLOTS as $slot => $category) {
                $selected = $player['customization'][$slot] ?? null;
                foreach ($orders as $order) {
                    if (collect($order->cosmetics)->contains(fn (array $item): bool => $item['category'] === $category && $item['id'] === $selected)) {
                        $state['paid_cosmetic_orders'][$playerId][$slot] = ['order_id' => $order->id, 'cosmetic' => $category.':'.$selected];
                        // Shared ownership of identical content does not mark two packs used.
                        break;
                    }
                }
            }
            foreach (['title', 'frame', 'accent', 'background'] as $slot) {
                $this->record($state, $playerId, $slot);
            }
            if ($playerId === $state['host_id'] && MatchCosmetics::choice($player, 'table') !== 'classic') {
                $this->record($state, $playerId, 'table');
            }
        }
        $expansion = isset($state['fae']) ? 'fae-court' : ($state['expansion']['id'] ?? null);
        if ($expansion !== null) {
            // The host's entitlement is preferred. Other owners joining do not all consume their purchases.
            $players = [$state['host_id'] => $state['players'][$state['host_id']], ...$state['players']];
            foreach ($players as $playerId => $player) {
                if (! isset($player['user_id']) || ! User::whereKey($player['user_id'])->whereNotNull('email_verified_at')->exists()) {
                    continue;
                }
                $order = PaidOrder::where('user_id', $player['user_id'])->where('bundle_id', $expansion)
                    ->where('status', 'paid')->orderBy('id')->lockForUpdate()->first();
                if ($order !== null) {
                    $state['paid_cosmetic_orders'][$playerId]['expansion'] = ['order_id' => $order->id, 'cosmetic' => 'expansions:'.$expansion];
                    $this->record($state, $playerId, 'expansion');
                    break;
                }
            }
        }
    }

    /** Called only for a server-produced cosmetic event, within the room transaction.
     * @param  array<string,mixed>  $state
     */
    public function record(array $state, string $playerId, string $slot): void
    {
        $source = $state['paid_cosmetic_orders'][$playerId][$slot] ?? null;
        if ($source === null || ! isset($state['match_id'])) {
            return;
        }
        $order = PaidOrder::whereKey($source['order_id'])->lockForUpdate()->first();
        if ($order === null || $order->status !== 'paid' || $order->user_id !== ($state['players'][$playerId]['user_id'] ?? null)) {
            return;
        }
        $query = DB::table('paid_pack_usages')->where('paid_order_id', $order->id)->where('match_id', $state['match_id']);
        $existing = $query->first();
        if ($existing === null) {
            DB::table('paid_pack_usages')->insert(['paid_order_id' => $order->id, 'match_id' => $state['match_id'],
                'cosmetics' => json_encode([$source['cosmetic']], JSON_THROW_ON_ERROR), 'used_at' => now()]);
        } else {
            $cosmetics = json_decode($existing->cosmetics, true, 512, JSON_THROW_ON_ERROR);
            $query->update(['cosmetics' => json_encode(array_values(array_unique([...$cosmetics, $source['cosmetic']])), JSON_THROW_ON_ERROR)]);
        }
    }
}
