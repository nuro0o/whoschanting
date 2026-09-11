<?php

namespace App\Game;

use App\Events\RoomUpdated;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MatchEngine
{
    public function __construct(private CurseEngine $curses, private GameModes $modes = new GameModes, private AccountProgression $progression = new AccountProgression) {}

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

    /** @param array<string, mixed> $setup */
    public function create(string $identity, string $name, ?string $character = null, array $setup = [], ?int $accountId = null): GameRoom
    {
        $setup = $this->modes->normalize($setup);

        return DB::transaction(function () use ($identity, $name, $character, $setup, $accountId): GameRoom {
            $id = (string) Str::uuid();
            do {
                $code = strtoupper(Str::random(6));
            } while (GameRoom::where('code', $code)->exists());

            return GameRoom::create(['code' => $code, 'state' => [
                'phase' => 'lobby', 'phase_id' => 1, 'revision' => 1, 'day' => 0,
                'mode_setup' => $setup, 'roster' => $setup['classic_variant'],
                'host_id' => $id, 'players' => [$id => $this->seat($id, $identity, $name, $character, $accountId)],
                'tokens' => 0, 'threshold' => 0, 'mission' => null, 'winner' => null,
                'win_reason' => null, 'actions' => [], 'awards' => [], 'messages' => [],
                'log' => ['A new gathering takes shape.'], 'cult_banished' => false,
            ]]);
        });
    }

    /** @return array<string, mixed> */
    private function seat(string $id, string $identity, string $name, ?string $character = null, ?int $accountId = null): array
    {
        $characters = array_column(config('game.characters'), 'id');
        $this->ensure($character === null || in_array($character, $characters, true), 'Choose a character from the village.');
        $this->ensure($accountId === null || User::whereKey($accountId)->whereNotNull('email_verified_at')->exists(), 'Verify your account before joining.');

        return ['id' => $id, 'identity' => hash('sha256', $identity), 'name' => $name,
            'user_id' => $accountId, 'customization' => $accountId === null ? null : $this->progression->appearance($accountId),
            'alive' => true, 'ready' => false, 'role' => null, 'alignment' => null, 'results' => [],
            'character' => $character ?? $characters[random_int(0, count($characters) - 1)]];
    }

    public function join(string $code, string $identity, string $name, ?string $character = null, ?int $accountId = null): GameRoom
    {
        return DB::transaction(function () use ($code, $identity, $name, $character, $accountId): GameRoom {
            $room = GameRoom::where('code', $code)->lockForUpdate()->firstOrFail();
            $s = $room->state;
            $existing = $this->playerId($s, $identity, $accountId);
            if ($existing !== null) {
                if ($accountId !== null && $s['phase'] === 'lobby') {
                    $this->ensure(User::whereKey($accountId)->whereNotNull('email_verified_at')->exists(), 'Verify your account before joining.');
                    $this->ensure($character === null || in_array($character, array_column(config('game.characters'), 'id'), true), 'Choose a character from the village.');
                    $s['players'][$existing]['user_id'] = $accountId;
                    $s['players'][$existing]['customization'] = $this->progression->appearance($accountId);
                    if ($character !== null) {
                        $s['players'][$existing]['character'] = $character;
                    }
                    $this->save($room, $s);
                }

                return $room;
            }
            $this->ensure($s['phase'] === 'lobby', 'This match has already begun.');
            $setup = $this->modes->setup($s);
            $capacity = $setup['mode'] === 'custom' ? array_sum($setup['roles']) : config('game.max_players');
            $this->ensure(count($s['players']) < $capacity, 'This room is full for its selected role setup.');
            foreach ($s['players'] as $player) {
                $this->ensure(mb_strtolower($player['name']) !== mb_strtolower($name), 'That name is already at the table.');
            }
            $id = (string) Str::uuid();
            $s['players'][$id] = $this->seat($id, $identity, $name, $character, $accountId);
            $this->save($room, $s);

            return $room;
        });
    }

    /** @param array<string, mixed> $s */
    public function playerId(array $s, string $identity, ?int $accountId = null): ?string
    {
        if ($accountId !== null) {
            foreach ($s['players'] as $id => $player) {
                if (($player['user_id'] ?? null) === $accountId) {
                    return $id;
                }
            }
        }
        foreach ($s['players'] as $id => $player) {
            if (hash_equals($player['identity'], hash('sha256', $identity))) {
                return isset($player['user_id']) && $player['user_id'] !== $accountId ? null : $id;
            }
        }

        return null;
    }

    // Every reader and writer serializes on the same row. No client can resolve a phase.
    /** @param array<string, mixed>|null $action
     * @return array<string, mixed>
     */
    public function access(string $code, string $identity, ?array $action = null, ?int $accountId = null): array
    {
        $result = DB::transaction(function () use ($code, $identity, $action, $accountId): array {
            $room = GameRoom::where('code', $code)->lockForUpdate()->firstOrFail();
            $s = $room->state;
            $id = $this->playerId($s, $identity, $accountId);
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
        if (in_array($type, ['roster', 'configure_mode'], true)) {
            $this->ensure($s['phase'] === 'lobby' && $s['host_id'] === $id, 'Only the host can choose the roster in the lobby.');
            if ($type === 'roster') {
                $this->ensure(in_array($a['roster'] ?? null, ['classic', 'illusions'], true), 'Choose a valid role roster.');
                $setup = ['classic_variant' => $a['roster']];
            } else {
                $this->ensure(isset($a['setup']) && is_array($a['setup']), 'Choose mode settings.');
                $setup = $a['setup'];
            }
            $s['mode_setup'] = $this->modes->normalize($setup);
            $s['roster'] = $s['mode_setup']['classic_variant'];
            foreach ($s['players'] as &$player) {
                $player['ready'] = false;
            }
            unset($player);

            return;
        }
        $selectedCurse = $a['curse_type'] ?? null;
        if ($selectedCurse !== null) {
            $this->ensure($type === 'night' && in_array($p['role'], ['veilweaver', 'acolyte'], true) && ($a['target'] ?? null) !== null, 'Only a cultist cursing a target can choose a curse.');
            $this->ensure(in_array($selectedCurse, $this->curses->availableTypes($s['tokens'], $s['threshold']), true), 'Choose an unlocked curse. Misdirection unlocks at ritual level 3.');
        }
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
            $s['messages'][] = ['id' => (string) Str::uuid(), 'player_id' => $id, 'name' => $p['name'],
                'body' => $body, 'day' => $s['day'], 'sent_at' => now()->toISOString()];
            $s['messages'] = array_slice($s['messages'], -100);

            return;
        }
        if ($type === 'rematch') {
            $this->ensure($id === $s['host_id'] && $s['phase'] === 'finished', 'Only the host can start a new gathering after victory.');
            foreach ($s['players'] as &$player) {
                $player = array_replace($player, ['ready' => false, 'alive' => true, 'role' => null, 'alignment' => null, 'results' => [], 'curse' => null, 'curse_notice' => null]);
                unset($player['last_protection'], $player['ability_used'], $player['haunting'], $player['oath'], $player['oath_protection_day']);
            }
            unset($player);
            $s = array_replace($s, ['day' => 0, 'tokens' => 0, 'threshold' => 0, 'winner' => null, 'win_reason' => null,
                'mission' => null, 'awards' => [], 'messages' => [], 'log' => ['The village gathers again.'], 'cult_banished' => false]);
            unset($s['match_id'], $s['match_rules'], $s['started_at'], $s['finished_at'], $s['rounds'], $s['missed_actions'], $s['chaos_event'], $s['match_rewards']);
            $this->phase($room, $s, 'lobby');

            return;
        }
        if ($type === 'start') {
            $this->ensure($s['phase'] === 'lobby' && $s['host_id'] === $id, 'Only the host can begin from the lobby.');
            $count = count($s['players']);
            $this->ensure($count >= config('game.min_players') && $count <= config('game.max_players'), 'Gather between '.config('game.min_players').' and '.config('game.max_players').' players.');
            $this->ensure(count(array_filter($s['players'], fn (array $p): bool => ! $p['ready'])) === 0, 'Every player must be ready.');
            $ids = array_keys($s['players']);
            shuffle($ids);
            $setup = $this->modes->setup($s);
            $roster = $this->modes->roster($setup, $count);
            foreach ($ids as $index => $pid) {
                if (isset($s['players'][$pid]['user_id'])) {
                    $s['players'][$pid]['customization'] = $this->progression->appearance($s['players'][$pid]['user_id']);
                }
                $s['players'][$pid]['role'] = $roster[$index];
                $s['players'][$pid]['alignment'] = config('game.role_alignments')[$roster[$index]];
            }
            $s['threshold'] = $this->ritualGoal($count);
            $missions = config('game.missions');
            $s['mission'] = $count <= config('game.small_gathering_max_players')
                ? config('game.small_gathering_mission') : $missions[array_rand($missions)];
            $s['match_id'] = (string) Str::uuid();
            $s['match_rules'] = ['version' => config('game.rules_version'), 'player_count' => $count,
                'seconds' => config('game.seconds'), 'roster' => $s['roster'] ?? 'classic', 'mode_setup' => $setup];
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
            $this->ensure($curse !== null && in_array($curse['type'], ['puzzle', 'mist', 'misdirection'], true)
                && ($curse['challenge'] ?? null) !== null && ($curse['solution'] ?? []) !== []
                && ($a['curse_id'] ?? null) === $curse['id'], 'This curse has already faded or changed.');
            $this->ensure(($a['answer'] ?? null) === $curse['solution'], 'The sigils resist. Check the clues and try again.');
            $s['players'][$id]['curse'] = $this->curses->advance($curse);

            return;
        }
        if (in_array($type, ['exorcise', 'oath'], true)) {
            $this->ensure($s['phase'] === 'discussion', 'Use this ability during discussion.');
            $target = $a['target'] ?? null;
            $this->ensure($target !== $id && isset($s['players'][$target]) && $s['players'][$target]['alive'], 'Choose another living player.');
            if ($type === 'exorcise') {
                $this->ensure($p['role'] === 'exorcist' && ! ($p['ability_used'] ?? false), 'Your exorcism is unavailable.');
                $s['players'][$id]['ability_used'] = true;
                $s['players'][$target]['curse'] = null;
                $s['players'][$target]['haunting'] = null;
                $s['players'][$id]['results'][] = ['kind' => 'exorcism', 'day' => $s['day'], 'target' => $s['players'][$target]['name']];
            } else {
                $this->ensure($p['role'] === 'oathkeeper' && ($p['oath']['day'] ?? null) !== $s['day'], 'You may make one oath per discussion.');
                $s['players'][$id]['oath'] = ['day' => $s['day'], 'target_id' => $target];
                $s['log'][] = $p['name'].' publicly swore to vote for '.$s['players'][$target]['name'].'.';
            }
            $s['rounds'][$s['day']]['discussion'][] = ['type' => $type, 'player_id' => $id, 'target_id' => $target];

            return;
        }
        $this->ensure(! isset($s['actions'][$id]), 'Your action is already sealed for this phase.');
        $target = $a['target'] ?? null;
        // Older clients submit Oracle investigations with just a target.
        $useAbility = (bool) ($a['use_ability'] ?? false) || ($type === 'night' && $p['role'] === 'oracle' && $target !== null);
        $this->ensure(! $useAbility || ($type === 'night' && in_array($p['role'], ['oracle', 'medium', 'dreamweaver', 'bellkeeper', 'phantasm', 'counterfeiter', 'herbalist'], true)), 'This action cannot use a once-per-match ability.');
        $this->ensure(! $useAbility || ! $this->abilityUsed($p), 'Your once-per-match ability has already been used.');
        $forgedAlignment = $a['forged_alignment'] ?? null;
        $this->ensure($forgedAlignment === null || ($type === 'night' && $p['role'] === 'counterfeiter' && $useAbility), 'Only a Counterfeiter using their ability can forge a reading.');
        $this->ensure(! ($p['role'] === 'counterfeiter' && $useAbility) || in_array($forgedAlignment, ['town', 'cult'], true), 'Choose the forged alignment.');
        $deadTarget = $type === 'night' && $p['role'] === 'medium' && $useAbility;
        if ($target !== null) {
            $this->ensure(isset($s['players'][$target]) && $s['players'][$target]['alive'] !== $deadTarget && $target !== $id, $deadTarget ? 'Choose a banished player.' : 'Choose another living player.');
        }
        if ($type === 'discussion_ready') {
            $this->ensure($s['phase'] === 'discussion', 'You can be ready for voting only during discussion.');
            $this->ensure($target === null, 'Readiness does not target another player.');
        } elseif ($type === 'night') {
            $this->ensure($s['phase'] === 'night', 'Night has ended.');
            $this->ensure($p['role'] !== 'lamplighter' || $target !== null, 'Choose someone to watch.');
            $this->ensure($p['role'] !== 'tracker' || $target !== null, 'Choose someone to track.');
            $this->ensure(! $useAbility || in_array($p['role'], ['bellkeeper', 'herbalist'], true) || $target !== null, 'Choose a target for your ability.');
            $canTarget = in_array($p['role'], ['oracle', 'veilweaver', 'acolyte', 'warden', 'lamplighter', 'tracker']) || ($useAbility && in_array($p['role'], ['medium', 'dreamweaver', 'phantasm', 'counterfeiter']));
            $this->ensure($canTarget || $target === null, 'Your chosen action does not target another player.');
            $this->ensure($target === null || $target !== $this->previousProtection($p, $s['day']), 'You cannot protect the same player on consecutive nights.');
        } elseif ($type === 'vote') {
            $this->ensure($s['phase'] === 'voting', 'Voting is not open.');
        } else {
            $this->ensure(false, 'Unknown action.');
        }
        $chosenTarget = $target;
        if ($target !== null && in_array($type, ['night', 'vote'], true)) {
            $curseType = $p['curse']['type'] ?? null;
            if ($curseType === 'misdirection') {
                $excludedProtection = $type === 'night' ? $this->previousProtection($p, $s['day']) : null;
                $alternatives = array_keys(array_filter($s['players'], fn (array $player): bool => $player['alive'] !== $deadTarget && $player['id'] !== $id && $player['id'] !== $target && $player['id'] !== $excludedProtection));
                if ($alternatives !== []) {
                    $target = $alternatives[random_int(0, count($alternatives) - 1)];
                    $s['players'][$id]['curse'] = null;
                    $s['players'][$id]['curse_notice'] = 'The curse turned your choice toward '.$s['players'][$target]['name'].'. It is now spent.';
                }
            }
        }
        if ($useAbility) {
            // Committing the action spends the ability even if it is disrupted later.
            $s['players'][$id]['ability_used'] = true;
        }
        $s['actions'][$id] = ['type' => $type, 'target' => $target, 'chosen_target' => $chosenTarget, 'use_ability' => $useAbility,
            'forged_alignment' => $forgedAlignment,
            'curse_type' => $type === 'night' && in_array($p['role'], ['veilweaver', 'acolyte'], true) && $target !== null ? ($selectedCurse ?? 'puzzle') : null];
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
        // Disruptions resolve first, independent of submission order.
        $disruptions = [];
        $disrupted = [];
        foreach ($s['actions'] as $id => $action) {
            if ($s['players'][$id]['role'] === 'dreamweaver' && ($action['use_ability'] ?? false)) {
                $disruptions[$id] = $action['target'];
                $disrupted[$action['target']] = true;
                $s['players'][$id]['results'][] = ['kind' => 'disruption', 'day' => $s['day'], 'target' => $s['players'][$action['target']]['name']];
            }
        }
        // Simultaneous disruptions cannot undo one another after resolving.
        $disrupted = array_diff_key($disrupted, $disruptions);
        foreach ($disrupted as $id => $_) {
            if (isset($s['actions'][$id])) {
                $s['players'][$id]['results'][] = ['kind' => 'disrupted', 'day' => $s['day'], 'target' => $s['players'][$id]['name']];
            }
        }
        $effective = array_diff_key($s['actions'], $disrupted);
        if (in_array($this->modes->setup($s)['mode'], ['custom', 'chaos'], true)) {
            // Stable seat order resolves overlapping duplicate abilities, rather than arrival order.
            $effective = [];
            foreach ($s['players'] as $pid => $_) {
                if (isset($s['actions'][$pid]) && ! isset($disrupted[$pid])) {
                    $effective[$pid] = $s['actions'][$pid];
                }
            }
        }
        $veiled = [];
        $protected = [];
        $hiddenVisitors = [];
        $forgeries = [];
        foreach ($s['players'] as $pid => $player) {
            if (($player['oath_protection_day'] ?? null) === $s['day'] || ($s['chaos_event'] ?? null) === 'sanctuary') {
                $protected[$pid] = true;
            }
            if (($s['chaos_event'] ?? null) === 'eclipse') {
                $hiddenVisitors[$pid] = true;
            }
        }
        $investigated = [];
        $cult = array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['alignment'] === 'cult');
        foreach ($effective as $id => $a) {
            if ($s['players'][$id]['role'] === 'herbalist' && ($a['use_ability'] ?? false)) {
                $protected = array_fill_keys(array_keys($s['players']), true);
                $s['players'][$id]['results'][] = ['kind' => 'herbs', 'day' => $s['day'], 'target' => 'The village'];
            }
            if (($a['use_ability'] ?? false) && $s['players'][$id]['role'] === 'phantasm') {
                $hiddenVisitors[$a['target']] = true;
                // The decoy is cosmetic, selected without consulting anyone's allegiance.
                $decoys = array_keys(array_filter($s['players'], fn (array $p): bool => $p['alive'] && $p['id'] !== $a['target']));
                $s['players'][$a['target']]['haunting'] = ['day' => $s['day'], 'seat_id' => $decoys[array_rand($decoys)]];
                $s['players'][$id]['results'][] = ['kind' => 'haunting', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name']];
            }
            if (($a['use_ability'] ?? false) && $s['players'][$id]['role'] === 'counterfeiter') {
                $forgeries[$a['target']] = $a['forged_alignment'];
                $s['players'][$id]['results'][] = ['kind' => 'forgery', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name'], 'alignment' => $a['forged_alignment']];
            }
            if ($s['players'][$id]['role'] === 'veilweaver' && $a['target'] !== null) {
                $veiled[] = $a['target'];
            }
            if ($s['players'][$id]['role'] === 'warden' && $a['target'] !== null) {
                $protected[$a['target']] = true;
                $s['players'][$id]['last_protection'] = ['day' => $s['day'], 'target' => $a['target']];
                $s['players'][$id]['results'][] = ['kind' => 'protection', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name']];
            }
        }
        foreach ($effective as $id => $a) {
            if ($s['players'][$id]['role'] === 'oracle' && $a['target'] !== null) {
                $target = $a['target'];
                $investigated[] = $target;
                $alignment = $s['players'][$target]['alignment'];
                if (($s['chaos_event'] ?? null) === 'mirrors') {
                    $alignment = $alignment === 'cult' ? 'town' : 'cult';
                }
                if (in_array($target, $veiled)) {
                    $alignment = $alignment === 'cult' ? 'town' : 'cult';
                }
                $alignment = $forgeries[$target] ?? $alignment;
                $s['players'][$id]['results'][] = ['day' => $s['day'], 'target' => $s['players'][$target]['name'], 'alignment' => $alignment];
            }
            if ($s['players'][$id]['role'] === 'lamplighter') {
                // Count other submitted visits, including curses that protection blocks.
                // Observing a player never counts as the Lamplighter's own evidence.
                $visited = false;
                foreach ($s['actions'] as $visitor => $visit) {
                    if ($visitor !== $id && ! isset($hiddenVisitors[$visitor]) && ($visit['target'] ?? null) === $a['target']) {
                        $visited = true;
                        break;
                    }
                }
                $s['players'][$id]['results'][] = ['kind' => 'visits', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name'], 'visited' => $visited];
            }
            if ($s['players'][$id]['role'] === 'medium' && ($a['use_ability'] ?? false)) {
                $s['players'][$id]['results'][] = ['kind' => 'spirit', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name'], 'alignment' => $s['players'][$a['target']]['alignment']];
            }
            if ($s['players'][$id]['role'] === 'tracker') {
                $visitedTarget = isset($hiddenVisitors[$a['target']]) ? null : ($s['actions'][$a['target']]['target'] ?? null);
                $s['players'][$id]['results'][] = ['kind' => 'tracking', 'day' => $s['day'], 'target' => $s['players'][$a['target']]['name'],
                    'visited_target' => $visitedTarget === null ? null : $s['players'][$visitedTarget]['name']];
            }
        }
        $chants = array_filter(array_intersect_key($effective, $cult), fn (array $action): bool => ! ($action['use_ability'] ?? false));
        $allChanted = count($chants) === count($cult);
        $bells = [];
        foreach ($effective as $id => $action) {
            if ($s['players'][$id]['role'] === 'bellkeeper' && ($action['use_ability'] ?? false)) {
                $bells[$id] = 0;
            }
        }
        $unusedBells = array_keys($bells);
        $ritualBlocked = [];
        $gained = 0;
        $contributors = [];
        foreach ($cult as $id => $player) {
            $eligible = isset($chants[$id]) && match ($s['mission']['id']) {
                'solitary' => true,
                'concord' => $allChanted,
                'shadows' => ! in_array($id, $investigated),
                'patience' => ! $s['cult_banished'],
                default => false,
            };
            $key = $s['day'].':'.$id;
            if ($eligible && ! isset($s['awards'][$key])) {
                $s['awards'][$key] = true;
                if ($unusedBells !== []) {
                    $bell = array_shift($unusedBells);
                    $bells[$bell] = 1;
                    $ritualBlocked[$id] = true;

                    continue;
                }
                $s['tokens']++;
                $gained++;
                $contributors[] = $id;
            }
        }
        foreach ($bells as $id => $prevented) {
            $s['players'][$id]['results'][] = ['kind' => 'bell', 'day' => $s['day'], 'target' => 'The ritual', 'prevented' => $prevented];
        }
        // One affliction per victim per dawn, even if multiple cultists chose them.
        $cursed = [];
        $blockedCurses = [];
        foreach ($effective as $id => $action) {
            $target = $action['target'] ?? null;
            if (in_array($s['players'][$id]['role'], ['veilweaver', 'acolyte'], true) && $target !== null && ! isset($cursed[$target])) {
                if (isset($protected[$target])) {
                    $blockedCurses[$target] = true;

                    continue;
                }
                $s['players'][$target]['curse'] = $this->curses->create($s['tokens'], $s['threshold'], $s['day'], $action['curse_type'] ?? 'puzzle');
                $cursed[$target] = $id;
            }
        }
        $actions = [];
        foreach ($s['players'] as $id => $player) {
            if (! $player['alive']) {
                continue;
            }
            $submitted = isset($s['actions'][$id]);
            $target = $s['actions'][$id]['target'] ?? null;
            $reading = $player['role'] === 'oracle' && isset($effective[$id]) && $target !== null ? end($player['results']) : null;
            $canCurse = in_array($player['role'], ['veilweaver', 'acolyte'], true) && isset($effective[$id]);
            $actions[] = ['player_id' => $id, 'role' => $player['role'], 'target_id' => $target,
                'chosen_target_id' => $s['actions'][$id]['chosen_target'] ?? $target,
                'curse_type' => $canCurse && $target !== null && ($cursed[$target] ?? null) === $id ? ($s['players'][$target]['curse']['type'] ?? null) : null,
                'curse_blocked' => $canCurse && $target !== null && isset($blockedCurses[$target]),
                'prevented_curse' => $player['role'] === 'warden' && isset($effective[$id]) && $target !== null && isset($blockedCurses[$target]),
                'visited' => $player['role'] === 'lamplighter' && isset($effective[$id]) ? end($player['results'])['visited'] : null,
                'used_ability' => $s['actions'][$id]['use_ability'] ?? false,
                'forged_alignment' => $s['actions'][$id]['forged_alignment'] ?? null,
                'forged' => $reading && isset($forgeries[$target]),
                'visits_hidden' => isset($hiddenVisitors[$id]) && ($s['chaos_event'] ?? null) !== 'eclipse',
                'tracked_target_id' => $player['role'] === 'tracker' && isset($effective[$id]) && ! isset($hiddenVisitors[$target]) ? ($s['actions'][$target]['target'] ?? null) : null,
                'disrupted' => $submitted && isset($disrupted[$id]),
                'ritual_blocked' => isset($ritualBlocked[$id]),
                'prevented_steps' => $bells[$id] ?? null,
                'true_alignment' => $player['role'] === 'medium' && isset($effective[$id]) && ($effective[$id]['use_ability'] ?? false) ? $s['players'][$target]['alignment'] : null,
                'submitted' => $submitted, 'contributed' => in_array($id, $contributors, true),
                'apparent_alignment' => $reading ? $reading['alignment'] : null,
                'veiled' => $reading && in_array($target, $veiled, true)];
            if (! $submitted) {
                $s['missed_actions']['night'] = ($s['missed_actions']['night'] ?? 0) + 1;
            }
        }
        $s['rounds'][$s['day']]['day'] = $s['day'];
        $s['rounds'][$s['day']]['night'] = ['actions' => $actions, 'gained' => $gained, 'tokens' => $s['tokens'], 'chaos_event' => $s['chaos_event'] ?? null];
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
                $oathKept = ($player['oath']['day'] ?? null) === $s['day']
                    ? $submitted && ($s['actions'][$id]['target'] ?? null) === $player['oath']['target_id'] : null;
                if ($oathKept !== null) {
                    $s['players'][$id]['oath_protection_day'] = $oathKept ? $s['day'] + 1 : null;
                    $s['players'][$id]['results'][] = ['kind' => 'oath', 'day' => $s['day'], 'target' => $s['players'][$player['oath']['target_id']]['name'], 'kept' => $oathKept];
                }
                $ballots[] = ['player_id' => $id, 'target_id' => $s['actions'][$id]['target'] ?? null,
                    'oath_kept' => $oathKept,
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
        if ($phase === 'night') {
            $setup = $s['match_rules']['mode_setup'] ?? $this->modes->setup($s);
            $s['chaos_event'] = null;
            if ($setup['mode'] === 'chaos' && $setup['chaos_variant'] === 'maelstrom') {
                $events = ['mirrors' => 'Hall of mirrors: base Oracle readings are reversed tonight. Veils apply afterwards; a forgery has the final say.',
                    'sanctuary' => 'Sanctuary: all new curses are blocked tonight. Other abilities still work.',
                    'eclipse' => 'Eclipse: no visits can be seen by Lamplighters or Trackers tonight.'];
                $s['chaos_event'] = array_rand($events);
                $s['log'][] = 'Maelstrom — '.$events[$s['chaos_event']];
            }
        }
        if (in_array($phase, ['voting', 'finished'], true)) {
            foreach ($s['players'] as &$player) {
                $player['haunting'] = null;
            }
            unset($player);
        }
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
        $setup = $this->modes->setup($s);
        $preview = $this->modes->preview($setup, count($s['players']));
        if ($s['phase'] !== 'lobby') {
            $preview['roles'] = array_count_values(array_filter(array_column($s['players'], 'role'), 'is_string'));
            $preview['error'] = null;
        }
        $players = [];
        $allies = [];
        foreach ($s['players'] as $pid => $p) {
            $public = array_intersect_key($p, array_flip(['id', 'name', 'alive', 'ready']));
            $public['character'] = $this->character($p);
            if (isset($p['customization'])) {
                $public['customization'] = $p['customization'];
            }
            $public['oath'] = ($p['oath']['day'] ?? null) === $s['day'] ? $p['oath'] : null;
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
            'roster' => $s['roster'] ?? 'classic',
            'mode_setup' => $setup, 'mode_preview' => $preview, 'chaos_event' => $s['chaos_event'] ?? null,
            'server_time' => now()->toISOString(), 'host_id' => $s['host_id'],
            'ritual' => ['tokens' => $s['tokens'], 'threshold' => $s['threshold'], 'level' => $this->curses->level($s['tokens'], $s['threshold']),
                'final_vote' => $s['threshold'] > 0 && $s['tokens'] >= $s['threshold'] && in_array($s['phase'], ['discussion', 'voting'], true)],
            'winner' => $s['winner'], 'win_reason' => $s['win_reason'], 'players' => $players,
            'me' => array_replace(array_intersect_key($me, array_flip(['id', 'name', 'alive', 'role', 'alignment', 'results'])), [
                'character' => $this->character($me),
                'customization' => $me['customization'] ?? null,
                'account_progression' => isset($me['user_id']),
                'match_reward' => $s['phase'] === 'finished' ? ($s['match_rewards'][$id] ?? null) : null,
                'curse' => $this->curses->view($me['curse'] ?? null),
                'curse_notice' => $me['curse_notice'] ?? null,
                'previous_protection_target' => $this->previousProtection($me, $s['day']),
                'ability_used' => $this->abilityUsed($me),
                'haunting' => $s['phase'] === 'discussion' ? ($me['haunting'] ?? null) : null,
                'oath_protected' => $s['phase'] === 'night' && ($me['oath_protection_day'] ?? null) === $s['day'],
                'mission' => $me['alignment'] === 'cult' ? $s['mission'] : null,
                'allies' => $allies, 'submitted' => isset($s['actions'][$id]),
            ]),
            'messages' => ($me['curse']['type'] ?? null) === 'mist'
                ? array_map(fn (array $message): array => array_replace($message, ['body' => $this->curses->garble($message['body'], $me['curse']['id'].$message['id'])]), $s['messages'])
                : $s['messages'], 'log' => $s['log'],
            'recap' => $s['phase'] === 'finished' ? $this->recap($s) : null,
            'rules' => array_replace(array_intersect_key($this->rules(), array_flip(['min_players', 'max_players', 'seconds', 'ritual_goals', 'cultists_by_player_count', 'small_gathering_max_players', 'town_roles_min_players', 'cult_roles_min_players'])),
                ($s['roster'] ?? 'classic') === 'illusions' ? ['town_roles_min_players' => config('game.illusion_town_roles_min_players'), 'cult_roles_min_players' => config('game.illusion_cult_roles_min_players')] : [],
                $setup['mode'] === 'hard' ? ['town_roles_min_players' => config('game.hard_town_roles_min_players'), 'cult_roles_min_players' => config('game.hard_cult_roles_min_players')] : [],
                in_array($setup['mode'], ['custom', 'chaos'], true) ? ['town_roles_min_players' => [], 'cult_roles_min_players' => []] : [],
                isset($s['match_rules']['seconds']) ? ['seconds' => $s['match_rules']['seconds']] : []),
        ];
    }

    /** @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function recap(array $s): array
    {
        return ['rules_version' => $s['match_rules']['version'] ?? 'legacy',
            'mode_setup' => $s['match_rules']['mode_setup'] ?? $this->modes->setup($s),
            'roster' => $s['match_rules']['roster'] ?? 'classic',
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
    private function archive(GameRoom $room, array &$s): void
    {
        $recap = $this->recap($s);
        $match = GameMatch::firstOrCreate(['id' => $s['match_id'] ?? (string) Str::uuid()], [
            'game_room_id' => $room->id, 'rules_version' => $recap['rules_version'],
            'player_count' => $recap['player_count'], 'mission' => $s['mission']['id'],
            'winner' => $s['winner'], 'win_reason' => $s['win_reason'], 'nights' => $s['day'],
            'ritual_goal' => $s['threshold'], 'ritual_steps' => $s['tokens'],
            'missed_night_actions' => $recap['missed_actions']['night'], 'missed_votes' => $recap['missed_actions']['vote'],
            'duration_seconds' => $recap['duration_seconds'], 'recap_complete' => $recap['complete'],
            'recap' => ['players' => array_values(array_map(fn (array $p): array => array_intersect_key($p, array_flip(['id', 'name', 'role', 'alignment', 'character'])), $s['players'])), ...$recap],
            'started_at' => $s['started_at'] ?? null, 'finished_at' => $s['finished_at'],
        ]);
        $s['match_rewards'] = $this->progression->award($match, $s);
    }

    /** Older rooms receive a stable cosmetic portrait without altering their roles.
     * @param  array<string, mixed>  $player
     */
    private function character(array $player): string
    {
        $characters = array_column(config('game.characters'), 'id');

        return $player['character'] ?? $characters[hexdec(substr(hash('sha256', $player['id']), 0, 6)) % count($characters)];
    }

    /** @param array<string, mixed> $player */
    private function previousProtection(array $player, int $day): ?string
    {
        return $player['role'] === 'warden' && ($player['last_protection']['day'] ?? null) === $day - 1
            ? $player['last_protection']['target'] : null;
    }

    /** Existing Oracle readings also consume the charge in rooms created before this rule.
     * @param  array<string, mixed>  $player
     */
    private function abilityUsed(array $player): bool
    {
        return ($player['ability_used'] ?? false)
            || (($player['role'] ?? null) === 'oracle' && count(array_filter($player['results'] ?? [],
                fn (array $result): bool => ! isset($result['kind']) && isset($result['alignment']))) > 0);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
