<?php

namespace App\Game;

use App\Models\GameRoom;

class RoomBrowser
{
    public function __construct(private GameModes $modes, private ProfanityFilter $profanity) {}

    /** Public lobby summaries are deliberately separate from player state.
     * @return array{rooms: list<array<string, mixed>>, page: int, has_more: bool}
     */
    public function listing(int $page): array
    {
        $rooms = GameRoom::query()
            ->where('state->visibility', 'public')
            ->where('state->phase', 'lobby')
            ->orderByDesc('id')
            ->simplePaginate(24, ['code', 'state'], 'page', $page);
        $summaries = [];
        foreach ($rooms as $room) {
            $state = $this->profanity->roomText($room->state);
            $setup = $this->modes->setup($state);
            $summaries[] = [
                'code' => $room->code,
                'host_name' => $state['players'][$state['host_id']]['name'],
                'player_count' => count($state['players']),
                'capacity' => $setup['mode'] === 'custom' ? array_sum($setup['roles']) : config('game.max_players'),
                'pin_required' => isset($state['pin_hash']),
                'mode_setup' => $setup,
            ];
        }

        return ['rooms' => $summaries, 'page' => $rooms->currentPage(), 'has_more' => $rooms->hasMorePages()];
    }
}
