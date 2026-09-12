<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use App\Game\VillageNames;
use App\Models\GameMatch;
use App\Models\GameRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_http_creation_replaces_toxic_names_and_chat_stays_masked(): void
    {
        $code = $this->postJson('/rooms', ['name' => 'shithead', 'visibility' => 'public'])->assertCreated()->json('code');
        $state = GameRoom::where('code', $code)->firstOrFail()->state;
        $name = $state['players'][$state['host_id']]['name'];
        $this->assertContains($name, (new VillageNames)->all());
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'chat', 'phase_id' => 1, 'body' => 'That is sh1t!'])
            ->assertOk()->assertJsonPath('me.name', $name)->assertJsonPath('messages.0.body', 'That is ****!');
        $state = GameRoom::where('code', $code)->firstOrFail()->state;
        $this->assertSame($name, $state['players'][$state['host_id']]['name']);
        $this->assertSame($name, $state['messages'][0]['name']);
        $this->assertSame('That is ****!', $state['messages'][0]['body']);
        $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('messages.0.body', 'That is ****!');
        $this->getJson('/rooms/public')->assertOk()->assertJsonPath('rooms.0.host_name', $name);
    }

    public function test_join_checks_uniqueness_using_the_name_players_will_see(): void
    {
        $room = $this->engine->create('host', 'Captain');
        $this->engine->join($room->code, 'guest', 'Shit sailor');
        $name = $this->engine->access($room->code, 'guest')['me']['name'];
        $this->assertContains($name, (new VillageNames)->all());
        $this->engine->join($room->code, 'other', 'Shit sailor');
        $otherName = $this->engine->access($room->code, 'other')['me']['name'];
        $this->assertContains($otherName, (new VillageNames)->all());
        $this->assertNotSame($name, $otherName);
        $this->engine->join($room->code, 'guest', 'Fuck sailor');
        $this->assertSame($name, $this->engine->access($room->code, 'guest')['me']['name']);
        $this->expectException(ValidationException::class);
        $this->engine->join($room->code, 'duplicate', mb_strtolower($name));
    }

    public function test_http_join_replaces_an_obfuscated_compound_name(): void
    {
        $room = $this->engine->create('host', 'Captain');
        $this->withSession(['chanting.identity' => 'guest'])->postJson('/rooms/join', [
            'code' => $room->code, 'name' => 'xxSh1thead99',
        ])->assertOk();
        $name = $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->json('me.name');
        $this->assertContains($name, (new VillageNames)->all());
        $this->assertSame($name, $this->engine->access($room->code, 'guest')['me']['name']);
    }

    public function test_statements_defenses_and_feedback_stay_masked_in_views_and_archives(): void
    {
        $room = $this->engine->create('host', 'Shit captain');
        $this->engine->join($room->code, 'guest', 'Sailor');
        $this->engine->join($room->code, 'third', 'Oracle');
        foreach (['host', 'guest', 'third'] as $identity) {
            $this->act($room, $identity, 'ready');
        }
        $this->act($room, 'host', 'start');
        $s = $room->fresh()->state;
        $s['phase'] = 'discussion';
        $s['day'] = 1;
        $s['phase_id']++;
        $s['actions'] = [];
        $room->update(['state' => $s, 'deadline' => now()->addSeconds(90)]);

        $view = $this->act($room, 'host', 'claim', ['role' => 'oracle', 'body' => 'This is bullshit.']);
        $this->assertSame('This is ********.', $view['table']['claims'][0]['body']);
        $view = $this->act($room, 'host', 'discussion_response', ['body' => 'A sh1t story.']);
        $this->assertSame('A **** story.', $view['table']['responses'][0]['body']);
        $this->assertSame($view['table'], $this->engine->access($room->code, 'guest')['table']);

        $host = $s['host_id'];
        $this->act($room, 'guest', 'accuse', ['target' => $host]);
        foreach (['host', 'guest', 'third'] as $identity) {
            $this->act($room, $identity, 'discussion_ready');
        }
        $view = $this->act($room, 'host', 'defend', ['body' => 'That is fucking wrong.']);
        $this->assertSame('That is ******* wrong.', $view['table']['last_words']['defenses'][0]['body']);

        $s = $room->fresh()->state;
        $cult = array_keys(array_filter($s['players'], fn (array $player): bool => $player['alignment'] === 'cult'))[0];
        $s['phase'] = 'voting';
        $s['phase_id']++;
        $s['actions'] = [];
        foreach ($s['players'] as $id => $player) {
            if ($player['alignment'] === 'town') {
                $s['actions'][$id] = ['type' => 'vote', 'target' => $cult];
            }
        }
        $room->update(['state' => $s, 'deadline' => now()->subSecond()]);
        $this->engine->resolve($room->id);
        $view = $this->act($room, 'host', 'feedback', ['engagement' => 'mixed', 'body' => 'Shit luck.']);
        $this->assertSame('finished', $view['phase']);
        $this->assertSame('**** luck.', $view['me']['feedback']['body']);
        $recap = GameMatch::findOrFail($s['match_id'])->recap;
        $this->assertSame($s['players'][$host]['name'], $recap['players'][0]['name']);
        $this->assertContains($recap['players'][0]['name'], (new VillageNames)->all());
        $this->assertSame('This is ********.', $recap['claims'][0]['body']);
        $this->assertSame('A **** story.', $recap['responses'][0]['body']);
        $this->assertSame('That is ******* wrong.', $recap['rounds'][0]['last_words']['defenses'][0]['body']);
        $this->assertSame('**** luck.', $recap['feedback'][$host]['body']);
        $this->assertNull($this->engine->access($room->code, 'guest')['me']['feedback']);
    }

    public function test_existing_room_text_is_masked_without_changing_identity_or_action_targets(): void
    {
        $room = $this->engine->create('host', 'Captain');
        $s = $room->state;
        $host = $s['host_id'];
        $s['players'][$host]['name'] = 'Shit captain';
        $s['players'][$host]['results'] = [['kind' => 'tracking', 'day' => 1, 'target' => 'Shit captain', 'visited_target' => 'Fuck sailor']];
        $s['players'][$host]['curse_notice'] = 'Your choice turned toward Shit captain.';
        $s['messages'] = [['id' => 'old-message', 'player_id' => $host, 'name' => 'Shit captain', 'body' => 'Shit!', 'day' => 0, 'sent_at' => now()->toISOString()]];
        $s['log'][] = 'Shit captain is now the host.';
        $s['actions'][$host] = ['type' => 'vote', 'target' => $host];
        $room->update(['state' => $s]);
        $view = $this->engine->access($room->code, 'host');
        $name = $view['me']['name'];
        $this->assertContains($name, (new VillageNames)->all());
        $this->assertSame($name, $room->fresh()->state['players'][$host]['name']);
        $this->assertSame($name, $this->engine->access($room->code, 'host')['me']['name']);
        $this->assertSame($name, $view['messages'][0]['name']);
        $this->assertSame($name, $view['me']['results'][0]['target']);
        $this->assertSame('****!', $view['messages'][0]['body']);
        $this->assertSame('**** sailor', $view['me']['results'][0]['visited_target']);
        $this->assertSame('Your choice turned toward '.$name.'.', $view['me']['curse_notice']);
        $this->assertSame($name.' is now the host.', $view['log'][1]);
        $this->act($room, 'host', 'chat', ['body' => 'Hello']);
        $saved = $room->fresh()->state;
        $this->assertSame($s['players'][$host]['identity'], $saved['players'][$host]['identity']);
        $this->assertSame($s['actions'], $saved['actions']);
        $this->assertSame($name, $saved['players'][$host]['name']);
    }
}
