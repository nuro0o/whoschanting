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

class TableExperienceTest extends TestCase
{
    use RefreshDatabase;

    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(MatchEngine::class);
        Event::fake([RoomUpdated::class]);
    }

    /** @return array{GameRoom, list<string>} */
    private function gathering(bool $start = true): array
    {
        $room = $this->engine->create('seat-0', 'Player 0');
        for ($i = 1; $i < 5; $i++) {
            $this->engine->join($room->code, 'seat-'.$i, 'Player '.$i);
        }
        if ($start) {
            for ($i = 0; $i < 5; $i++) {
                $this->act($room, $i, 'ready');
            }
            $this->act($room, 0, 'start');
            $s = $room->fresh()->state;
            $s['phase'] = 'discussion';
            $s['phase_id']++;
            $s['day'] = 1;
            $s['actions'] = [];
            $room->update(['state' => $s, 'deadline' => now()->addSeconds(90)]);
        }

        return [$room, array_keys($room->fresh()->state['players'])];
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function act(GameRoom $room, int $seat, string $type, array $extra = []): array
    {
        return $this->engine->access($room->code, 'seat-'.$seat, ['type' => $type, 'phase_id' => $room->fresh()->state['phase_id'], ...$extra]);
    }

    private function denied(callable $action): void
    {
        try {
            $action();
            $this->fail('The invalid action was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    /** Finish through the real vote resolver and archive, with a Town victory. */
    private function finish(GameRoom $room): void
    {
        $s = $room->fresh()->state;
        $cult = array_keys(array_filter($s['players'], fn (array $p): bool => $p['alignment'] === 'cult'));
        foreach ($cult as $id) {
            $s['players'][$id]['alive'] = $id === $cult[0];
        }
        $s['phase'] = 'voting';
        $s['phase_id']++;
        $s['actions'] = [];
        foreach ($s['players'] as $id => $p) {
            if ($p['alive'] && $p['alignment'] === 'town') {
                $s['actions'][$id] = ['type' => 'vote', 'target' => $cult[0]];
            }
        }
        $room->update(['state' => $s, 'deadline' => now()->subSecond()]);
        $this->engine->resolve($room->id);
        $this->assertSame('finished', $room->fresh()->state['phase']);
    }

    public function test_claims_are_unverified_immutable_dated_and_do_not_submit_a_role_action(): void
    {
        [$room, $ids] = $this->gathering();
        $view = $this->act($room, 0, 'claim', ['role' => 'counterfeiter', 'body' => 'I am the Counterfeiter.', 'target' => $ids[1]]);
        $this->assertFalse($view['me']['submitted']);
        $this->assertSame('counterfeiter', $view['table']['claims'][0]['role']);
        $this->assertSame(1, $view['table']['claims'][0]['day']);
        $other = $this->engine->access($room->code, 'seat-1');
        $this->assertSame($view['table']['claims'], $other['table']['claims']);
        $this->assertArrayNotHasKey('alignment', $other['players'][0]);
        $this->denied(fn () => $this->act($room, 0, 'claim', ['role' => 'oracle', 'body' => 'Changed story']));
        $s = $room->fresh()->state;
        $s['day'] = 2;
        $room->update(['state' => $s]);
        $next = $this->act($room, 0, 'claim', ['role' => 'oracle', 'body' => 'My new story']);
        $this->assertSame([1, 2], array_column($next['table']['claims'], 'day'));
    }

    public function test_statements_require_living_discussion_valid_role_body_and_room_target(): void
    {
        [$room, $ids] = $this->gathering();
        foreach ([['role' => 'invented', 'body' => 'A claim'], ['role' => 'oracle', 'body' => ' '], ['role' => 'oracle', 'body' => 'A claim', 'target' => (string) Str::uuid()]] as $payload) {
            $this->denied(fn () => $this->act($room, 0, 'claim', $payload));
        }
        $s = $room->fresh()->state;
        $s['players'][$ids[0]]['alive'] = false;
        $room->update(['state' => $s]);
        $this->denied(fn () => $this->act($room, 0, 'discussion_response', ['body' => 'A clue']));
        $s['phase'] = 'night';
        $room->update(['state' => $s]);
        $this->denied(fn () => $this->act($room, 1, 'claim', ['role' => 'oracle', 'body' => 'A claim']));
    }

    public function test_prompts_are_shared_across_factions_with_one_response_each_day(): void
    {
        [$room] = $this->gathering();
        $first = $this->engine->access($room->code, 'seat-0');
        $other = $this->engine->access($room->code, 'seat-1');
        $this->assertSame($first['table']['prompt'], $other['table']['prompt']);
        $view = $this->act($room, 0, 'discussion_response', ['body' => 'Ask Player 1 who they protected.']);
        $this->assertSame($view['table']['prompt']['id'], $view['table']['responses'][0]['prompt_id']);
        $this->assertFalse($view['me']['submitted']);
        $this->denied(fn () => $this->act($room, 0, 'discussion_response', ['body' => 'A second answer']));
        $s = $room->fresh()->state;
        $s['day']++;
        $room->update(['state' => $s]);
        $next = $this->act($room, 0, 'discussion_response', ['body' => 'Today I changed my mind.']);
        $this->assertNotSame($first['table']['prompt'], $next['table']['prompt']);
        $this->assertCount(2, $next['table']['responses']);
    }

    public function test_mind_mist_cannot_read_or_write_statements_through_the_new_board(): void
    {
        [$room, $ids] = $this->gathering();
        $this->act($room, 0, 'claim', ['role' => 'oracle', 'body' => 'Private through mist']);
        $this->act($room, 0, 'discussion_response', ['body' => 'Discuss this clue']);
        $s = $room->fresh()->state;
        $s['players'][$ids[1]]['curse'] = ['id' => (string) Str::uuid(), 'type' => 'mist', 'level' => 1, 'day' => 1, 'challenge' => null];
        $room->update(['state' => $s]);
        $view = $this->engine->access($room->code, 'seat-1');
        $this->assertSame([], $view['table']['claims']);
        $this->assertSame([], $view['table']['responses']);
        $this->denied(fn () => $this->act($room, 1, 'discussion_response', ['body' => 'Bypass mist']));
    }

    public function test_extension_requires_every_living_player_and_only_adds_time_once(): void
    {
        [$room] = $this->gathering();
        $deadline = $room->fresh()->deadline;
        $this->act($room, 0, 'discussion_ready');
        for ($i = 0; $i < 4; $i++) {
            $view = $this->act($room, $i, 'extend_discussion');
            $this->assertFalse($view['table']['extension']['used']);
            $this->assertTrue($room->fresh()->deadline->equalTo($deadline));
        }
        $this->denied(fn () => $this->act($room, 0, 'extend_discussion'));
        $view = $this->act($room, 4, 'extend_discussion');
        $this->assertTrue($view['table']['extension']['used']);
        $this->assertTrue($room->fresh()->deadline->equalTo($deadline->addSeconds(30)));
        $this->assertSame('discussion', $view['phase']);
        $this->assertFalse($view['players'][0]['discussion_ready']);
        $this->denied(fn () => $this->act($room, 1, 'extend_discussion'));
        $room->refresh();
        $room->update(['deadline' => now()->subSecond()]);
        $this->engine->resolve($room->id);
        $view = $this->engine->access($room->code, 'seat-0');
        $this->assertFalse($view['table']['extension']['used']);
        $this->assertSame([], $view['table']['extension']['voter_ids']);
    }

    public function test_banished_players_cannot_extend_and_do_not_count_toward_agreement(): void
    {
        [$room, $ids] = $this->gathering();
        $s = $room->fresh()->state;
        $s['players'][$ids[4]]['alive'] = false;
        $room->update(['state' => $s]);
        $this->denied(fn () => $this->act($room, 4, 'extend_discussion'));
        for ($i = 0; $i < 4; $i++) {
            $view = $this->act($room, $i, 'extend_discussion');
        }
        $this->assertTrue($view['table']['extension']['used']);
    }

    public function test_prediction_is_private_sealed_and_scored_against_the_original_living_seats(): void
    {
        [$room, $ids] = $this->gathering();
        $s = $room->fresh()->state;
        // Make a known observer without depending on the random deal.
        $s['players'][$ids[0]]['role'] = 'townsperson';
        $s['players'][$ids[0]]['alignment'] = 'town';
        $s['players'][$ids[0]]['alive'] = false;
        $s['players'][$ids[1]]['alignment'] = 'cult';
        $s['players'][$ids[2]]['alignment'] = 'cult';
        $s['players'][$ids[3]]['alignment'] = 'town';
        $s['players'][$ids[4]]['alignment'] = 'town';
        $room->update(['state' => $s]);
        $view = $this->act($room, 0, 'prediction', ['cultist_ids' => [$ids[1], $ids[2]], 'winner' => 'town']);
        $this->assertSame([$ids[1], $ids[2]], $view['me']['prediction']['cultist_ids']);
        $this->assertArrayNotHasKey('living_ids', $view['me']['prediction']);
        $this->assertArrayNotHasKey('correct_picks', $view['me']['prediction']);
        $this->assertSame([], $view['table']['predictions']);
        $other = $this->engine->access($room->code, 'seat-1');
        $this->assertNull($other['me']['prediction']);
        $this->assertNull($other['recap']);
        $this->denied(fn () => $this->act($room, 0, 'prediction', ['cultist_ids' => [], 'winner' => 'cult']));
        $this->finish($room);
        $view = $this->engine->access($room->code, 'seat-1');
        $result = $view['table']['predictions'][0];
        $this->assertTrue($result['exact']);
        $this->assertTrue($result['winner_correct']);
        $this->assertFalse($result['informed']);
        $this->assertSame(2, $result['correct_picks']);
        $this->assertSame($view['table']['predictions'], $view['recap']['predictions']);
        $this->assertSame($view['recap']['predictions'], GameMatch::first()->recap['predictions']);
    }

    public function test_predictions_reject_living_observers_invalid_duplicate_or_dead_targets(): void
    {
        [$room, $ids] = $this->gathering();
        $this->denied(fn () => $this->act($room, 0, 'prediction', ['cultist_ids' => [], 'winner' => 'town']));
        $s = $room->fresh()->state;
        $s['players'][$ids[0]]['alive'] = false;
        $room->update(['state' => $s]);
        foreach ([[$ids[0]], [(string) Str::uuid()], [$ids[1], $ids[1]]] as $suspects) {
            $this->denied(fn () => $this->act($room, 0, 'prediction', ['cultist_ids' => $suspects, 'winner' => 'town']));
        }
        $view = $this->act($room, 0, 'prediction', ['cultist_ids' => [], 'winner' => 'cult']);
        $this->assertSame([], $view['me']['prediction']['cultist_ids']);
    }

    public function test_host_transfer_changes_permissions_and_lobby_removal_clears_readiness(): void
    {
        [$room, $ids] = $this->gathering(false);
        $this->denied(fn () => $this->act($room, 1, 'transfer_host', ['target' => $ids[2]]));
        $this->denied(fn () => $this->act($room, 0, 'remove_player', ['target' => $ids[0]]));
        $this->act($room, 0, 'transfer_host', ['target' => $ids[1]]);
        $this->denied(fn () => $this->act($room, 0, 'remove_player', ['target' => $ids[2]]));
        $this->act($room, 0, 'ready');
        $view = $this->act($room, 1, 'remove_player', ['target' => $ids[2]]);
        $this->assertSame($ids[1], $view['host_id']);
        $this->assertCount(4, $view['players']);
        $this->assertFalse($view['players'][0]['ready']);
        $this->assertNull($this->engine->playerId($room->fresh()->state, 'seat-2'));
        $this->withSession(['chanting.identity' => 'seat-2'])->getJson('/rooms/'.$room->code.'/state')->assertForbidden();
    }

    public function test_host_can_transfer_during_play_but_cannot_remove_a_seat(): void
    {
        [$room, $ids] = $this->gathering();
        $this->denied(fn () => $this->act($room, 0, 'remove_player', ['target' => $ids[1]]));
        $view = $this->act($room, 0, 'transfer_host', ['target' => $ids[1]]);
        $this->assertSame($ids[1], $view['host_id']);
    }

    public function test_feedback_is_private_persisted_once_and_available_to_playtest_summary_after_rematch(): void
    {
        [$room, $ids] = $this->gathering();
        $this->denied(fn () => $this->act($room, 0, 'feedback', ['engagement' => 'waiting']));
        $this->act($room, 0, 'claim', ['role' => 'oracle', 'body' => 'My story']);
        $this->finish($room);
        $view = $this->act($room, 0, 'feedback', ['engagement' => 'waiting', 'body' => 'I wanted more discussion.']);
        $this->assertSame('waiting', $view['me']['feedback']['engagement']);
        $other = $this->engine->access($room->code, 'seat-1');
        $this->assertNull($other['me']['feedback']);
        $this->assertArrayNotHasKey('feedback', $other['recap']);
        $archive = GameMatch::first();
        $this->assertSame('waiting', $archive->recap['feedback'][$ids[0]]['engagement']);
        $this->assertCount(1, $archive->recap['claims']);
        $this->denied(fn () => $this->act($room, 0, 'feedback', ['engagement' => 'engaged']));
        $view = $this->act($room, 0, 'rematch');
        $this->assertSame([], $view['table']['claims']);
        $this->assertSame([], $view['table']['responses']);
        $this->assertSame([], $view['table']['predictions']);
        $this->assertNull($view['me']['feedback']);
        $this->assertNull($view['me']['prediction']);
        $this->assertSame('waiting', $archive->fresh()->recap['feedback'][$ids[0]]['engagement']);
        $this->artisan('game:feedback')->expectsOutputToContain('Voluntary responses')->assertSuccessful();
        $this->artisan('game:feedback', ['--rules-version' => 'nonexistent'])->expectsOutputToContain('No engagement feedback yet')->assertSuccessful();
    }

    public function test_http_validation_accepts_empty_prediction_and_rejects_malformed_social_actions(): void
    {
        [$room, $ids] = $this->gathering();
        $url = '/rooms/'.$room->code.'/actions';
        $phase = $room->fresh()->state['phase_id'];
        $this->withSession(['chanting.identity' => 'seat-0'])->postJson($url, ['type' => 'claim', 'phase_id' => $phase, 'role' => 'oracle', 'body' => 'I investigated last night.'])->assertOk()->assertJsonPath('table.claims.0.role', 'oracle');
        $this->postJson($url, ['type' => 'prediction', 'phase_id' => $phase, 'cultist_ids' => ['not-a-uuid'], 'winner' => 'town'])->assertUnprocessable();
        $this->postJson($url, ['type' => 'feedback', 'phase_id' => $phase, 'engagement' => 'invented'])->assertUnprocessable();
        $s = $room->fresh()->state;
        $s['players'][$ids[0]]['alive'] = false;
        $room->update(['state' => $s]);
        $this->postJson($url, ['type' => 'prediction', 'phase_id' => $phase, 'cultist_ids' => [], 'winner' => 'town'])->assertOk()->assertJsonPath('me.prediction.cultist_ids', []);
    }

    public function test_stale_social_action_cannot_land_in_the_next_phase(): void
    {
        [$room] = $this->gathering();
        $phase = $room->fresh()->state['phase_id'];
        $room->update(['deadline' => now()->subSecond()]);
        $this->denied(fn () => $this->engine->access($room->code, 'seat-0', ['type' => 'claim', 'phase_id' => $phase, 'role' => 'oracle', 'body' => 'Too late']));
        $this->assertSame('voting', $room->fresh()->state['phase']);
        $this->assertSame([], $room->fresh()->state['claims'] ?? []);
    }
}
