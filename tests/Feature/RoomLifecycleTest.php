<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameMatch;
use App\Models\GameRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
        $this->engine = app(MatchEngine::class);
    }

    private function room(int $count = 3, bool $start = false): GameRoom
    {
        $room = $this->engine->create('p0', 'Player 0', visibility: 'public');
        for ($i = 1; $i < $count; $i++) {
            $this->engine->join($room->code, 'p'.$i, 'Player '.$i);
        }
        for ($i = 0; $i < $count; $i++) {
            $this->engine->presence($room->code, 'p'.$i, 'tab'.$i);
            $this->act($room, 'p'.$i, 'ready');
        }
        if ($start) {
            $this->act($room, 'p0', 'start');
        }

        return $room->fresh();
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, string $identity, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, $identity, ['phase_id' => $room->fresh()->state['phase_id'], 'type' => $type, ...$extra]);
    }

    private function expire(GameRoom $room): void
    {
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->artisan('game:tick')->assertSuccessful();
        $room->refresh();
    }

    private function id(GameRoom $room, string $identity): string
    {
        return $this->engine->playerId($room->fresh()->state, $identity);
    }

    public function test_lobby_leave_removes_seat_and_hands_host_to_next_player(): void
    {
        $room = $this->room();
        $host = $this->id($room, 'p0');
        $next = $this->id($room, 'p1');
        $this->engine->presence($room->code, 'p0', 'tab0', 'leave');
        $s = $room->fresh()->state;
        $this->assertArrayNotHasKey($host, $s['players']);
        $this->assertSame($next, $s['host_id']);
        $this->assertFalse($s['players'][$next]['ready']);
        $this->getJson('/rooms/public')->assertJsonPath('rooms.0.player_count', 2)->assertJsonPath('rooms.0.host_name', 'Player 1');
    }

    public function test_last_person_leaving_closes_the_lobby(): void
    {
        $room = $this->room(1);
        $this->engine->presence($room->code, 'p0', 'tab0', 'leave');
        $this->assertSame('closed', $room->fresh()->state['phase']);
        $this->assertNull($room->fresh()->maintenance_at);
        $this->getJson('/rooms/public')->assertJsonCount(0, 'rooms');
        $this->withSession(['chanting.identity' => 'p0'])->getJson('/rooms/'.$room->code.'/state')->assertGone();
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'New player'])->assertGone();
    }

    public function test_disconnect_grace_preserves_refresh_and_other_tabs(): void
    {
        $room = $this->room(2);
        $host = $room->state['host_id'];
        $this->engine->presence($room->code, 'p0', 'tab0', 'disconnect');
        $this->travel(2)->seconds();
        $this->engine->presence($room->code, 'p0', 'replacement');
        $this->travel(5)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertSame($host, $room->fresh()->state['host_id']);
        $this->assertCount(2, $room->fresh()->state['players']);
        $this->engine->presence($room->code, 'p0', 'another-tab');
        $this->engine->presence($room->code, 'p0', 'replacement', 'disconnect');
        $this->travel(6)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertSame($host, $room->fresh()->state['host_id']);
        $this->engine->presence($room->code, 'p0', 'another-tab', 'disconnect');
        $this->travel(6)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertNotSame($host, $room->fresh()->state['host_id']);
    }

    public function test_lost_connection_expires_without_a_close_notification(): void
    {
        $room = $this->room(2);
        $host = $room->state['host_id'];
        $this->travel(40)->seconds();
        $this->engine->presence($room->code, 'p1', 'tab1');
        $this->travel(6)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertArrayNotHasKey($host, $room->fresh()->state['players']);
        $this->assertSame('lobby', $room->fresh()->state['phase']);
    }

    public function test_only_the_host_can_end_a_room_and_outsiders_cannot_manage_presence(): void
    {
        $room = $this->room(start: true);
        $url = '/rooms/'.$room->code.'/presence';
        $body = ['type' => 'end_room', 'client_id' => (string) Str::uuid()];
        $this->withSession(['chanting.identity' => 'outsider'])->postJson($url, $body)->assertForbidden();
        $this->withSession(['chanting.identity' => 'p1'])->postJson($url, $body)->assertUnprocessable();
        $this->withSession(['chanting.identity' => 'p0'])->postJson($url, $body)->assertOk()->assertJsonPath('closed', true);
        $this->assertNull($room->fresh()->deadline);
        $this->assertDatabaseCount('game_matches', 0);
        $this->withSession(['chanting.identity' => 'p1'])->getJson('/rooms/'.$room->code.'/state')->assertGone();
    }

    public function test_leaving_a_match_preserves_role_and_host_and_allows_return(): void
    {
        $room = $this->room(start: true);
        $host = $room->state['host_id'];
        $role = $room->state['players'][$host]['role'];
        $this->engine->presence($room->code, 'p0', 'tab0', 'leave');
        $this->assertSame($host, $room->fresh()->state['host_id']);
        $this->assertCount(3, $room->fresh()->state['players']);
        $this->assertFalse($room->fresh()->state['players'][$host]['connected']);
        $this->engine->presence($room->code, 'p0', 'return');
        $view = $this->engine->access($room->code, 'p0');
        $this->assertSame($role, $view['me']['role']);
        $this->assertSame($host, $view['me']['id']);
        $this->assertTrue($room->fresh()->state['players'][$host]['connected']);
    }

    public function test_two_missed_phases_prompt_then_afk_stops_delaying_votes_and_can_resume(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $away = $this->id($room, 'p2');
        $this->expire($room); // reveal -> night
        $this->act($room, 'p0', 'night');
        $this->act($room, 'p1', 'night');
        $this->expire($room);
        $this->assertSame(1, $room->state['players'][$away]['missed_phases']);
        $this->act($room, 'p0', 'discussion_ready');
        $this->act($room, 'p1', 'discussion_ready');
        $this->expire($room);
        $view = $this->engine->access($room->code, 'p2');
        $this->assertNotNull($view['me']['afk_prompt_deadline']);
        $this->assertFalse($view['me']['afk']);
        $this->assertArrayNotHasKey('connections', $view['players'][0]);
        $this->assertArrayNotHasKey('afk_prompt_deadline', $view['players'][0]);
        // Keeping the tab open and polling does not count as participating.
        $this->engine->presence($room->code, 'p2', 'tab2');
        $this->travel(16)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertTrue($room->fresh()->state['players'][$away]['afk']);
        $this->act($room, 'p0', 'vote');
        $this->act($room, 'p1', 'vote');
        $this->assertSame('night', $room->fresh()->state['phase']);
        $ballot = collect($room->fresh()->state['rounds'][1]['vote']['ballots'])->firstWhere('player_id', $away);
        $this->assertNull($ballot['target_id']);
        $this->engine->presence($room->code, 'p2', 'tab2', 'here');
        $this->assertFalse($room->fresh()->state['players'][$away]['afk']);
        $this->act($room, 'p0', 'night');
        $this->act($room, 'p1', 'night');
        $this->assertSame('night', $room->fresh()->state['phase']);
        $this->act($room, 'p2', 'night');
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $this->assertFalse($room->fresh()->state['players'][$away]['ability_used'] ?? false);
    }

    public function test_afk_prompt_expiry_advances_when_every_active_player_has_already_voted(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'p0', 'discussion_ready');
        $this->act($room, 'p1', 'discussion_ready');
        $this->expire($room);
        $this->act($room, 'p0', 'vote');
        $this->act($room, 'p1', 'vote');
        $this->assertSame('voting', $room->fresh()->state['phase']);
        $this->travel(16)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertSame('night', $room->fresh()->state['phase']);
    }

    public function test_chat_counts_but_banished_and_curse_blocked_players_are_not_flagged(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'p0', 'chat', ['body' => 'I am listening.']);
        $s = $room->fresh()->state;
        $s['players'][$this->id($room, 'p1')]['alive'] = false;
        $s['players'][$this->id($room, 'p2')]['curse'] = ['type' => 'mist'];
        $room->update(['state' => $s]);
        $this->expire($room);
        foreach ($room->state['players'] as $p) {
            $this->assertNull($p['afk_prompt_deadline'] ?? null);
            $this->assertFalse($p['afk'] ?? false);
        }
        $this->assertSame(0, $room->state['players'][$this->id($room, 'p0')]['missed_phases']);
    }

    public function test_finished_match_transfers_host_preserves_recap_and_drops_departed_seats_on_rematch(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $host = $room->state['host_id'];
        $next = $this->id($room, 'p1');
        $this->engine->presence($room->code, 'p0', 'tab0', 'leave');
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'p1', 'discussion_ready');
        $this->act($room, 'p2', 'discussion_ready');
        $this->expire($room);
        $s = $room->fresh()->state;
        $s['tokens'] = $s['threshold'];
        $room->update(['state' => $s]);
        $this->act($room, 'p1', 'vote');
        $this->act($room, 'p2', 'vote');
        $this->expire($room);
        $s = $room->fresh()->state;
        $this->assertSame('finished', $s['phase']);
        $this->assertSame($next, $s['host_id']);
        $this->assertFalse($s['players'][$host]['in_room']);
        $this->assertCount(3, GameMatch::firstOrFail()->recap['players']);
        $this->withSession(['chanting.identity' => 'p0'])->postJson('/rooms/'.$room->code.'/actions', [
            'type' => 'ready', 'phase_id' => $s['phase_id'], 'client_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->act($room, 'p1', 'rematch');
        $this->assertCount(2, $room->fresh()->state['players']);
        $this->assertArrayNotHasKey($host, $room->fresh()->state['players']);
    }

    public function test_rejected_choices_do_not_reset_afk(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $id = $this->id($room, 'p0');
        $s = $room->state;
        $s['players'][$id]['afk'] = true;
        $room->update(['state' => $s]);
        try {
            $this->act($room, 'p0', 'vote');
            $this->fail('Invalid phase should reject a vote.');
        } catch (ValidationException) {
            $this->assertTrue($room->fresh()->state['players'][$id]['afk']);
        }
    }

    public function test_everyone_away_closes_an_abandoned_match_without_rewards(): void
    {
        $room = $this->room(start: true);
        for ($i = 0; $i < 3; $i++) {
            $this->engine->presence($room->code, 'p'.$i, 'tab'.$i, 'leave');
        }
        $this->assertNotNull($room->fresh()->state['abandoned_deadline']);
        $this->travel(301)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertSame('closed', $room->fresh()->state['phase']);
        $this->assertNull($room->fresh()->deadline);
        $this->assertNull($room->fresh()->maintenance_at);
        $this->assertDatabaseCount('game_matches', 0);
    }

    public function test_returning_before_abandonment_keeps_the_match_open(): void
    {
        $room = $this->room(start: true);
        for ($i = 0; $i < 3; $i++) {
            $this->engine->presence($room->code, 'p'.$i, 'tab'.$i, 'leave');
        }
        $this->travel(30)->seconds();
        $this->engine->presence($room->code, 'p0', 'return', 'here');
        $this->assertArrayNotHasKey('abandoned_deadline', $room->fresh()->state);
        $this->assertNotSame('closed', $room->fresh()->state['phase']);
    }

    public function test_migrated_rooms_acquire_leases_and_abandoned_lobbies_expire(): void
    {
        $room = $this->engine->create('old-host', 'Old Host');
        $room->update(['maintenance_at' => now()->subSecond()]);
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertNotEmpty($room->fresh()->state['players'][$room->state['host_id']]['connections']);
        $this->travel(46)->seconds();
        $this->artisan('game:tick')->assertSuccessful();
        $this->assertSame('closed', $room->fresh()->state['phase']);
    }

    public function test_here_works_during_a_curse_without_clearing_it_or_spending_an_ability(): void
    {
        $room = $this->room(start: true);
        $id = $room->state['host_id'];
        $s = $room->state;
        $s['players'][$id]['curse'] = ['type' => 'mist'];
        $s['players'][$id]['afk'] = true;
        $room->update(['state' => $s]);
        $this->withSession(['chanting.identity' => 'p0'])->postJson('/rooms/'.$room->code.'/presence', [
            'type' => 'here', 'client_id' => (string) Str::uuid(),
        ])->assertOk();
        $p = $room->fresh()->state['players'][$id];
        $this->assertFalse($p['afk']);
        $this->assertSame('mist', $p['curse']['type']);
        $this->assertFalse($p['ability_used'] ?? false);
        $this->assertSame([], $room->fresh()->state['actions']);
    }

    public function test_reading_state_also_finishes_a_phase_when_afk_grace_expires(): void
    {
        config(['game.presence.timeout_seconds' => 3600]);
        $room = $this->room(start: true);
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'p0', 'discussion_ready');
        $this->act($room, 'p1', 'discussion_ready');
        $this->expire($room);
        $this->act($room, 'p0', 'vote');
        $this->act($room, 'p1', 'vote');
        $this->travel(16)->seconds();
        $view = $this->engine->access($room->code, 'p0');
        $this->assertSame('night', $view['phase']);
    }

    public function test_an_action_from_a_returning_tab_renews_presence_before_lobby_cleanup(): void
    {
        $room = $this->room(2);
        $host = $room->state['host_id'];
        $this->travel(46)->seconds();
        $this->withSession(['chanting.identity' => 'p0'])->postJson('/rooms/'.$room->code.'/actions', [
            'type' => 'ready', 'phase_id' => $room->state['phase_id'], 'client_id' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('host_id', $host)->assertJsonPath('phase', 'lobby');
        $this->assertTrue($room->fresh()->state['players'][$host]['connected']);
        $this->assertCount(1, $room->fresh()->state['players']);
    }
}
