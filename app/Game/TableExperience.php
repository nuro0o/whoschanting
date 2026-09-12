<?php

namespace App\Game;

use App\Models\GameMatch;
use App\Models\GameRoom;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Social actions share the match's row lock, but never consume a role action. */
class TableExperience
{
    private const PROMPTS = [
        ['id' => 'detail', 'question' => 'Whose story needs one more detail, and what would you ask?'],
        ['id' => 'change', 'question' => 'Which claim changed your mind, and why?'],
        ['id' => 'convince', 'question' => 'What would convince you to change your vote?'],
        ['id' => 'compare', 'question' => 'Which two stories should the village compare before voting?'],
    ];

    /** @param array<string, mixed> $s
     * @param  array<string, mixed>  $a
     */
    public function act(GameRoom $room, array &$s, string $id, array $a): bool
    {
        $type = $a['type'];
        $p = $s['players'][$id];
        if ($type === 'accuse') {
            $this->ensure($p['alive'] && $s['phase'] === 'discussion' && isset($s['match_rules']['seconds']['last_words']), 'Living players can accuse someone during discussion in a new match.');
            $target = $a['target'] ?? null;
            $this->ensure(is_string($target) && $target !== $id && ($s['players'][$target]['alive'] ?? false), 'Choose another living player.');
            $record = $s['rounds'][$s['day']]['last_words'] ?? ['accusations' => [], 'accused_ids' => [], 'defenses' => []];
            foreach ($record['accusations'] as $accusation) {
                $this->ensure($accusation['player_id'] !== $id, 'Your accusation for this discussion is already sealed.');
            }
            $record['accusations'][] = ['player_id' => $id, 'target_id' => $target];
            $s['rounds'][$s['day']]['day'] = $s['day'];
            $s['rounds'][$s['day']]['last_words'] = $record;

            return true;
        }
        if ($type === 'defend') {
            $record = $s['rounds'][$s['day']]['last_words'] ?? [];
            $this->ensure($p['alive'] && $s['phase'] === 'last_words' && in_array($id, $record['accused_ids'] ?? [], true), 'Only the accused can give Last Words now.');
            foreach ($record['defenses'] as $defense) {
                $this->ensure($defense['player_id'] !== $id, 'Your Last Words are already sealed.');
            }
            $body = trim($a['body'] ?? '');
            $this->ensure($body !== '' && mb_strlen($body) <= 280, 'Write a defense of 1–280 characters.');
            $s['rounds'][$s['day']]['last_words']['defenses'][] = ['player_id' => $id, 'body' => $body];

            return true;
        }
        if (in_array($type, ['transfer_host', 'remove_player'], true)) {
            $this->ensure($s['host_id'] === $id, 'Only the host can manage seats.');
            $target = $a['target'] ?? null;
            $this->ensure(is_string($target) && $target !== $id && isset($s['players'][$target]), 'Choose another player at the table.');
            if ($type === 'transfer_host') {
                $s['host_id'] = $target;
                $s['log'][] = $s['players'][$target]['name'].' is now the host.';
            } else {
                $this->ensure($s['phase'] === 'lobby', 'Seats can only be removed in the lobby.');
                $name = $s['players'][$target]['name'];
                unset($s['players'][$target]);
                foreach ($s['players'] as &$player) {
                    $player['ready'] = false;
                }
                unset($player);
                $s['log'][] = $name.' was removed from the lobby. Please ready up again.';
            }

            return true;
        }
        if (in_array($type, ['claim', 'discussion_response'], true)) {
            $this->ensure($p['alive'] && $s['phase'] === 'discussion', 'Living players can share statements during discussion.');
            $key = $type === 'claim' ? 'claims' : 'responses';
            foreach ($s[$key] ?? [] as $entry) {
                $this->ensure($entry['player_id'] !== $id || $entry['day'] !== $s['day'], 'Your statement for this discussion is already sealed.');
            }
            $body = trim($a['body'] ?? '');
            $this->ensure($body !== '' && mb_strlen($body) <= 280, 'Write a statement of 1–280 characters.');
            $target = $a['target'] ?? null;
            $this->ensure($target === null || (is_string($target) && isset($s['players'][$target])), 'Choose a player at this table.');
            $entry = ['id' => (string) Str::uuid(), 'player_id' => $id, 'day' => $s['day'], 'body' => $body, 'target_id' => $target];
            if ($type === 'claim') {
                $role = $a['role'] ?? null;
                $this->ensure(is_string($role) && array_key_exists($role, config('game.role_alignments')), 'Choose a role to claim.');
                $entry['role'] = $role;
            } else {
                $entry['prompt_id'] = $this->prompt($s['day'])['id'];
                $entry['question'] = $this->prompt($s['day'])['question'];
            }
            $s[$key][] = $entry;

            return true;
        }
        if ($type === 'extend_discussion') {
            $this->ensure($p['alive'] && $s['phase'] === 'discussion', 'Only living players can extend a discussion.');
            $extension = $s['discussion_extension'] ?? ['voter_ids' => [], 'used' => false];
            $this->ensure(! $extension['used'], 'This discussion has already been extended.');
            $this->ensure(! in_array($id, $extension['voter_ids'], true), 'You already agreed to extend this discussion.');
            $extension['voter_ids'][] = $id;
            $living = array_keys(array_filter($s['players'], fn (array $player): bool => $player['alive']));
            if (array_diff($living, $extension['voter_ids']) === []) {
                $this->ensure($room->deadline !== null, 'This discussion has no active deadline.');
                $room->deadline = $room->deadline->addSeconds(30);
                $extension['used'] = true;
                // Agreement to more time supersedes readiness, without ending discussion.
                $s['actions'] = [];
                $s['log'][] = 'Everyone agreed: 30 more seconds to discuss. Ready for voting has been reset.';
            }
            $s['discussion_extension'] = $extension;

            return true;
        }
        if ($type === 'prediction') {
            $this->ensure(! $p['alive'] && in_array($s['phase'], ['night', 'discussion', 'last_words', 'voting'], true), 'Seal a prediction after you are banished and before the match ends.');
            $this->ensure(! isset($s['predictions'][$id]), 'Your prediction is already sealed.');
            $ids = $a['cultist_ids'] ?? null;
            $living = array_keys(array_filter($s['players'], fn (array $player): bool => $player['alive']));
            $this->ensure(is_array($ids) && array_is_list($ids) && count($ids) <= count($living), 'Choose the living players you suspect.');
            foreach ($ids as $candidate) {
                $this->ensure(is_string($candidate) && in_array($candidate, $living, true), 'Predictions may only name living players.');
            }
            $this->ensure(count(array_unique($ids)) === count($ids), 'Choose each player only once.');
            $this->ensure(in_array($a['winner'] ?? null, ['town', 'cult'], true), 'Choose the faction you expect to win.');
            $s['predictions'][$id] = ['cultist_ids' => $ids, 'winner' => $a['winner'], 'day' => $s['day'], 'living_ids' => $living];

            return true;
        }
        if ($type === 'feedback') {
            $this->ensure($s['phase'] === 'finished', 'Share feedback after the match ends.');
            $this->ensure(! isset($s['feedback'][$id]), 'Your feedback has already been saved.');
            $this->ensure(in_array($a['engagement'] ?? null, ['engaged', 'mixed', 'waiting'], true), 'Choose how involved you felt.');
            $body = trim($a['body'] ?? '');
            $this->ensure(mb_strlen($body) <= 280, 'Keep your feedback within 280 characters.');
            $s['feedback'][$id] = ['engagement' => $a['engagement'], 'body' => $body, 'role' => $p['role']];
            // Keep observations after a rematch without exposing another player's feedback.
            if (isset($s['match_id'])) {
                $match = GameMatch::where('id', $s['match_id'])->first();
                if ($match !== null) {
                    $recap = $match->recap;
                    $recap['feedback'] = $s['feedback'];
                    $match->update(['recap' => $recap]);
                }
            }

            return true;
        }

        return false;
    }

    /** @return array{id: string, question: string} */
    private function prompt(int $day): array
    {
        return self::PROMPTS[max(0, $day - 1) % count(self::PROMPTS)];
    }

    /** @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    public function view(array $s, string $id): array
    {
        $mist = ($s['players'][$id]['curse']['type'] ?? null) === 'mist' && $s['phase'] !== 'finished';

        return [
            'last_words' => $mist || ! isset($s['match_rules']['seconds']['last_words']) ? null : ($s['rounds'][$s['day']]['last_words'] ?? ['accusations' => [], 'accused_ids' => [], 'defenses' => []]),
            'claims' => $mist ? [] : ($s['claims'] ?? []),
            'responses' => $mist ? [] : ($s['responses'] ?? []),
            'prompt' => $s['phase'] === 'discussion' ? $this->prompt($s['day']) : null,
            'extension' => [...($s['discussion_extension'] ?? ['voter_ids' => [], 'used' => false]), 'seconds' => 30],
            'predictions' => $s['phase'] === 'finished' ? $this->predictionResults($s) : [],
        ];
    }

    /** @param array<string, mixed> $s
     * @return list<array<string, mixed>>
     */
    public function predictionResults(array $s): array
    {
        if (($s['winner'] ?? null) === null) {
            return [];
        }
        $results = [];
        foreach ($s['predictions'] ?? [] as $id => $prediction) {
            $cult = array_values(array_filter($prediction['living_ids'], fn (string $pid): bool => $s['players'][$pid]['alignment'] === 'cult'));
            $correct = count(array_intersect($prediction['cultist_ids'], $cult));
            $results[] = [
                'player_id' => $id, ...array_intersect_key($prediction, array_flip(['cultist_ids', 'winner', 'day'])),
                'correct_picks' => $correct, 'total_cultists' => count($cult),
                'exact' => $correct === count($cult) && count($prediction['cultist_ids']) === count($cult),
                'winner_correct' => $prediction['winner'] === $s['winner'],
                'informed' => $s['players'][$id]['alignment'] === 'cult',
            ];
        }

        return $results;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }
}
