<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RoomBrowserTest extends TestCase
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

    public function test_guests_can_browse_without_creating_a_seat(): void
    {
        $this->get('/rooms')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('RoomBrowser'));
        $this->getJson('/rooms/public')->assertOk()->assertExactJson(['rooms' => [], 'page' => 1, 'has_more' => false])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertDatabaseCount('game_rooms', 0);
        $this->assertFalse(session()->has('chanting.identity'));
    }

    public function test_creation_defaults_to_private_and_accepts_explicit_visibility(): void
    {
        foreach ([null, 'private', 'public'] as $visibility) {
            $code = $this->postJson('/rooms', ['name' => 'Host', ...($visibility === null ? [] : ['visibility' => $visibility])])
                ->assertCreated()->json('code');
            $room = GameRoom::where('code', $code)->firstOrFail();
            $this->assertSame($visibility ?? 'private', $room->state['visibility']);
            $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('visibility', $visibility ?? 'private');
        }
        $this->getJson('/rooms/public')->assertOk()->assertJsonCount(1, 'rooms');
        foreach (['hidden', true, ['public'], null] as $visibility) {
            $this->postJson('/rooms', ['name' => 'Host', 'visibility' => $visibility])->assertUnprocessable()->assertJsonValidationErrors('visibility');
        }
        $this->assertDatabaseCount('game_rooms', 3);
        $this->expectException(ValidationException::class);
        $this->engine->create('host', 'Host', visibility: 'invalid');
    }

    public function test_private_and_legacy_rooms_never_appear_and_remain_joinable_by_code(): void
    {
        $private = $this->engine->create('private-host', 'Private host', pin: '0123');
        $legacy = $this->engine->create('legacy-host', 'Legacy host');
        $s = $legacy->state;
        unset($s['visibility']);
        $legacy->update(['state' => $s]);
        $this->getJson('/rooms/public')->assertOk()->assertJsonCount(0, 'rooms');
        $this->withSession(['chanting.identity' => 'guest'])->postJson('/rooms/join', ['code' => $private->code, 'name' => 'Guest', 'pin' => '0123'])->assertOk();
        $this->postJson('/rooms/join', ['code' => $legacy->code, 'name' => 'Guest'])->assertOk();
        $this->getJson('/rooms/'.$legacy->code.'/state')->assertOk()->assertJsonPath('visibility', 'private');
        $this->getJson('/rooms/public')->assertOk()->assertJsonCount(0, 'rooms');
    }

    public function test_public_pin_rooms_are_listed_but_still_require_the_pin(): void
    {
        $room = $this->engine->create('host-secret', 'Host', pin: '0123', visibility: 'public');
        $this->getJson('/rooms/public')->assertOk()->assertJsonPath('rooms.0.code', $room->code)->assertJsonPath('rooms.0.pin_required', true);
        $this->getJson('/rooms/'.$room->code.'/state')->assertForbidden();
        foreach ([null, '9999'] as $pin) {
            $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => $pin])->assertUnprocessable()->assertJsonValidationErrors('pin');
        }
        $this->assertCount(1, $room->fresh()->state['players']);
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Guest', 'pin' => '0123'])->assertOk();
        $this->getJson('/rooms/public')->assertJsonPath('rooms.0.player_count', 2);
        $this->engine->access($room->code, 'host-secret', ['type' => 'set_pin', 'phase_id' => 1, 'pin' => null]);
        $this->getJson('/rooms/public')->assertJsonPath('rooms.0.pin_required', false)->assertJsonCount(1, 'rooms');
    }

    public function test_directory_only_returns_allowlisted_summary_and_masks_existing_host_names(): void
    {
        $room = $this->engine->create('secret-identity', 'Host', pin: '0123', visibility: 'public');
        $s = $room->state;
        $s['players'][$s['host_id']]['name'] = 'Shit captain';
        $s['messages'][] = ['body' => 'Private conversation'];
        $room->update(['state' => $s]);
        $this->getJson('/rooms/public')->assertOk()->assertExactJson([
            'rooms' => [[
                'code' => $room->code, 'host_name' => '**** captain', 'player_count' => 1,
                'capacity' => config('game.max_players'), 'pin_required' => true,
                'mode_setup' => $s['mode_setup'],
            ]],
            'page' => 1, 'has_more' => false,
        ]);
        $this->assertCount(1, $room->fresh()->state['players']);
    }

    public function test_custom_capacity_and_full_rooms_are_reported_without_bypassing_join_rules(): void
    {
        $room = $this->engine->create('host', 'Host', setup: ['mode' => 'custom', 'roles' => ['oracle' => 1, 'veilweaver' => 1, 'townsperson' => 1]], visibility: 'public');
        $this->engine->join($room->code, 'guest', 'Guest');
        $this->engine->join($room->code, 'third', 'Third');
        $this->getJson('/rooms/public')->assertOk()->assertJsonPath('rooms.0.capacity', 3)->assertJsonPath('rooms.0.player_count', 3);
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Fourth'])->assertUnprocessable();
        $this->assertCount(3, $room->fresh()->state['players']);
    }

    public function test_started_rooms_disappear_and_public_rematches_reappear(): void
    {
        $room = $this->engine->create('host', 'Host', visibility: 'public');
        foreach (['guest', 'third'] as $identity) {
            $this->engine->join($room->code, $identity, ucfirst($identity));
        }
        foreach (['host', 'guest', 'third'] as $identity) {
            $this->engine->access($room->code, $identity, ['type' => 'ready', 'phase_id' => 1]);
        }
        $this->engine->access($room->code, 'host', ['type' => 'start', 'phase_id' => 1]);
        $this->getJson('/rooms/public')->assertOk()->assertJsonCount(0, 'rooms');
        $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Late guest'])->assertUnprocessable();
        $s = $room->fresh()->state;
        $s['phase'] = 'finished';
        $room->update(['state' => $s, 'deadline' => null]);
        $this->getJson('/rooms/public')->assertOk()->assertJsonCount(0, 'rooms');
        $view = $this->engine->access($room->code, 'host', ['type' => 'rematch', 'phase_id' => $s['phase_id']]);
        $this->assertSame('public', $view['visibility']);
        $this->getJson('/rooms/public')->assertOk()->assertJsonPath('rooms.0.code', $room->code);
    }

    public function test_pagination_is_bounded_ordered_and_filters_before_paginating(): void
    {
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = $this->engine->create('host-'.$i, 'Host '.$i, visibility: 'public')->code;
        }
        $this->engine->create('private', 'Private');
        $first = $this->getJson('/rooms/public')->assertOk()->assertJsonCount(24, 'rooms')->assertJsonPath('has_more', true)->json('rooms');
        $this->assertSame(array_slice(array_reverse($codes), 0, 24), array_column($first, 'code'));
        $this->getJson('/rooms/public?page=2')->assertOk()->assertJsonPath('page', 2)->assertJsonCount(1, 'rooms')->assertJsonPath('rooms.0.code', $codes[0])->assertJsonPath('has_more', false);
        foreach (['0', '-1', '1.5', 'no', '1001', '%5B%5D'] as $page) {
            $this->getJson('/rooms/public?page='.$page)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
    }

    public function test_browsing_keeps_account_verification_rules_and_is_rate_limited(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->getJson('/rooms/public')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/rooms/public')->assertOk();
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/rooms/public');
        }
        $response->assertTooManyRequests()->assertHeader('Retry-After');
    }
}
