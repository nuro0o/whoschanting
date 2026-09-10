<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameTest extends TestCase
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

    /** @return array{GameRoom, array<string, string>} */
    private function match(string $mission = 'concord', int $playerCount = 5): array
    {
        $room = $this->engine->create('secret-0', 'Player 0');
        for ($i = 1; $i < $playerCount; $i++) {
            $this->engine->join($room->code, 'secret-'.$i, 'Player '.$i);
        }
        $identities = [];
        foreach ($room->fresh()->state['players'] as $id => $p) {
            $identity = 'secret-'.substr($p['name'], -1);
            $identities[$id] = $identity;
            $this->act($room, $identity, 'ready');
        }
        $this->act($room, 'secret-0', 'start');
        $s = $room->fresh()->state;
        $s['mission'] = config('game.missions.'.$mission);
        $room->update(['state' => $s]);

        return [$room, $identities];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, string $identity, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, $identity, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function expire(GameRoom $room): void
    {
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->artisan('game:tick')->assertSuccessful();
        $room->refresh();
    }

    /** @return array<string, string> */
    private function roles(GameRoom $room): array
    {
        $roles = [];
        foreach ($room->fresh()->state['players'] as $id => $p) {
            // Keep both townspeople individually available through state.
            $roles[$p['role']] = $id;
        }

        return $roles;
    }

    /** @return array<string, array{int, int}> */
    public static function rosterSizes(): array
    {
        return [
            'three players' => [3, 1], 'four players' => [4, 1],
            'five players' => [5, 2], 'six players' => [6, 2],
            'seven players' => [7, 3], 'eight players' => [8, 3],
            'nine players' => [9, 4], 'ten players' => [10, 4],
        ];
    }

    #[DataProvider('rosterSizes')]
    public function test_roster_sizes_private_teammates_and_shared_scoring(int $playerCount, int $cultistCount): void
    {
        [$room, $identities] = $this->match(playerCount: $playerCount);
        $players = $room->fresh()->state['players'];
        $cult = array_filter($players, fn (array $p): bool => $p['alignment'] === 'cult');
        $this->assertCount($playerCount, $players);
        $this->assertCount($cultistCount, $cult);
        $roles = array_count_values(array_column($players, 'role'));
        $this->assertSame(1, $roles['veilweaver']);
        $this->assertSame($cultistCount - 1, $roles['acolyte'] ?? 0);
        $this->assertSame(1, $roles['oracle']);
        $this->assertSame($playerCount - $cultistCount - 1, $roles['townsperson']);
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            foreach ($view['players'] as $public) {
                $this->assertArrayNotHasKey('role', $public);
                $this->assertArrayNotHasKey('alignment', $public);
            }
            if (isset($cult[$id])) {
                $this->assertEqualsCanonicalizing(array_values(array_diff(array_keys($cult), [$id])), array_column($view['me']['allies'], 'id'));
                $this->assertSame(config('game.missions.concord'), $view['me']['mission']);
            } else {
                $this->assertSame([], $view['me']['allies']);
                $this->assertNull($view['me']['mission']);
            }
        }
        $this->expire($room);
        foreach (array_keys($cult) as $id) {
            $this->act($room, $identities[$id], 'night');
        }
        $this->expire($room);
        $this->assertSame($cultistCount, $room->state['tokens']);
        $this->assertCount($cultistCount, $room->state['awards']);
    }

    public function test_three_player_town_can_win_by_banishing_the_lone_cultist(): void
    {
        [$room, $identities] = $this->match(playerCount: 3);
        $cultist = $this->roles($room)['veilweaver'];
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $cultist);
        $this->assertSame('town', $room->fresh()->state['winner']);
        $this->assertSame('finished', $room->fresh()->state['phase']);
    }

    public function test_three_player_lone_cultist_can_complete_the_ritual(): void
    {
        [$room, $identities] = $this->match(playerCount: 3);
        $cultist = $this->roles($room)['veilweaver'];
        $this->expire($room);
        for ($night = 1; $night <= 6; $night++) {
            $this->act($room, $identities[$cultist], 'night');
            $this->expire($room);
            if ($night < 6) {
                $this->expire($room);
                $this->expire($room);
            }
        }
        $this->assertSame('cult', $room->state['winner']);
        $this->assertSame('finished', $room->state['phase']);
        $this->assertSame(6, $room->state['tokens']);
    }

    public function test_two_players_cannot_start_and_an_eleventh_cannot_join(): void
    {
        $room = $this->engine->create('limit-0', 'Neighbor 0');
        $this->engine->join($room->code, 'limit-1', 'Neighbor 1');
        $this->act($room, 'limit-0', 'ready');
        $this->act($room, 'limit-1', 'ready');
        $this->assertRejected(fn () => $this->act($room, 'limit-0', 'start'));
        for ($i = 2; $i < 10; $i++) {
            $this->engine->join($room->code, 'limit-'.$i, 'Neighbor '.$i);
        }
        $this->assertRejected(fn () => $this->engine->join($room->code, 'limit-10', 'Neighbor 10'));
        $this->assertCount(10, $room->fresh()->state['players']);
    }

    public function test_private_views_reveal_only_own_role_and_cult_allies(): void
    {
        [$room, $identities] = $this->match();
        foreach ($identities as $id => $identity) {
            $view = $this->engine->access($room->code, $identity);
            $p = $room->fresh()->state['players'][$id];
            $this->assertSame($p['role'], $view['me']['role']);
            foreach ($view['players'] as $public) {
                $this->assertSame(['id', 'name', 'alive', 'ready', 'character', 'discussion_ready'], array_keys($public));
            }
            $this->assertStringNotContainsString('identity', json_encode($view));
            $this->assertArrayNotHasKey('actions', $view);
            $this->assertArrayNotHasKey('awards', $view);
            if ($p['alignment'] === 'cult') {
                $this->assertSame(config('game.missions.concord'), $view['me']['mission']);
                $this->assertCount(1, $view['me']['allies']);
            } else {
                $this->assertNull($view['me']['mission']);
                $this->assertSame([], $view['me']['allies']);
            }
        }
        $event = new RoomUpdated($room->id, 42);
        $this->assertSame(['revision' => 42], $event->broadcastWith());
        $this->assertSame('private-room.'.$room->id, $event->broadcastOn()->name);
        $this->assertArrayNotHasKey('state', $room->toArray());
    }

    public function test_guest_http_seat_reconnects_and_outsider_cannot_read_or_act(): void
    {
        $code = $this->postJson('/rooms', ['name' => 'Harbor keeper'])->assertCreated()->json('code');
        $first = $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->json();
        $this->get('/rooms/'.$code)->assertOk();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.id', $first['me']['id']);
        $this->postJson('/rooms/join', ['code' => $code, 'name' => 'Changed name'])->assertOk();
        $this->assertCount(1, GameRoom::first()->state['players']);
        $this->withSession(['chanting.identity' => 'outsider']);
        $this->getJson('/rooms/'.$code.'/state')->assertForbidden();
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'ready', 'phase_id' => 1])->assertForbidden();
    }

    public function test_channel_authorization_is_limited_to_members_and_their_room(): void
    {
        config(['broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        $code = $this->postJson('/rooms', ['name' => 'Harbor keeper'])->json('code');
        $room = GameRoom::first();
        $url = '/rooms/'.$code.'/broadcast-auth';
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.'.$room->id])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.99999'])->assertForbidden();
        $this->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'presence-room.'.$room->id])->assertForbidden();
        $this->withSession(['chanting.identity' => 'outsider'])->postJson($url, ['socket_id' => '123.456', 'channel_name' => 'private-room.'.$room->id])->assertForbidden();
    }

    public function test_lobby_requires_minimum_players_and_ready_members_and_host(): void
    {
        $room = $this->engine->create('host', 'Host');
        $this->assertRejected(fn () => $this->act($room, 'host', 'start'));
        for ($i = 1; $i < 5; $i++) {
            $this->engine->join($room->code, 'guest'.$i, 'Guest '.$i);
        }
        $this->assertRejected(fn () => $this->act($room, 'guest1', 'start'));
        $this->assertRejected(fn () => $this->act($room, 'host', 'start'));
        $this->assertRejected(fn () => $this->engine->join($room->code, 'another', 'HOST'));
    }

    public function test_players_on_shared_wifi_have_independent_polling_limits(): void
    {
        $room = $this->engine->create('wifi-0', 'Neighbor 0');
        for ($i = 1; $i < 10; $i++) {
            $this->engine->join($room->code, 'wifi-'.$i, 'Neighbor '.$i);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->withSession(['chanting.identity' => 'wifi-'.$i]);
            for ($poll = 0; $poll < 15; $poll++) {
                $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
            }
        }
        for ($poll = 15; $poll < 120; $poll++) {
            $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
        }
        $this->getJson('/rooms/'.$room->code.'/state')->assertTooManyRequests();
    }

    public function test_veil_reverses_investigation_only_for_one_night_and_never_changes_own_truth(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->expire($room);
        $oracle = $this->engine->access($room->code, $identities[$r['oracle']]);
        $this->assertSame('town', $oracle['me']['results'][0]['alignment']);
        $cult = $this->engine->access($room->code, $identities[$r['acolyte']]);
        $this->assertSame('cult', $cult['me']['alignment']);
        $this->assertSame(config('game.missions.concord'), $cult['me']['mission']);
        $this->assertSame([], $cult['me']['results']);
        $this->expire($room); // discussion -> voting
        $this->expire($room); // voting -> night
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]);
        $this->expire($room);
        $oracle = $this->engine->access($room->code, $identities[$r['oracle']]);
        $this->assertSame('cult', $oracle['me']['results'][1]['alignment']);
    }

    public function test_veil_can_make_town_appear_cult(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['townsperson']]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['townsperson']]);
        $this->expire($room);
        $this->assertSame('cult', $this->engine->access($room->code, $identities[$r['oracle']])['me']['results'][0]['alignment']);
    }

    public function test_duplicate_invalid_and_stale_actions_are_rejected_and_deadline_is_committed(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $oracle = $identities[$r['oracle']];
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night'));
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['oracle']]));
        $this->assertRejected(fn () => $this->act($room, $identities[$r['townsperson']], 'night', ['target' => $r['oracle']]));
        $this->act($room, $oracle, 'night', ['target' => $r['acolyte']]);
        $this->assertRejected(fn () => $this->act($room, $oracle, 'night', ['target' => $r['veilweaver']]));
        $phase = $room->fresh()->state['phase_id'];
        $this->travelTo($room->fresh()->deadline->addSecond());
        $this->assertRejected(fn () => $this->engine->access($room->code, $oracle, ['type' => 'night', 'phase_id' => $phase, 'target' => $r['acolyte']]));
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $this->assertCount(1, $this->engine->access($room->code, $oracle)['me']['results']);
        $this->engine->resolve($room->id);
        $this->assertCount(1, $this->engine->access($room->code, $oracle)['me']['results']);
    }

    public function test_all_ready_and_all_night_actions_advance_early_with_single_awards(): void
    {
        [$room, $identities] = $this->match();
        foreach ($identities as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $this->assertSame('night', $room->fresh()->state['phase']);
        $r = $this->roles($room);
        foreach ($identities as $id => $identity) {
            $this->act($room, $identity, 'night', $id === $r['oracle'] ? ['target' => $r['acolyte']] : []);
        }
        $this->assertSame('discussion', $room->fresh()->state['phase']);
        $this->assertSame(2, $room->fresh()->state['tokens']);
        $this->engine->resolve($room->id);
        $this->assertSame(2, $room->fresh()->state['tokens']);
        $this->assertCount(2, $room->fresh()->state['awards']);
    }

    public function test_concord_needs_every_surviving_cultist_to_chant(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->expire($room);
        $this->assertSame(0, $room->state['tokens']);
    }

    public function test_shadows_excludes_investigated_cult_even_if_veiled(): void
    {
        [$room, $identities] = $this->match('shadows');
        $r = $this->roles($room);
        $this->expire($room);
        $this->act($room, $identities[$r['acolyte']], 'night');
        $this->act($room, $identities[$r['veilweaver']], 'night', ['target' => $r['acolyte']]);
        $this->act($room, $identities[$r['oracle']], 'night', ['target' => $r['acolyte']]);
        $this->expire($room);
        $this->assertSame(1, $room->state['tokens']);
    }

    public function test_patience_scores_first_night_but_not_after_cult_banishment_and_dead_cannot_act(): void
    {
        [$room, $identities] = $this->match('patience');
        $r = $this->roles($room);
        $this->expire($room);
        foreach (['acolyte', 'veilweaver'] as $role) {
            $this->act($room, $identities[$r[$role]], 'night');
        }
        $this->expire($room);
        $this->assertSame(2, $room->state['tokens']);
        $this->expire($room);
        $this->banish($room, $identities, $r['acolyte']);
        $this->assertFalse($room->fresh()->state['players'][$r['acolyte']]['alive']);
        $this->assertRejected(fn () => $this->act($room, $identities[$r['acolyte']], 'night'));
        $this->act($room, $identities[$r['veilweaver']], 'night');
        $this->expire($room);
        $this->assertSame(2, $room->state['tokens']);
        $this->assertRejected(fn () => $this->act($room, $identities[$r['acolyte']], 'chat', ['body' => 'Secret from the grave']));
    }

    /** @param array<string, string> $identities */
    private function banish(GameRoom $room, array $identities, string $target): void
    {
        foreach ($room->fresh()->state['players'] as $id => $player) {
            if ($player['alive']) {
                $this->act($room, $identities[$id], 'vote', ['target' => $id === $target ? null : $target]);
            }
        }
    }

    public function test_town_victory_reveals_roster_and_rematch_preserves_seats_clears_secrets(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $r['acolyte']);
        $this->expire($room);
        $this->expire($room);
        $this->banish($room, $identities, $r['veilweaver']);
        $view = $this->engine->access($room->code, 'secret-0');
        $this->assertSame('town', $view['winner']);
        $this->assertSame('finished', $view['phase']);
        $this->assertNull($view['deadline']);
        $this->assertArrayHasKey('role', $view['players'][0]);
        $this->assertRejected(fn () => $this->act($room, 'secret-1', 'rematch'));
        $replay = $this->act($room, 'secret-0', 'rematch');
        $this->assertSame('lobby', $replay['phase']);
        $this->assertSame($view['me']['id'], $replay['me']['id']);
        $this->assertNull($replay['me']['role']);
        $this->assertNull($replay['me']['mission']);
        $this->assertSame([], $replay['me']['results']);
        $this->assertSame([], $room->fresh()->state['awards']);
        foreach ($replay['players'] as $p) {
            $this->assertTrue($p['alive']);
            $this->assertFalse($p['ready']);
        }
    }

    public function test_ritual_victory_finishes_complete_match_after_three_nights(): void
    {
        [$room, $identities] = $this->match();
        $r = $this->roles($room);
        $this->expire($room);
        for ($night = 1; $night <= 3; $night++) {
            foreach (['acolyte', 'veilweaver'] as $role) {
                $this->act($room, $identities[$r[$role]], 'night');
            }
            $this->expire($room);
            if ($night < 3) {
                $this->expire($room);
                $this->expire($room);
            }
        }
        $this->assertSame('finished', $room->state['phase']);
        $this->assertSame('cult', $room->state['winner']);
        $this->assertSame(6, $room->state['tokens']);
        $this->assertNull($room->deadline);
    }

    public function test_tied_vote_and_missing_votes_abstain_without_elimination(): void
    {
        [$room, $identities] = $this->match();
        $ids = array_keys($identities);
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $targets = [$ids[1], $ids[0], $ids[0], $ids[1], null];
        foreach ($ids as $i => $id) {
            $this->act($room, $identities[$id], 'vote', ['target' => $targets[$i]]);
        }
        $this->assertCount(5, array_filter($room->fresh()->state['players'], fn ($p) => $p['alive']));
        $this->expire($room);
        $this->expire($room);
        $this->act($room, $identities[$ids[0]], 'vote', ['target' => $ids[1]]);
        $this->expire($room);
        $this->assertCount(5, array_filter($room->state['players'], fn ($p) => $p['alive']));
    }

    public function test_chat_is_public_only_in_allowed_phases_and_names_are_validated(): void
    {
        $this->postJson('/rooms', ['name' => ' '])->assertUnprocessable();
        [$room, $identities] = $this->match();
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => 'During reveal']));
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => 'During night']));
        $this->expire($room);
        $this->act($room, 'secret-0', 'chat', ['body' => 'The lighthouse looks suspicious.']);
        $this->assertSame('The lighthouse looks suspicious.', $this->engine->access($room->code, 'secret-1')['messages'][0]['body']);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'chat', ['body' => str_repeat('a', 281)]));
    }

    public function test_guests_receive_stable_random_characters_and_cannot_choose_them(): void
    {
        $this->postJson('/rooms', ['name' => 'Guest', 'character' => 'mariner'])->assertUnprocessable();
        $code = $this->postJson('/rooms', ['name' => 'Guest'])->assertCreated()->json('code');
        $view = $this->getJson('/rooms/'.$code.'/state')->assertOk()->json();
        $character = $view['me']['character'];
        $this->assertContains($character, array_column(config('game.characters'), 'id'));
        $this->assertSame($character, $view['players'][0]['character']);
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', $character);
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => 'baker', 'phase_id' => 1])->assertUnprocessable();
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => 1])->assertForbidden();
        $this->withSession(['chanting.identity' => 'new-guest']);
        $this->postJson('/rooms/join', ['name' => 'Other guest', 'code' => $code, 'character' => 'baker'])->assertUnprocessable();
        $this->postJson('/rooms/join', ['name' => 'Other guest', 'code' => $code])->assertOk();
        $this->assertCount(2, GameRoom::first()->state['players']);
    }

    public function test_accounts_can_choose_every_character_and_change_only_in_lobby(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/rooms', ['name' => 'Neighbor', 'character' => 'invented'])->assertUnprocessable();
        $code = $this->postJson('/rooms', ['name' => 'Neighbor', 'character' => 'botanist'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', 'botanist');
        foreach (config('game.characters') as $character) {
            $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => $character['id'], 'phase_id' => 1])
                ->assertOk()->assertJsonPath('me.character', $character['id']);
        }
        $nextCode = $this->postJson('/rooms', ['name' => 'Neighbor'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$nextCode.'/state')->assertJsonPath('me.character', 'musician');
        $room = GameRoom::where('code', $code)->firstOrFail();
        $s = $room->state;
        $s['phase'] = 'reveal';
        $room->update(['state' => $s]);
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'character' => 'baker', 'phase_id' => 1])->assertUnprocessable();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', 'musician');
        $this->withSession(['chanting.identity' => 'account-join']);
        $this->postJson('/rooms/join', ['name' => 'Joined', 'code' => $nextCode, 'character' => 'archivist'])->assertOk();
        $this->getJson('/rooms/'.$nextCode.'/state')->assertJsonPath('me.character', 'archivist');
    }

    public function test_characters_survive_roles_rematches_and_legacy_rooms(): void
    {
        [$room, $identities] = $this->match();
        $before = array_column($room->fresh()->state['players'], 'character', 'id');
        $s = $room->fresh()->state;
        $s['phase'] = 'finished';
        $room->update(['state' => $s, 'deadline' => null]);
        $this->act($room, 'secret-0', 'rematch');
        $this->assertSame($before, array_column($room->fresh()->state['players'], 'character', 'id'));
        $s = $room->fresh()->state;
        foreach ($s['players'] as &$player) {
            unset($player['character']);
        }
        unset($player);
        $room->update(['state' => $s]);
        $first = $this->engine->access($room->code, 'secret-0');
        $second = $this->engine->access($room->code, 'secret-0');
        $this->assertSame(array_column($first['players'], 'character'), array_column($second['players'], 'character'));
        $this->assertContains($first['me']['character'], array_column(config('game.characters'), 'id'));
    }

    public function test_discussion_readiness_is_public_and_unanimous_readiness_starts_voting_once(): void
    {
        [$room, $identities] = $this->match();
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->expire($room);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->expire($room);
        $phase = $room->fresh()->state['phase_id'];
        $this->withSession(['chanting.identity' => 'secret-0']);
        $view = $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'discussion_ready', 'phase_id' => $phase])->assertOk()->json();
        $this->assertSame('discussion', $view['phase']);
        $this->assertTrue($view['me']['submitted']);
        $other = $this->engine->access($room->code, 'secret-1');
        $this->assertCount(1, array_filter($other['players'], fn (array $p): bool => $p['discussion_ready']));
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        $this->act($room, 'secret-0', 'chat', ['body' => 'Still here if you have questions.']);
        foreach ($identities as $identity) {
            if ($identity !== 'secret-0') {
                $view = $this->act($room, $identity, 'discussion_ready');
            }
        }
        $this->assertSame('voting', $view['phase']);
        $this->assertSame($phase + 1, $view['phase_id']);
        $this->assertFalse($view['me']['submitted']);
        $this->assertCount(0, array_filter($view['players'], fn (array $p): bool => $p['discussion_ready']));
        $this->engine->resolve($room->id);
        $this->assertSame($phase + 1, $room->fresh()->state['phase_id']);
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'discussion_ready', 'phase_id' => $phase])->assertUnprocessable();
    }

    public function test_banished_players_cannot_ready_and_do_not_hold_up_discussion(): void
    {
        [$room, $identities] = $this->match();
        $this->expire($room);
        $this->expire($room);
        $s = $room->fresh()->state;
        $deadId = array_search('secret-0', $identities, true);
        $s['players'][$deadId]['alive'] = false;
        $room->update(['state' => $s]);
        $this->assertRejected(fn () => $this->act($room, 'secret-0', 'discussion_ready'));
        foreach ($identities as $identity) {
            if ($identity !== 'secret-0') {
                $this->act($room, $identity, 'discussion_ready');
            }
        }
        $this->assertSame('voting', $room->fresh()->state['phase']);
    }

    public function test_readiness_deadline_still_advances_and_home_goals_match_game_rules(): void
    {
        [$room] = $this->match();
        $this->expire($room);
        $this->expire($room);
        $this->act($room, 'secret-0', 'discussion_ready');
        $this->expire($room);
        $this->assertSame('voting', $room->state['phase']);
        $this->assertSame([6, 6, 6, 8, 9, 10, 11, 12], array_column($this->engine->rules()['ritual_goals'], 'steps'));
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected invalid action to be rejected.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }
}
