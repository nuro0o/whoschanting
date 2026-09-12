<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\MatchEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GameConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_serializes_competing_actions_and_deadline_workers(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run against a dedicated MySQL test database to verify row locks.');
        }
        Event::fake([RoomUpdated::class]);
        $engine = app(MatchEngine::class);
        $room = $engine->create('race-0', 'Racer 0');
        for ($i = 1; $i < 5; $i++) {
            $engine->join($room->code, 'race-'.$i, 'Racer '.$i);
        }
        for ($i = 0; $i < 5; $i++) {
            $engine->access($room->code, 'race-'.$i, ['type' => 'ready', 'phase_id' => 1]);
        }
        $engine->access($room->code, 'race-0', ['type' => 'start', 'phase_id' => 1]);
        for ($i = 0; $i < 5; $i++) {
            $engine->access($room->code, 'race-'.$i, ['type' => 'ready', 'phase_id' => 2]);
        }
        $s = $room->fresh()->state;
        $s['mission'] = config('game.missions.concord');
        $room->update(['state' => $s]);
        $cult = array_values(array_filter($s['players'], fn ($p) => $p['alignment'] === 'cult'));
        $identity = 'race-'.substr($cult[0]['name'], -1);
        $workers = [
            $this->worker(['action', $room->code, $identity, '3']),
            $this->worker(['action', $room->code, $identity, '3']),
        ];
        foreach ($workers as $worker) {
            $worker->start();
        }
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $results[] = $worker->getOutput();
        }
        sort($results);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertCount(1, $room->fresh()->state['actions']);
        $engine->access($room->code, 'race-'.substr($cult[1]['name'], -1), ['type' => 'night', 'phase_id' => 3]);
        $room->update(['deadline' => now()->subSecond()]);
        $workers = [$this->worker(['resolve', (string) $room->id]), $this->worker(['resolve', (string) $room->id])];
        foreach ($workers as $worker) {
            $worker->start();
        }
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
        }
        $room->refresh();
        $this->assertSame('discussion', $room->state['phase']);
        $this->assertSame(4, $room->state['phase_id']);
        $this->assertSame(2, $room->state['tokens']);
        $this->assertCount(2, $room->state['awards']);
    }

    /** @param list<string> $args */
    private function worker(array $args): Process
    {
        $db = config('database.connections.mysql');

        return new Process([PHP_BINARY, base_path('tests/Support/game-worker.php'), ...$args], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
        ], timeout: 15);
    }
}
