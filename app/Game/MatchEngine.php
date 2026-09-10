<?php

namespace App\Game;

use App\Events\RoomUpdated;
use App\Models\GameMatch;
use App\Models\GameRoom;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MatchEngine
{
    public function __construct(private CurseEngine $curses) {}

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = config('game');
        $rules['ritual_goals'] = array_map(fn (int $players): array => [
            'players' => $players, 'steps' => $this->ritualGoal($players),
        ], range($rules['min_players'], $rules['max_players']));

        return $rules;
    }

    private function ritualGoal(int $players): int
    {
        return config('game.ritual_goals_by_player_count.'.$players)
            ?? max(6, (int) ceil($players * config('game.tokens_per_player')));
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
        if ($p['alive'] && in_array($s['phase'], ['discussion', 'voting', 'night'], true)
            && in_array($p['curse']['type'] ?? null, ['puzzle', 'mist'], true)) {
            $this->ensure($type === 'solve_curse', 'Break your curse before returning to the village. It also fades at the next dawn.');
        }
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
                $player = array_replace($player, ['ready' => false, 'alive' => true, 'role' => null, 'alignment' => null, 'results' => [], 'curse' => null, 'curse_notice' => null]);
            }
            unset($player);
            $s = array_replace($s, ['day' => 0, 'tokens' => 0, 'threshold' => 0, 'winner' => null, 'win_reason' => null,
                'mission' => null, 'awards' => [], 'messages' => [], 'log' => ['The village gathers again.'], 'cult_banished' => false]);
            unset($s['match_id'], $s['match_rules'], $s['started_at'], $s['finished_at'], $s['rounds'], $s['missed_actions']);
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
            $s['threshold'] = $this->ritualGoal($count);
            $missions = config('game.missions');
            $s['mission'] = $count <= config('game.small_gathering_max_players')
                ? config('game.small_gathering_mission') : $missions[array_rand($missions)];
            $s['match_id'] = (string) Str::uuid();
            $s['match_rules'] = ['version' => config('game.rules_version'), 'player_count' => $count,
                'seconds' => config('game.seconds')];
            $s['started_at'] = now()->toISOString();
            $s['rounds'] = [];
            $s['missed_actions'] = ['night' => 0, 'vote' => 0];
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
        if ($type === 'solve_curse') {
            $curse = $p['curse'] ?? null;
            $this->ensure(in_array($s['phase'], ['discussion', 'voting', 'night'], true), 'There is no curse to break now.');
            $this->ensure($curse !== null && in_array($curse['type'], ['puzzle', 'mist'], true)
                && ($a['curse_id'] ?? null) === $curse['id'], 'This curse has already faded or changed.');
            $this->ensure(($a['answer'] ?? null) === $curse['solution'], 'The sigils resist. Check the clues and try again.');
            $s['players'][$id]['curse'] = null;

            return;
        }
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
            $this->ensure(in_array($p['role'], ['oracle', 'veilweaver', 'acolyte']) || $target === null, 'Your role does not target another player.');
        } elseif ($type === 'vote') {
            $this->ensure($s['phase'] === 'voting', 'Voting is not open.');
        } else {
            $this->ensure(false, 'Unknown action.');
        }
        $chosenTarget = $target;
        if ($target !== null && in_array($type, ['night', 'vote'], true)) {
            $curseType = $p['curse']['type'] ?? null;
            if ($curseType === 'misdirection') {
                $alternatives = array_keys(array_filter($s['players'], fn (array $player): bool => $player['alive'] && $player['id'] !== $id && $player['id'] !== $target));
                if ($alternatives !== []) {
                    $target = $alternatives[random_int(0, count($alternatives) - 1)];
                    $s['players'][$id]['curse'] = null;
                    $s['players'][$id]['curse_notice'] = 'The curse turned your choice toward '.$s['players'][$target]['name'].'. It is now spent.';
                }
            }
        }
        $s['actions'][$id] = ['type' => $type, 'target' => $target, 'chosen_target' => $chosenTarget];
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
                    if ($s['tokens'] >= $s['threshold']) {
                        $s['log'][] = 'The ritual is complete. The cult is preparing to summon. One final discussion and vote remain: banish every remaining cultist or the cult wins.';
                    }
                }
                break;
            case 'discussion':
                $this->phase($room, $s, 'voting');
                break;
            case 'voting':
                $this->resolveVote($s);
                if (! $this->victory($room, $s, afterVote: true)) {
                    $s['day']++;
                    $this->phase($room, $s, 'night');
                }
                break;
        }
    }

    /** @param array<string, mixed> $s */
    private function resolveNight(array &$s): void
    {
        // Previous dawn's curses survive this night's actions, then fade before new ones arrive.
        foreach ($s['players'] as &$player) {
            $player['curse'] = null;
        }
        unset($player);
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
        $contributors = [];
        foreach ($cult as $id => $player) {
            $eligible = isset($s['actions'][$id]) && match ($s['mission']['id']) {
                'solitary' => true,
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
                $contributors[] = $id;
            }
        }
        // One affliction per victim per dawn, even if multiple cultists chose them.
        $cursed = [];
        foreach ($s['actions'] as $id => $action) {
            $target = $action['target'] ?? null;
            if (isset($cult[$id]) && $target !== null && ! isset($cursed[$target])) {
                $s['players'][$target]['curse'] = $this->curses->create($s['tokens'], $s['threshold'], $s['day']);
                $cursed[$target] = true;
            }
        }
        $actions = [];
        foreach ($s['players'] as $id => $player) {
            if (! $player['alive']) {
                continue;
            }
            $submitted = isset($s['actions'][$id]);
            $target = $s['actions'][$id]['target'] ?? null;
            $reading = $player['role'] === 'oracle' && $submitted ? end($player['results']) : null;
            $actions[] = ['player_id' => $id, 'role' => $player['role'], 'target_id' => $target,
                'chosen_target_id' => $s['actions'][$id]['chosen_target'] ?? $target,
                'curse_type' => isset($cult[$id]) && $target !== null ? ($s['players'][$target]['curse']['type'] ?? null) : null,
                'submitted' => $submitted, 'contributed' => in_array($id, $contributors, true),
                'apparent_alignment' => $reading ? $reading['alignment'] : null,
                'veiled' => $reading && in_array($target, $veiled, true)];
            if (! $submitted) {
                $s['missed_actions']['night'] = ($s['missed_actions']['night'] ?? 0) + 1;
            }
        }
        $s['rounds'][$s['day']]['day'] = $s['day'];
        $s['rounds'][$s['day']]['night'] = ['actions' => $actions, 'gained' => $gained, 'tokens' => $s['tokens']];
        $s['log'][] = 'Dawn '.$s['day'].': the ritual advanced by '.$gained.' step'.($gained === 1 ? '' : 's').'.';
    }

    /** @param array<string, mixed> $s */
    private function resolveVote(array &$s): void
    {
        $votes = [];
        $ballots = [];
        foreach ($s['players'] as $id => $player) {
            if ($player['alive']) {
                $submitted = isset($s['actions'][$id]);
                $ballots[] = ['player_id' => $id, 'target_id' => $s['actions'][$id]['target'] ?? null,
                    'chosen_target_id' => $s['actions'][$id]['chosen_target'] ?? ($s['actions'][$id]['target'] ?? null), 'submitted' => $submitted];
                if (! $submitted) {
                    $s['missed_actions']['vote'] = ($s['missed_actions']['vote'] ?? 0) + 1;
                }
            }
        }
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
        $banished = null;
        if (count($top) === 1 && $top[0] !== 'abstain') {
            $id = $top[0];
            $banished = $id;
            $s['players'][$id]['alive'] = false;
            $s['players'][$id]['curse'] = null;
            $s['cult_banished'] = $s['players'][$id]['alignment'] === 'cult';
            $s['log'][] = $s['players'][$id]['name'].' was banished by the village. Their allegiance remains unknown.';
        } else {
            $s['log'][] = 'The vote ended without a banishment.';
        }
        $s['rounds'][$s['day']]['day'] = $s['day'];
        $s['rounds'][$s['day']]['vote'] = ['ballots' => $ballots, 'banished_id' => $banished];
    }

    /** @param array<string, mixed> $s */
    private function victory(GameRoom $room, array &$s, bool $afterVote = false): bool
    {
        $cult = count(array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'cult'));
        $town = count(array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'town'));
        // A full ritual grants one last vote. Banishment resolves before this check,
        // so removing the final cultist still takes precedence over the summoning.
        $summoned = $afterVote && $s['tokens'] >= $s['threshold'];
        if ($cult === 0) {
            $s['winner'] = 'town';
            $s['win_reason'] = 'Every cultist has been banished. The village sees another sunrise.';
        } elseif ($summoned || $town === 0 || ($cult === 1 && $town === 1)) {
            $s['winner'] = 'cult';
            $s['win_reason'] = match (true) {
                $town === 0 => 'No townspeople remain to stop the summoning.',
                $summoned => 'The final vote is over, and cultists remain. Cthulhu awakens.',
                default => 'One cultist and one town player remain. The last villager cannot banish the cult alone.',
            };
        }
        if ($s['winner'] !== null) {
            foreach ($s['players'] as &$player) {
                $player['curse'] = null;
                $player['curse_notice'] = null;
            }
            unset($player);
            $s['finished_at'] = now()->toISOString();
            $this->archive($room, $s);
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
        $duration = $s['match_rules']['seconds'][$phase] ?? config('game.seconds.'.$phase);
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
            'ritual' => ['tokens' => $s['tokens'], 'threshold' => $s['threshold'], 'level' => $this->curses->level($s['tokens'], $s['threshold']),
                'final_vote' => $s['threshold'] > 0 && $s['tokens'] >= $s['threshold'] && in_array($s['phase'], ['discussion', 'voting'], true)],
            'winner' => $s['winner'], 'win_reason' => $s['win_reason'], 'players' => $players,
            'me' => array_replace(array_intersect_key($me, array_flip(['id', 'name', 'alive', 'role', 'alignment', 'results'])), [
                'character' => $this->character($me),
                'curse' => $this->curses->view($me['curse'] ?? null),
                'curse_notice' => $me['curse_notice'] ?? null,
                'mission' => $me['alignment'] === 'cult' ? $s['mission'] : null,
                'allies' => $allies, 'submitted' => isset($s['actions'][$id]),
            ]),
            'messages' => ($me['curse']['type'] ?? null) === 'mist'
                ? array_map(fn (array $message): array => array_replace($message, ['body' => $this->curses->garble($message['body'], $me['curse']['id'].$message['id'])]), $s['messages'])
                : $s['messages'], 'log' => $s['log'],
            'recap' => $s['phase'] === 'finished' ? $this->recap($s) : null,
            'rules' => array_replace(array_intersect_key($this->rules(), array_flip(['min_players', 'max_players', 'seconds', 'ritual_goals', 'cultists_by_player_count', 'small_gathering_max_players'])),
                isset($s['match_rules']['seconds']) ? ['seconds' => $s['match_rules']['seconds']] : []),
        ];
    }

    /** @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function recap(array $s): array
    {
        return ['rules_version' => $s['match_rules']['version'] ?? 'legacy',
            'player_count' => $s['match_rules']['player_count'] ?? count($s['players']),
            'mission' => $s['mission'], 'ritual_goal' => $s['threshold'], 'nights' => $s['day'],
            'duration_seconds' => isset($s['started_at'], $s['finished_at'])
                ? max(0, (int) CarbonImmutable::parse($s['started_at'])->diffInSeconds(CarbonImmutable::parse($s['finished_at']))) : null,
            'complete' => isset($s['match_id']),
            'missed_actions' => array_replace(['night' => 0, 'vote' => 0], $s['missed_actions'] ?? []),
            'rounds' => array_values($s['rounds'] ?? [])];
    }

    /** Archive in the same locked transaction as victory, before rematch can clear secrets.
     * @param  array<string, mixed>  $s
     */
    private function archive(GameRoom $room, array $s): void
    {
        $recap = $this->recap($s);
        GameMatch::firstOrCreate(['id' => $s['match_id'] ?? (string) Str::uuid()], [
            'game_room_id' => $room->id, 'rules_version' => $recap['rules_version'],
            'player_count' => $recap['player_count'], 'mission' => $s['mission']['id'],
            'winner' => $s['winner'], 'win_reason' => $s['win_reason'], 'nights' => $s['day'],
            'ritual_goal' => $s['threshold'], 'ritual_steps' => $s['tokens'],
            'missed_night_actions' => $recap['missed_actions']['night'], 'missed_votes' => $recap['missed_actions']['vote'],
            'duration_seconds' => $recap['duration_seconds'], 'recap_complete' => $recap['complete'],
            'recap' => ['players' => array_values(array_map(fn (array $p): array => array_intersect_key($p, array_flip(['id', 'name', 'role', 'alignment', 'character'])), $s['players'])), ...$recap],
            'started_at' => $s['started_at'] ?? null, 'finished_at' => $s['finished_at'],
        ]);
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
