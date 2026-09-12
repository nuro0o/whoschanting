<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomUpdated::class]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
    }

    public static function roomTypes(): array
    {
        return ['public' => ['public'], 'private' => ['private']];
    }

    #[DataProvider('roomTypes')]
    public function test_guests_and_verified_accounts_can_share_wifi(string $visibility): void
    {
        $code = $this->withSession(['chanting.identity' => 'host'])
            ->postJson('/rooms', ['name' => 'Host', 'visibility' => $visibility])->assertCreated()->json('code');
        $this->withSession(['chanting.identity' => 'guest'])->postJson('/rooms/join', ['code' => $code, 'name' => 'Guest'])->assertOk();
        $users = User::factory()->count(2)->create();
        foreach ($users as $index => $user) {
            $this->actingAs($user)->withSession(['chanting.identity' => 'account-'.$index])
                ->postJson('/rooms/join', ['code' => $code, 'name' => 'Friend '.$index])->assertOk();
            $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('me.account_progression', true);
        }
        $room = GameRoom::where('code', $code)->firstOrFail();
        $this->assertCount(4, $room->state['players']);
        $this->assertStringNotContainsString('network_hashes', json_encode($room->state));
        $this->assertStringNotContainsString('192.0.2.1', json_encode($room->state));
        $this->withSession(['chanting.identity' => 'fresh-session'])
            ->postJson('/rooms/join', ['code' => $code, 'name' => 'Returning friend'])->assertOk();
        $this->assertCount(4, $room->fresh()->state['players']);
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/rooms/join', ['code' => $code, 'name' => 'Unverified'])->assertForbidden();
    }

    public function test_legacy_network_fingerprints_do_not_block_a_party(): void
    {
        $room = app(MatchEngine::class)->create('host', 'Host');
        $state = $room->state;
        $state['players'][$state['host_id']]['network_hashes'] = [hash_hmac('sha256', $room->code.'|'.inet_pton('192.0.2.1'), config('app.key'))];
        $room->update(['state' => $state]);
        $this->withSession(['chanting.identity' => 'guest'])
            ->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest'])->assertOk();
        $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->assertDontSee('network_hashes');
        $this->assertCount(2, $room->fresh()->state['players']);
    }
}
