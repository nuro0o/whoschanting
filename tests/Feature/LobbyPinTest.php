<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LobbyPinTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->engine = app(MatchEngine::class);
        Event::fake([RoomUpdated::class]);
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, string $identity, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, $identity, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function denied(callable $action): void
    {
        try {
            $action();
            $this->fail('An invalid action was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_open_and_legacy_lobbies_still_accept_players_without_a_pin(): void
    {
        $room = $this->engine->create('host', 'Host');
        $view = $this->engine->access($room->code, 'host');
        $this->assertFalse($view['pin_required']);
        $this->engine->join($room->code, 'guest', 'Guest');
        $s = $room->fresh()->state;
        unset($s['pin_hash']);
        $room->update(['state' => $s]);
        $this->engine->join($room->code, 'legacy-guest', 'Legacy guest');
        $this->assertCount(3, $room->fresh()->state['players']);
    }

    public function test_pin_is_hashed_preserves_leading_zeroes_and_is_never_returned(): void
    {
        $this->withSession(['chanting.identity' => 'host'])->postJson('/rooms', ['name' => 'Host', 'pin' => '004271'])->assertCreated();
        $room = GameRoom::firstOrFail();
        $hash = $room->state['pin_hash'];
        $this->assertNotSame('004271', $hash);
        $this->assertTrue(Hash::check('004271', $hash));
        $this->assertStringNotContainsString('004271', json_encode($room->state));
        $view = $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->assertJsonPath('pin_required', true);
        $this->assertStringNotContainsString($hash, $view->getContent());
        $this->assertStringNotContainsString('pin_hash', $view->getContent());
        $this->assertStringNotContainsString('004271', $view->getContent());
        $this->assertArrayNotHasKey('state', $room->toArray());
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->withSession(['chanting.identity' => 'guest'])
            ->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '004271'])->assertOk();
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_missing_or_wrong_pin_cannot_create_a_seat_or_access_room_secrets(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        $revision = $room->state['revision'];
        foreach ([null, '004272', '4271'] as $pin) {
            $this->withSession(['chanting.identity' => 'outsider'])->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => $pin])->assertUnprocessable()->assertJsonValidationErrors('pin');
        }
        $this->getJson('/rooms/'.$room->code.'/state')->assertForbidden();
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'ready', 'phase_id' => 1])->assertForbidden();
        $this->assertSame($revision, $room->fresh()->state['revision']);
        $this->assertCount(1, $room->fresh()->state['players']);
    }

    public function test_host_can_add_change_and_remove_pin_without_losing_seats_or_readiness(): void
    {
        $room = $this->engine->create('host', 'Host');
        $this->engine->join($room->code, 'guest', 'Guest');
        $this->act($room, 'guest', 'ready');
        $view = $this->act($room, 'host', 'set_pin', ['pin' => '004271']);
        $this->assertTrue($view['pin_required']);
        $this->assertTrue($view['players'][1]['ready']);
        $this->denied(fn () => $this->act($room, 'guest', 'set_pin', ['pin' => null]));
        $this->denied(fn () => $this->act($room, 'host', 'set_pin'));
        $this->act($room, 'host', 'set_pin', ['pin' => '87654321']);
        $this->denied(fn () => $this->engine->join($room->code, 'new', 'New guest', pin: '004271'));
        $this->engine->join($room->code, 'new', 'New guest', pin: '87654321');
        $view = $this->act($room, 'host', 'set_pin', ['pin' => null]);
        $this->assertFalse($view['pin_required']);
        $this->assertNull($room->fresh()->state['pin_hash']);
        $this->engine->join($room->code, 'open', 'Open guest');
        $this->assertCount(4, $room->fresh()->state['players']);
    }

    public function test_existing_guest_and_verified_account_seats_reconnect_without_a_pin(): void
    {
        $account = User::factory()->create();
        $room = $this->engine->create('host', 'Host', pin: '004271');
        $this->engine->join($room->code, 'guest', 'Guest', pin: '004271');
        $this->engine->join($room->code, 'account-browser', 'Account', accountId: $account->id, pin: '004271');
        $this->act($room, 'host', 'set_pin', ['pin' => '998877']);
        $this->engine->join($room->code, 'guest', 'Guest');
        $this->engine->join($room->code, 'another-browser', 'Account', accountId: $account->id);
        $this->assertCount(3, $room->fresh()->state['players']);
        $this->assertSame('Guest', $this->engine->access($room->code, 'guest')['me']['name']);
        $this->assertSame('Account', $this->engine->access($room->code, 'another-browser', accountId: $account->id)['me']['name']);
    }

    public function test_pin_survives_a_match_and_rematch_and_cannot_be_changed_during_play(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        foreach (['guest', 'other'] as $identity) {
            $this->engine->join($room->code, $identity, ucfirst($identity), pin: '004271');
        }
        foreach (['host', 'guest', 'other'] as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $this->act($room, 'host', 'start');
        $this->denied(fn () => $this->act($room, 'host', 'set_pin', ['pin' => null]));
        $this->engine->join($room->code, 'guest', 'Guest');
        $s = $room->fresh()->state;
        $s['phase'] = 'finished';
        $room->update(['state' => $s, 'deadline' => null]);
        $this->denied(fn () => $this->act($room, 'host', 'set_pin', ['pin' => null]));
        $view = $this->act($room, 'host', 'rematch');
        $this->assertTrue($view['pin_required']);
        $this->denied(fn () => $this->engine->join($room->code, 'new', 'New player'));
        $this->engine->join($room->code, 'new', 'New player', pin: '004271');
    }

    public function test_removed_players_need_the_pin_and_new_hosts_control_it(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        $this->engine->join($room->code, 'guest', 'Guest', pin: '004271');
        $ids = array_keys($room->fresh()->state['players']);
        $this->act($room, 'host', 'transfer_host', ['target' => $ids[1]]);
        $this->denied(fn () => $this->act($room, 'host', 'set_pin', ['pin' => null]));
        $this->act($room, 'guest', 'set_pin', ['pin' => '998877']);
        $this->act($room, 'guest', 'remove_player', ['target' => $ids[0]]);
        $this->denied(fn () => $this->engine->join($room->code, 'host', 'Host'));
        $this->engine->join($room->code, 'host', 'Host', pin: '998877');
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_invite_page_shows_only_whether_a_pin_is_required(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        $response = $this->get('/rooms/'.$room->code)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Game')->where('pinRequired', true)->missing('pin')->missing('pin_hash'));
        $this->assertStringNotContainsString($room->state['pin_hash'], $response->getContent());
        $this->assertStringNotContainsString('004271', $response->getContent());
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Invited guest'])->assertUnprocessable()->assertJsonValidationErrors('pin');
    }

    public function test_pin_validation_rejects_invalid_formats_and_omitted_pin_updates(): void
    {
        foreach (['123', '123456789', '12ab', '１２３４', ['1234'], 1234] as $pin) {
            $this->postJson('/rooms', ['name' => 'Host', 'pin' => $pin])->assertUnprocessable()->assertJsonValidationErrors('pin');
        }
        $this->assertSame(0, GameRoom::count());
        $room = $this->engine->create('host', 'Host', pin: '004271');
        $this->withSession(['chanting.identity' => 'host'])->postJson('/rooms/'.$room->code.'/actions', ['type' => 'set_pin', 'phase_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('pin');
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'set_pin', 'phase_id' => 1, 'pin' => '123'])->assertUnprocessable()->assertJsonValidationErrors('pin');
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'set_pin', 'phase_id' => 1, 'pin' => null])->assertOk()->assertJsonPath('pin_required', false);
    }

    public function test_failed_attempts_are_limited_per_room_and_ip_across_new_sessions(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['chanting.identity' => 'try-'.$i])->postJson('/rooms/join', ['code' => $i % 2 ? strtolower($room->code) : $room->code, 'name' => 'Guest', 'pin' => '998877'])->assertUnprocessable();
        }
        $this->withSession(['chanting.identity' => 'fresh-session'])->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '004271'])->assertStatus(429)->assertHeader('Retry-After');
        $other = $this->engine->create('other-host', 'Other host');
        $this->postJson('/rooms/join', ['code' => $other->code, 'name' => 'Guest'])->assertOk();
        $this->travel(61)->seconds();
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '004271'])->assertOk();
    }

    public function test_successful_reconnects_do_not_consume_the_failed_attempt_budget(): void
    {
        $room = $this->engine->create('host', 'Host', pin: '004271');
        for ($i = 0; $i < 9; $i++) {
            $this->withSession(['chanting.identity' => 'friend'])->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Friend', 'pin' => '004271'])->assertOk();
        }
        $this->assertCount(2, $room->fresh()->state['players']);
    }

    public function test_validation_redirects_never_flash_a_pin_to_the_session(): void
    {
        $this->from('/')->post('/rooms', ['name' => '', 'pin' => '004271'])->assertRedirect('/');
        $this->assertArrayNotHasKey('pin', session()->getOldInput());
    }

    public function test_non_json_attempts_are_also_limited_and_malformed_codes_do_not_crash(): void
    {
        $this->postJson('/rooms/join', ['code' => ['bad-code'], 'name' => 'Guest'])->assertUnprocessable();
        $room = $this->engine->create('host', 'Host', pin: '004271');
        for ($i = 0; $i < 5; $i++) {
            $this->from('/')->post('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '998877'])->assertRedirect('/');
        }
        $this->post('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '004271'])->assertStatus(429);
        $this->assertArrayNotHasKey('pin', session()->getOldInput());
        $this->assertCount(1, $room->fresh()->state['players']);
    }
}
