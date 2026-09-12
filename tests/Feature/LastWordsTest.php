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

class LastWordsTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(MatchEngine::class);
        Event::fake([RoomUpdated::class]);
        $this->travelTo(now()->startOfSecond());
    }

    /** @return array{GameRoom, list<string>} */
    private function gathering(): array
    {
        $room = $this->engine->create('words-0', 'Player 0');
        for ($i = 1; $i < 5; $i++) {
            $this->engine->join($room->code, 'words-'.$i, 'Player '.$i);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->act($room, $i, 'ready');
        }
        $this->act($room, 0, 'start');
        for ($i = 0; $i < 5; $i++) {
            $this->act($room, $i, 'ready');
        }
        $this->expire($room);

        return [$room, array_keys($room->fresh()->state['players'])];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, int $seat, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, 'words-'.$seat, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function expire(GameRoom $room): void
    {
        $room->refresh()->update(['deadline' => now()->subSecond()]);
        $this->engine->resolve($room->id);
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

    public function test_public_accusations_are_optional_immutable_and_separate_from_actions(): void
    {
        [$room, $ids] = $this->gathering();
        $view = $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $this->assertFalse($view['me']['submitted']);
        $this->assertSame([], $room->fresh()->state['actions']);
        $other = $this->engine->access($room->code, 'words-2');
        $this->assertSame($view['table']['last_words'], $other['table']['last_words']);
        $this->assertArrayNotHasKey('alignment', $other['players'][1]);
        $this->denied(fn () => $this->act($room, 0, 'accuse', ['target' => $ids[2]]));
        $this->act($room, 0, 'discussion_ready');
        $this->assertSame('discussion', $room->fresh()->state['phase']);
    }

    public function test_accusations_reject_self_dead_foreign_and_missing_targets_and_dead_accusers(): void
    {
        [$room, $ids] = $this->gathering();
        $s = $room->fresh()->state;
        $s['players'][$ids[4]]['alive'] = false;
        $room->update(['state' => $s]);
        foreach ([$ids[0], $ids[4], (string) Str::uuid(), null] as $target) {
            $this->denied(fn () => $this->act($room, 0, 'accuse', ['target' => $target]));
        }
        $this->denied(fn () => $this->act($room, 4, 'accuse', ['target' => $ids[1]]));
        $this->expire($room);
        $this->denied(fn () => $this->act($room, 0, 'accuse', ['target' => $ids[1]]));
    }

    public function test_most_accused_including_ties_get_a_shared_snapshotted_window(): void
    {
        [$room, $ids] = $this->gathering();
        foreach ([0 => 1, 2 => 1, 1 => 2, 3 => 2, 4 => 0] as $seat => $target) {
            $this->act($room, $seat, 'accuse', ['target' => $ids[$target]]);
        }
        config(['game.seconds.last_words' => 90]);
        for ($i = 0; $i < 5; $i++) {
            $view = $this->act($room, $i, 'discussion_ready');
        }
        $this->assertSame('last_words', $view['phase']);
        $this->assertEqualsCanonicalizing([$ids[1], $ids[2]], $view['table']['last_words']['accused_ids']);
        $this->assertTrue($room->fresh()->deadline->equalTo(now()->addSeconds(30)));
        $this->assertFalse($view['me']['submitted']);
        $this->assertSame([], $room->fresh()->state['actions']);
        $this->assertSame(30, $view['rules']['seconds']['last_words']);
    }

    public function test_no_accusations_and_legacy_matches_go_straight_to_voting(): void
    {
        [$room] = $this->gathering();
        $this->expire($room);
        $this->assertSame('voting', $room->fresh()->state['phase']);
        [$legacy, $ids] = $this->gathering();
        $s = $legacy->fresh()->state;
        unset($s['match_rules']['seconds']['last_words']);
        $legacy->update(['state' => $s]);
        $this->denied(fn () => $this->act($legacy, 0, 'accuse', ['target' => $ids[1]]));
        $view = $this->engine->access($legacy->code, 'words-0');
        $this->assertNull($view['table']['last_words']);
        $this->expire($legacy);
        $this->assertSame('voting', $legacy->fresh()->state['phase']);
    }

    public function test_only_accused_can_defend_once_and_everyone_has_time_to_read(): void
    {
        [$room, $ids] = $this->gathering();
        $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $this->denied(fn () => $this->act($room, 1, 'defend', ['body' => 'Too early']));
        $this->expire($room);
        $deadline = $room->fresh()->deadline;
        foreach (['', ' ', str_repeat('x', 281)] as $body) {
            $this->denied(fn () => $this->act($room, 1, 'defend', ['body' => $body]));
        }
        $this->denied(fn () => $this->act($room, 0, 'defend', ['body' => 'Not accused']));
        $view = $this->act($room, 1, 'defend', ['body' => '  I protected the Oracle.  ']);
        $this->assertSame([['player_id' => $ids[1], 'body' => 'I protected the Oracle.']], $view['table']['last_words']['defenses']);
        $this->assertSame('last_words', $view['phase']);
        $this->assertTrue($room->fresh()->deadline->equalTo($deadline));
        $this->denied(fn () => $this->act($room, 1, 'defend', ['body' => 'Changed my story']));
        foreach (['chat' => ['body' => 'Interrupt'], 'vote' => ['target' => $ids[2]], 'discussion_ready' => [], 'extend_discussion' => []] as $type => $payload) {
            $this->denied(fn () => $this->act($room, 1, $type, $payload));
        }
        $this->expire($room);
        $view = $this->act($room, 0, 'vote', ['target' => $ids[3]]);
        $this->assertSame('voting', $view['phase']);
        $this->assertSame($ids[3], $room->fresh()->state['actions'][$ids[0]]['target']);
        $this->assertCount(1, $view['table']['last_words']['defenses']);
        $this->act($room, 1, 'chat', ['body' => 'Chat is open again']);
    }

    public function test_deadline_commits_before_rejecting_a_late_defense_and_resolves_once(): void
    {
        [$room, $ids] = $this->gathering();
        $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $this->expire($room);
        $phaseId = $room->fresh()->state['phase_id'];
        $room->refresh()->update(['deadline' => now()->subSecond()]);
        $this->denied(fn () => $this->engine->access($room->code, 'words-1', ['type' => 'defend', 'phase_id' => $phaseId, 'body' => 'Too late']));
        $this->engine->resolve($room->id);
        $s = $room->fresh()->state;
        $this->assertSame('voting', $s['phase']);
        $this->assertSame($phaseId + 1, $s['phase_id']);
        $this->assertSame([], $s['rounds'][1]['last_words']['defenses']);
        $this->assertNull($s['winner']);
    }

    public function test_mist_hides_defenses_and_blocking_curses_must_be_solved_before_defending(): void
    {
        [$room, $ids] = $this->gathering();
        $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $s = $room->fresh()->state;
        $curseId = (string) Str::uuid();
        $answer = (string) Str::uuid();
        $s['players'][$ids[1]]['curse'] = ['id' => $curseId, 'type' => 'mist', 'level' => 1, 'day' => 1, 'challenge' => ['kind' => 'sequence'], 'solution' => [$answer]];
        $room->update(['state' => $s]);
        $this->denied(fn () => $this->act($room, 1, 'accuse', ['target' => $ids[0]]));
        $this->expire($room);
        $view = $this->engine->access($room->code, 'words-1');
        $this->assertNull($view['table']['last_words']);
        $this->denied(fn () => $this->act($room, 1, 'defend', ['body' => 'Bypass the curse']));
        $view = $this->act($room, 1, 'solve_curse', ['curse_id' => $curseId, 'answer' => [$answer]]);
        $this->assertNull($view['me']['curse']);
        $this->assertNotNull($view['table']['last_words']);
        $this->act($room, 1, 'defend', ['body' => 'The mist has lifted.']);
    }

    public function test_final_ritual_waits_for_voting_then_archives_last_words_and_rematch_clears_them(): void
    {
        [$room, $ids] = $this->gathering();
        $s = $room->fresh()->state;
        foreach ($s['players'] as $id => &$player) {
            $player['alignment'] = $id === $ids[1] ? 'cult' : 'town';
            $player['role'] = $id === $ids[1] ? 'acolyte' : 'townsperson';
        }
        unset($player);
        $s['tokens'] = $s['threshold'];
        $room->update(['state' => $s]);
        $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $this->expire($room);
        $view = $this->act($room, 1, 'defend', ['body' => 'Trust me one more night.']);
        $this->assertTrue($view['ritual']['final_vote']);
        $this->assertNull($view['winner']);
        $this->expire($room);
        for ($i = 0; $i < 5; $i++) {
            $view = $this->act($room, $i, 'vote', ['target' => $i === 1 ? null : $ids[1]]);
        }
        $this->assertSame('town', $view['winner']);
        $record = $view['recap']['rounds'][0]['last_words'];
        $this->assertSame('Trust me one more night.', $record['defenses'][0]['body']);
        $this->assertSame($record, GameMatch::first()->recap['rounds'][0]['last_words']);
        $view = $this->act($room, 0, 'rematch');
        $this->assertNull($view['table']['last_words']);
        $this->assertArrayNotHasKey('rounds', $room->fresh()->state);
        $this->assertSame($record, GameMatch::first()->recap['rounds'][0]['last_words']);
    }

    public function test_accusations_reset_for_the_next_day_and_dead_players_can_watch_and_predict(): void
    {
        [$room, $ids] = $this->gathering();
        $this->act($room, 0, 'accuse', ['target' => $ids[1]]);
        $s = $room->fresh()->state;
        $s['players'][$ids[4]]['alive'] = false;
        $room->update(['state' => $s]);
        $this->expire($room);
        $view = $this->act($room, 4, 'prediction', ['cultist_ids' => [$ids[1]], 'winner' => 'town']);
        $this->assertSame([$ids[1]], $view['table']['last_words']['accused_ids']);
        $this->denied(fn () => $this->act($room, 4, 'defend', ['body' => 'A ghost speaks']));
        $this->expire($room);
        $this->expire($room);
        $this->expire($room);
        $view = $this->act($room, 0, 'accuse', ['target' => $ids[2]]);
        $this->assertSame(2, $view['day']);
        $this->assertSame([['player_id' => $ids[0], 'target_id' => $ids[2]]], $view['table']['last_words']['accusations']);
        $this->assertSame($ids[1], $room->fresh()->state['rounds'][1]['last_words']['accusations'][0]['target_id']);
    }

    public function test_http_actions_validate_and_publish_accusations_and_defenses(): void
    {
        [$room, $ids] = $this->gathering();
        $this->withSession(['chanting.identity' => 'words-0'])
            ->postJson('/rooms/'.$room->code.'/actions', ['type' => 'accuse', 'phase_id' => $room->fresh()->state['phase_id'], 'target' => $ids[1]])
            ->assertOk()->assertJsonPath('table.last_words.accusations.0.target_id', $ids[1]);
        $this->expire($room);
        $this->withSession(['chanting.identity' => 'words-1'])
            ->postJson('/rooms/'.$room->code.'/actions', ['type' => 'defend', 'phase_id' => $room->fresh()->state['phase_id'], 'body' => 'Hear my side.'])
            ->assertOk()->assertJsonPath('table.last_words.defenses.0.body', 'Hear my side.');
    }
}
