<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
    }

    private function createRoom(string $ip = '192.0.2.1'): GameRoom
    {
        $code = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withSession(['chanting.identity' => 'host'])
            ->postJson('/rooms', ['name' => 'Host', 'visibility' => 'public'])
            ->assertCreated()->json('code');

        return GameRoom::where('code', $code)->firstOrFail();
    }

    private function joinRoom(GameRoom $room, string $identity, string $ip): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withSession(['chanting.identity' => $identity])
            ->postJson('/rooms/join', ['code' => $room->code, 'name' => ucfirst($identity)]);
    }

    /** @return array<string, array{string, string}> */
    public static function duplicateAddresses(): array
    {
        return [
            'IPv4' => ['192.0.2.1', '192.0.2.1'],
            'IPv6 spelling' => ['2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001'],
            'IPv4 mapped IPv6' => ['192.0.2.1', '::ffff:192.0.2.1'],
        ];
    }

    #[DataProvider('duplicateAddresses')]
    public function test_another_guest_cannot_join_from_the_same_address(string $hostIp, string $guestIp): void
    {
        $room = $this->createRoom($hostIp);
        $this->joinRoom($room, 'guest', $guestIp)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->joinRoom($room, 'guest', '192.0.2.2')->assertOk();
        $this->joinRoom($room, 'third', '192.0.2.2')->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_new_accounts_cannot_bypass_the_ip_check_but_own_seat_reconnects(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $room = $this->createRoom();
        $this->joinRoom($room, 'new-session', '192.0.2.1')->assertOk();
        $this->actingAs(User::factory()->create());
        $this->joinRoom($room, 'another-account', '192.0.2.1')->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->assertSame($user->id, $room->fresh()->state['players'][$room->state['host_id']]['user_id']);
    }

    public function test_guest_reconnect_keeps_old_and_new_addresses_reserved(): void
    {
        $room = $this->createRoom();
        $this->joinRoom($room, 'host', '192.0.2.1')->assertOk();
        $this->joinRoom($room, 'host', '192.0.2.2')->assertOk();
        foreach (['192.0.2.1', '192.0.2.2'] as $ip) {
            $this->joinRoom($room, 'guest', $ip)->assertUnprocessable();
        }
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->joinRoom($room, 'guest', '192.0.2.3')->assertOk();
        $this->joinRoom($room, 'host', '192.0.2.3')->assertUnprocessable();
    }

    public function test_untrusted_forwarded_headers_and_payload_cannot_spoof_an_address(): void
    {
        $room = $this->createRoom();
        $this->withHeaders(['X-Forwarded-For' => '192.0.2.99', 'Forwarded' => 'for=192.0.2.99', 'X-Real-IP' => '192.0.2.99'])
            ->withSession(['chanting.identity' => 'guest'])
            ->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'ip_address' => '192.0.2.99'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertCount(1, $room->fresh()->state['players']);
    }

    public function test_trusted_proxy_uses_the_original_client_address(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.1']]);
        $this->withHeaders(['X-Forwarded-For' => '192.0.2.1']);
        $room = $this->createRoom('10.0.0.1');
        $this->withHeaders(['X-Forwarded-For' => '192.0.2.2']);
        $this->joinRoom($room, 'guest', '10.0.0.1')->assertOk();
        $this->withHeaders(['X-Forwarded-For' => '192.0.2.1']);
        $this->joinRoom($room, 'third', '10.0.0.1')->assertUnprocessable();
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_networks_are_private_scoped_to_rooms_and_released_when_a_lobby_seat_leaves(): void
    {
        $room = $this->createRoom();
        $other = $this->createRoom();
        $firstHash = $room->state['players'][$room->state['host_id']]['network_hashes'][0];
        $this->assertNotSame($firstHash, $other->state['players'][$other->state['host_id']]['network_hashes'][0]);
        $this->assertStringNotContainsString('192.0.2.1', json_encode($room->state));
        $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->assertDontSee('network_hashes')->assertDontSee($firstHash);
        $this->getJson('/rooms/public')->assertOk()->assertDontSee('network_hashes')->assertDontSee($firstHash);
        $this->joinRoom($room, 'guest', '192.0.2.2')->assertOk();
        app(MatchEngine::class)->presence($room->code, 'guest', 'tab', 'leave');
        $this->joinRoom($room, 'replacement', '192.0.2.2')->assertOk();
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_network_reservations_survive_rematches(): void
    {
        $room = $this->createRoom();
        $this->joinRoom($room, 'guest', '192.0.2.2')->assertOk();
        $state = $room->fresh()->state;
        $state['phase'] = 'finished';
        $room->update(['state' => $state]);
        app(MatchEngine::class)->access($room->code, 'host', ['type' => 'rematch', 'phase_id' => 1]);
        $this->joinRoom($room, 'third', '192.0.2.2')->assertUnprocessable();
        $this->joinRoom($room, 'guest', '192.0.2.2')->assertOk();
    }

    public function test_missing_or_invalid_request_address_cannot_join_or_create_a_room(): void
    {
        $room = $this->createRoom();
        foreach (['', 'invalid'] as $ip) {
            $this->joinRoom($room, 'guest', $ip)->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->postJson('/rooms', ['name' => 'Guest'])->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->assertDatabaseCount('game_rooms', 1);
    }
}
