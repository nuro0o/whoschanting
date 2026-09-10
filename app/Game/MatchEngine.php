<?php

namespace App\Game;

use App\Events\RoomUpdated;
use App\Models\GameRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MatchEngine
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = config('game');
        $rules['ritual_goals'] = array_map(fn (int $players): array => [
            'players' => $players, 'steps' => max(6, (int) ceil($players * $rules['tokens_per_player'])),
        ], range($rules['min_players'], $rules['max_players']));

        return $rules;
    }

    public function create(string $identity, string $name, ?string $character = null): GameRoom
    {
        return DB::transaction(function () use ($identity, $name, $character): GameRoom {
            $id = (string) Str::uuid();
            do {
                $code = strtoupper(Str::random(6));
            } while (GameRoom::where('code', $code)->exists());

            return GameRoom::create(['code' => $code, 'state' => [
                'phase' => 'lobby', 'phase_id' => 1, 'revision' => 1, 'day' => 0,
                'host_id' => $id, 'players' => [$id => $this->seat($id, $identity, $name, $character)],
                'tokens' => 0, 'threshold' => 0, 'mission' => null, 'winner' => null,
                'win_reason' => null, 'actions' => [], 'awards' => [], 'messages' => [],
                'log' => ['A new gathering takes shape.'], 'cult_banished' => false,
            ]]);
        });
    }

    /** @return array<string, mixed> */
    private function seat(string $id, string $identity, string $name, ?string $character = null): array
    {
        $characters = array_column(config('game.characters'), 'id');
        $this->ensure($character === null || in_array($character, $characters, true), 'Choose a character from the village.');

        return ['id' => $id, 'identity' => hash('sha256', $identity), 'name' => $name,
            'alive' => true, 'ready' => false, 'role' => null, 'alignment' => null, 'results' => [],
            'character' => $character ?? $characters[random_int(0, count($characters) - 1)]];
    }

    public function join(string $code, string $identity, string $name, ?string $character = null): GameRoom
    {
        return DB::transaction(function () use ($code, $identity, $name, $character): GameRoom {
            $room = GameRoom::where('code', $code)->lockForUpdate()->firstOrFail();
            $s = $room->state;
            if ($this->playerId($s, $identity) !== null) {
                return $room;
            }
            $this->ensure($s['phase'] === 'lobby', 'This match has already begun.');
            $this->ensure(count($s['players']) < config('game.max_players'), 'This room is full.');
            foreach ($s['players'] as $player) {
                $this->ensure(mb_strtolower($player['name']) !== mb_strtolower($name), 'That name is already at the table.');
            }
            $id = (string) Str::uuid();
            $s['players'][$id] = $this->seat($id, $identity, $name, $character);
            $this->save($room, $s);

            return $room;
        });
    }

    /** @param array<string, mixed> $s */
    public function playerId(array $s, string $identity): ?string
    {
        foreach ($s['players'] as $id => $player) {
            if (hash_equals($player['identity'], hash('sha256', $identity))) {
                return $id;
            }
        }

        return null;
    }

    // Every reader and writer serializes on the same row. No client can resolve a phase.
    /** @param array<string, mixed>|null $action
     * @return array<string, mixed>
     */
    public function access(string $code, string $identity, ?array $action = null): array
    {
        $result = DB::transaction(function () use ($code, $identity, $action): array {
            $room = GameRoom::where('code', $code)->lockForUpdate()->firstOrFail();
            $s = $room->state;
            $id = $this->playerId($s, $identity);
            abort_if($id === null, 403, 'Join this room to take a seat.');
            if ($room->deadline?->isPast()) {
                $this->advance($room, $s);
                $this->save($room, $s);
            }
            // Commit expired phases even when an arriving action is stale.
            if ($action !== null && $action['phase_id'] !== $s['phase_id']) {
                return ['stale' => true];
            }
            if ($action !== null) {
                $this->act($room, $s, $id, $action);
                $this->save($room, $s);
            }

            return $this->view($room, $s, $id);
        });
        if (isset($result['stale'])) {
            $this->ensure(false, 'The phase changed. Refresh your view and choose again.');
        }

        return $result;
    }

    public function resolve(int $roomId): void
    {
        DB::transaction(function () use ($roomId): void {
            $room = GameRoom::whereKey($roomId)->lockForUpdate()->first();
            if ($room === null || ! $room->deadline?->isPast()) {
                return;
            }
            $s = $room->state;
            $this->advance($room, $s);
            $this->save($room, $s);
        });
    }

    /** @param array<string, mixed> $s
     * @param  array<string, mixed>  $a
     */
    private function act(GameRoom $room, array &$s, string $id, array $a): void
    {
        $p = $s['players'][$id];
        $type = $a['type'];
        if ($type === 'character') {
            $this->ensure($s['phase'] === 'lobby', 'Choose your character before the match begins.');
            $this->ensure(in_array($a['character'] ?? null, array_column(config('game.characters'), 'id'), true), 'Choose a character from the village.');
            $s['players'][$id]['character'] = $a['character'];

            return;
        }
        if ($type === 'chat') {
            $this->ensure(in_array($s['phase'], ['lobby', 'discussion', 'voting', 'finished']), 'The village is quiet during this phase.');
            $this->ensure($p['alive'] || $s['phase'] === 'finished', 'Banished players may only watch.');
            $body = trim($a['body'] ?? '');
            $this->ensure($body !== '' && mb_strlen($body) <= 280, 'Write a message of 1–280 characters.');
            $s['messages'][] = ['id' => (string) Str::uuid(), 'name' => $p['name'], 'body' => $body, 'day' => $s['day']];
            $s['messages'] = array_slice($s['messages'], -100);

            return;
        }
        if ($type === 'rematch') {
            $this->ensure($id === $s['host_id'] && $s['phase'] === 'finished', 'Only the host can start a new gathering after victory.');
            foreach ($s['players'] as &$player) {
                $player = array_replace($player, ['ready' => false, 'alive' => true, 'role' => null, 'alignment' => null, 'results' => []]);
            }
            unset($player);
            $s = array_replace($s, ['day' => 0, 'tokens' => 0, 'threshold' => 0, 'winner' => null, 'win_reason' => null,
                'mission' => null, 'awards' => [], 'messages' => [], 'log' => ['The village gathers again.'], 'cult_banished' => false]);
            $this->phase($room, $s, 'lobby');

            return;
        }
        if ($type === 'start') {
            $this->ensure($s['phase'] === 'lobby' && $s['host_id'] === $id, 'Only the host can begin from the lobby.');
            $count = count($s['players']);
            $this->ensure($count >= config('game.min_players') && $count <= config('game.max_players'), 'Gather between '.config('game.min_players').' and '.config('game.max_players').' players.');
            $cultistCount = config('game.cultists_by_player_count.'.$count);
            $this->ensure(is_int($cultistCount) && $cultistCount >= 1 && $cultistCount < $count, 'No valid role roster is configured for this room size.');
            $this->ensure(count(array_filter($s['players'], fn (array $p): bool => ! $p['ready'])) === 0, 'Every player must be ready.');
            $ids = array_keys($s['players']);
            shuffle($ids);
            foreach ($ids as $index => $pid) {
                $s['players'][$pid]['role'] = match (true) {
                    $index === 0 => 'veilweaver',
                    $index < $cultistCount => 'acolyte',
                    $index === $cultistCount => 'oracle',
                    default => 'townsperson',
                };
                $s['players'][$pid]['alignment'] = $index < $cultistCount ? 'cult' : 'town';
            }
            $s['threshold'] = max(6, (int) ceil($count * config('game.tokens_per_player')));
            $missions = config('game.missions');
            $s['mission'] = $missions[array_rand($missions)];
            $this->phase($room, $s, 'reveal');
            $s['log'][] = 'The roles are sealed. Keep yours close.';

            return;
        }
        if ($type === 'ready') {
            $this->ensure(in_array($s['phase'], ['lobby', 'reveal']), 'You cannot ready up now.');
            if ($s['phase'] === 'lobby') {
                $s['players'][$id]['ready'] = ! $p['ready'];
            } else {
                $this->ensure(! isset($s['actions'][$id]), 'You already acknowledged your role.');
                $s['actions'][$id] = ['type' => 'ready'];
                $this->advanceIfComplete($room, $s);
            }

            return;
        }
        $this->ensure($p['alive'], 'Banished players may only watch.');
        $this->ensure(! isset($s['actions'][$id]), 'Your action is already sealed for this phase.');
        $target = $a['target'] ?? null;
        if ($target !== null) {
            $this->ensure(isset($s['players'][$target]) && $s['players'][$target]['alive'] && $target !== $id, 'Choose another living player.');
        }
        if ($type === 'discussion_ready') {
            $this->ensure($s['phase'] === 'discussion', 'You can be ready for voting only during discussion.');
            $this->ensure($target === null, 'Readiness does not target another player.');
        } elseif ($type === 'night') {
            $this->ensure($s['phase'] === 'night', 'Night has ended.');
            $this->ensure($p['role'] !== 'oracle' || $target !== null, 'Choose someone to investigate.');
            $this->ensure(in_array($p['role'], ['oracle', 'veilweaver']) || $target === null, 'Your role does not target another player.');
        } elseif ($type === 'vote') {
            $this->ensure($s['phase'] === 'voting', 'Voting is not open.');
        } else {
            $this->ensure(false, 'Unknown action.');
        }
        $s['actions'][$id] = ['type' => $type, 'target' => $target];
        $this->advanceIfComplete($room, $s);
    }

    /** @param array<string, mixed> $s */
    private function advanceIfComplete(GameRoom $room, array &$s): void
    {
        $alive = array_filter($s['players'], fn (array $p): bool => $p['alive']);
        if (count($s['actions']) === count($alive)) {
            $this->advance($room, $s);
        }
    }

    /** @param array<string, mixed> $s */
    private function advance(GameRoom $room, array &$s): void
    {
        switch ($s['phase']) {
            case 'reveal':
                $s['day'] = 1;
                $this->phase($room, $s, 'night');
                break;
            case 'night':
                $this->resolveNight($s);
                if (! $this->victory($room, $s)) {
                    $this->phase($room, $s, 'discussion');
                }
                break;
            case 'discussion':
                $this->phase($room, $s, 'voting');
                break;
            case 'voting':
                $this->resolveVote($s);
                if (! $this->victory($room, $s)) {
                    $s['day']++;
                    $this->phase($room, $s, 'night');
                }
                break;
        }
    }

    /** @param array<string, mixed> $s */
    private function resolveNight(array &$s): void
    {
        $veiled = [];
        $investigated = [];
        $cult = array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'cult');
        foreach ($s['actions'] as $id => $a) {
            if ($s['players'][$id]['role'] === 'veilweaver' && $a['target'] !== null) {
                $veiled[] = $a['target'];
            }
        }
        foreach ($s['actions'] as $id => $a) {
            if ($s['players'][$id]['role'] === 'oracle') {
                $target = $a['target'];
                $investigated[] = $target;
                $alignment = $s['players'][$target]['alignment'];
                if (in_array($target, $veiled)) {
                    $alignment = $alignment === 'cult' ? 'town' : 'cult';
                }
                $s['players'][$id]['results'][] = ['day' => $s['day'], 'target' => $s['players'][$target]['name'], 'alignment' => $alignment];
            }
        }
        $allChanted = count(array_intersect(array_keys($cult), array_keys($s['actions']))) === count($cult);
        $gained = 0;
        foreach ($cult as $id => $player) {
            $eligible = isset($s['actions'][$id]) && match ($s['mission']['id']) {
                'concord' => $allChanted,
                'shadows' => ! in_array($id, $investigated),
                'patience' => ! $s['cult_banished'],
                default => false,
            };
            $key = $s['day'].':'.$id;
            if ($eligible && ! isset($s['awards'][$key])) {
                $s['awards'][$key] = true;
                $s['tokens']++;
                $gained++;
            }
        }
        $s['log'][] = 'Dawn '.$s['day'].': the ritual advanced by '.$gained.' step'.($gained === 1 ? '' : 's').'.';
    }

    /** @param array<string, mixed> $s */
    private function resolveVote(array &$s): void
    {
        $votes = [];
        foreach ($s['actions'] as $a) {
            $target = $a['target'] ?? 'abstain';
            $votes[$target] = ($votes[$target] ?? 0) + 1;
        }
        // Missing voters abstain. Abstention competes with candidates in the tally.
        $alive = count(array_filter($s['players'], fn (array $p): bool => $p['alive']));
        $votes['abstain'] = ($votes['abstain'] ?? 0) + $alive - count($s['actions']);
        arsort($votes);
        $top = array_keys($votes, max($votes), true);
        $s['cult_banished'] = false;
        if (count($top) === 1 && $top[0] !== 'abstain') {
            $id = $top[0];
            $s['players'][$id]['alive'] = false;
            $s['cult_banished'] = $s['players'][$id]['alignment'] === 'cult';
            $s['log'][] = $s['players'][$id]['name'].' was banished by the village. Their allegiance remains unknown.';
        } else {
            $s['log'][] = 'The vote ended without a banishment.';
        }
    }

    /** @param array<string, mixed> $s */
    private function victory(GameRoom $room, array &$s): bool
    {
        $cult = count(array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'cult'));
        $town = count(array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'town'));
        if ($cult === 0) {
            $s['winner'] = 'town';
            $s['win_reason'] = 'Every cultist has been banished. The village sees another sunrise.';
        } elseif ($s['tokens'] >= $s['threshold'] || $town === 0) {
            $s['winner'] = 'cult';
            $s['win_reason'] = $town === 0 ? 'No townspeople remain to stop the summoning.' : 'The ritual is complete. Cthulhu awakens.';
        }
        if ($s['winner'] !== null) {
            $this->phase($room, $s, 'finished');
            $s['log'][] = $s['win_reason'];

            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $s */
    private function phase(GameRoom $room, array &$s, string $phase): void
    {
        $s['phase'] = $phase;
        $s['phase_id']++;
        $s['actions'] = [];
        $duration = config('game.seconds.'.$phase);
        $room->deadline = $duration === null ? null : now()->addSeconds($duration);
    }

    /** @param array<string, mixed> $s */
    private function save(GameRoom $room, array &$s): void
    {
        $s['revision']++;
        $s['log'] = array_slice($s['log'], -100);
        $room->state = $s;
        $room->save();
        event(new RoomUpdated($room->id, $s['revision']));
    }

    /** Explicit allowlist: never serialize the authoritative state or model to a client.
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    private function view(GameRoom $room, array $s, string $id): array
    {
        $me = $s['players'][$id];
        $players = [];
        $allies = [];
        foreach ($s['players'] as $pid => $p) {
            $public = array_intersect_key($p, array_flip(['id', 'name', 'alive', 'ready']));
            $public['character'] = $this->character($p);
            $public['discussion_ready'] = $s['phase'] === 'discussion' && isset($s['actions'][$pid]) && $s['actions'][$pid]['type'] === 'discussion_ready';
            if ($s['phase'] === 'finished') {
                $public['role'] = $p['role'];
                $public['alignment'] = $p['alignment'];
            }
            $players[] = $public;
            if ($me['alignment'] === 'cult' && $p['alignment'] === 'cult' && $pid !== $id) {
                $allies[] = array_intersect_key($p, array_flip(['id', 'name', 'role']));
            }
        }

        return [
            'id' => $room->id, 'code' => $room->code, 'phase' => $s['phase'], 'phase_id' => $s['phase_id'],
            'revision' => $s['revision'], 'day' => $s['day'], 'deadline' => $room->deadline?->toISOString(),
            'server_time' => now()->toISOString(), 'host_id' => $s['host_id'],
            'ritual' => ['tokens' => $s['tokens'], 'threshold' => $s['threshold']],
            'winner' => $s['winner'], 'win_reason' => $s['win_reason'], 'players' => $players,
            'me' => array_replace(array_intersect_key($me, array_flip(['id', 'name', 'alive', 'role', 'alignment', 'results'])), [
                'character' => $this->character($me),
                'mission' => $me['alignment'] === 'cult' ? $s['mission'] : null,
                'allies' => $allies, 'submitted' => isset($s['actions'][$id]),
            ]),
            'messages' => $s['messages'], 'log' => $s['log'],
            'rules' => array_intersect_key($this->rules(), array_flip(['min_players', 'max_players', 'seconds'])),
        ];
    }

    /** Older rooms receive a stable cosmetic portrait without altering their roles.
     * @param  array<string, mixed>  $player
     */
    private function character(array $player): string
    {
        $characters = array_column(config('game.characters'), 'id');

        return $player['character'] ?? $characters[hexdec(substr(hash('sha256', $player['id']), 0, 6)) % count($characters)];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
