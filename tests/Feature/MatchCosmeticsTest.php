<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchCosmetics;
use App\Game\MatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MatchCosmeticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminated_winners_are_eligible_and_the_choice_is_persisted(): void
    {
        $state = ['winner' => 'town', 'phase' => 'finished', 'host_id' => 'loser', 'players' => [
            'loser' => ['alignment' => 'cult', 'alive' => true, 'customization' => ['celebration' => 'crownfall']],
            'dead-winner' => ['alignment' => 'town', 'alive' => false, 'customization' => ['celebration' => 'moonrise']],
        ]];
        MatchCosmetics::celebrate($state);
        $event = $state['cosmetic_events'][0];
        $this->assertSame('dead-winner', $event['player_id']);
        $this->assertSame('moonrise', $event['effect']);
        for ($i = 0; $i < 10; $i++) {
            MatchCosmetics::celebrate($state);
            $this->assertSame([$event], $state['cosmetic_events']);
        }
        $state['phase'] = 'voting';
        $this->assertSame([], MatchCosmetics::view($state)['events']);
    }

    public function test_legacy_and_unknown_cosmetics_have_free_defaults(): void
    {
        $state = ['phase' => 'lobby', 'host_id' => 'guest', 'players' => ['guest' => []]];
        $this->assertSame(['table' => 'classic', 'events' => []], MatchCosmetics::view($state));
        foreach (['table', 'banishment', 'celebration'] as $slot) {
            $this->assertSame('classic', MatchCosmetics::choice(['customization' => [$slot => 'retired-item']], $slot));
        }
        MatchCosmetics::celebrate($state);
        $this->assertArrayNotHasKey('cosmetic_events', $state);
    }

    public function test_final_vote_snapshots_banished_cosmetic_then_one_winning_players_effect_for_every_viewer(): void
    {
        Event::fake([RoomUpdated::class]);
        $engine = app(MatchEngine::class);
        $room = $engine->create('cosmetic-0', 'Host');
        for ($i = 1; $i < 5; $i++) {
            $engine->join($room->code, 'cosmetic-'.$i, 'Player '.$i);
        }
        $state = $room->fresh()->state;
        $host = $state['host_id'];
        $state['players'][$host]['customization'] = ['table' => 'moonlit', 'banishment' => 'gilded_vortex'];
        $room->update(['state' => $state]);
        $act = function (string $identity, string $type, array $extra = []) use ($engine, $room): array {
            return $engine->access($room->code, $identity, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
        };
        for ($i = 0; $i < 5; $i++) {
            $act('cosmetic-'.$i, 'ready');
        }
        $act('cosmetic-0', 'start');
        $state = $room->fresh()->state;
        $this->assertSame('moonlit', $state['cosmetic_table']);
        $ids = array_keys($state['players']);
        $banished = $ids[1];
        foreach ($state['players'] as $id => &$player) {
            $player['role'] = $id === $banished ? 'acolyte' : 'townsperson';
            $player['alignment'] = $id === $banished ? 'cult' : 'town';
            $player['customization']['celebration'] = $id === $banished ? 'moonrise' : 'lantern_festival';
        }
        unset($player);
        $state['players'][$host]['customization']['table'] = 'harvest';
        $state['players'][$banished]['customization']['banishment'] = 'lunar_rift';
        $state['phase'] = 'voting';
        $state['day'] = 1;
        $state['actions'] = [];
        $room->update(['state' => $state, 'deadline' => now()->addMinutes(5)]);
        $before = $engine->access($room->code, 'cosmetic-0');
        $this->assertSame('moonlit', $before['cosmetics']['table']);
        $this->assertSame([], $before['cosmetics']['events']);
        foreach ($before['players'] as $player) {
            $this->assertArrayNotHasKey('alignment', $player);
        }
        for ($i = 0; $i < 5; $i++) {
            $act('cosmetic-'.$i, 'vote', ['target' => $i === 1 ? $host : $banished]);
        }
        $finished = $engine->access($room->code, 'cosmetic-0');
        $this->assertSame('finished', $finished['phase']);
        $events = $finished['cosmetics']['events'];
        $this->assertSame(['banishment', 'celebration'], array_column($events, 'kind'));
        $this->assertSame($banished, $events[0]['player_id']);
        $this->assertSame('lunar_rift', $events[0]['effect']);
        $this->assertNotSame($banished, $events[1]['player_id']);
        $this->assertSame('lantern_festival', $events[1]['effect']);
        $this->assertNotSame($events[0]['id'], $events[1]['id']);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($events, $engine->access($room->code, 'cosmetic-'.$i)['cosmetics']['events']);
        }
        $rematch = $act('cosmetic-0', 'rematch');
        $this->assertSame([], $rematch['cosmetics']['events']);
        $this->assertSame('harvest', $rematch['cosmetics']['table']);
    }
}
