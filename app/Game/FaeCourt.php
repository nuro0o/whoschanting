<?php

namespace App\Game;

use App\Models\PaidOrder;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaeCourt
{
    /** @param array<string, mixed> $state */
    public static function available(array $state): bool
    {
        if (! FactionExpansions::active('fae-court')) {
            return false;
        }
        $accounts = array_values(array_filter(array_column($state['players'], 'user_id')));

        return $accounts !== [] && PaidOrder::whereIn('user_id', User::whereIn('id', $accounts)->whereNotNull('email_verified_at')->select('id'))
            ->where('bundle_id', 'fae-court')->where('status', 'paid')->exists();
    }

    /** @param array<string, mixed> $state */
    public static function start(array &$state): void
    {
        if (! ($state['mode_setup']['fae_court'] ?? false)) {
            return;
        }
        self::ensure(FactionExpansions::active('fae-court'), 'The Fae Court expansion is currently inactive.');
        self::ensure(self::available($state), 'The Fae Court needs one seated player who owns the expansion.');
        $state['fae'] = ['rules' => config('fae'), 'bargains' => []];
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $action
     * @return array<string,mixed>
     */
    public static function validateOffer(array $state, string $id, array $action): array
    {
        $renewal = $action['renewal_id'] ?? null;
        $collector = $state['players'][$id]['role'] === 'fae_collector';
        if ($collector && $renewal !== null) {
            self::ensure(! ($state['players'][$id]['collector_used'] ?? false), 'Your renewal has already been spent.');
            self::ensure(($action['bargain_kind'] ?? null) === null && ($action['promise_target'] ?? null) === null && ($action['gift_target'] ?? null) === null, 'A renewed bargain keeps its original terms.');
            $original = collect(self::renewable($state))->firstWhere('id', $renewal);
            self::ensure($original !== null, 'Choose a failed bargain from an earlier night.');
            self::ensure(($action['target'] ?? null) !== null && $action['target'] !== $original['recipient_id'], 'Renew the bargain with a different player.');
            $action['bargain_kind'] = $original['kind'];
            $action['promise_target'] = $original['promise_target'];
            $action['gift_target'] = $original['gift_target'] ?? null;
        } else {
            self::ensure($renewal === null, 'Only the Collector can renew a bargain.');
            self::ensure(! $collector || ($action['target'] ?? null) === null, 'Choose a failed bargain to renew, or keep watch.');
        }
        $target = $action['target'] ?? null;
        $kind = $action['bargain_kind'] ?? null;
        $promise = $action['promise_target'] ?? null;
        $gift = $action['gift_target'] ?? null;
        if (! in_array($state['players'][$id]['role'], ['fae_broker', 'fae_collector'], true)) {
            self::ensure($kind === null && $promise === null && $gift === null, 'Only the Fae Broker can offer a bargain.');

            return $action;
        }
        self::ensure(isset($state['fae']), 'This match does not include the Fae Court.');
        if ($target === null) {
            self::ensure($kind === null && $promise === null && $gift === null, 'Choose a recipient for your bargain.');

            return $action;
        }
        self::ensure(in_array($kind, ['thorn', 'voice', 'passage'], true), 'Choose a bargain.');
        self::ensure(($state['players'][$target]['alignment'] ?? null) !== 'fae', 'Choose a player outside the Court.');
        foreach ($state['actions'] as $actor => $submitted) {
            self::ensure(! in_array($state['players'][$actor]['role'], ['fae_broker', 'fae_collector'], true) || ($submitted['target'] ?? null) !== $target, 'Your teammate already chose that recipient tonight. Choose another player.');
        }
        // A partner who has already earned a seal cannot be farmed for another.
        foreach ($state['fae']['bargains'] as $bargain) {
            self::ensure($bargain['recipient_id'] !== $target || $bargain['status'] !== 'fulfilled', 'That partner has already earned the Court a seal.');
        }
        if ($kind === 'voice') {
            self::ensure($promise === null, 'A Borrowed Voice asks for abstention, not a target.');
            self::ensure($gift !== $target && isset($state['players'][$gift]) && $state['players'][$gift]['alive'], 'Choose a living player other than the recipient whose ballot will be revealed.');
        } else {
            self::ensure($gift === null, 'Only A Borrowed Voice can offer a ballot report.');
            self::ensure($promise !== $target && isset($state['players'][$promise]) && $state['players'][$promise]['alive'], 'Choose a living player other than the recipient for the promise.');
        }

        return $action;
    }

    /** @param array<string,mixed> $state
     * @return list<array<string,mixed>>
     */
    private static function renewable(array $state): array
    {
        return array_values(array_filter($state['fae']['bargains'] ?? [], function (array $b) use ($state): bool {
            return $b['day'] < $state['day'] && in_array($b['status'], ['broken', 'declined', 'expired'], true)
                && in_array($b['kind'], ['thorn', 'voice', 'passage'], true)
                && ($b['kind'] === 'voice'
                    ? isset($b['gift_target']) && $state['players'][$b['gift_target']]['alive']
                    : $state['players'][$b['promise_target']]['alive']);
        }));
    }

    /** Resolve offers only after disruptions and night departures, before the private response window.
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $effective
     * @param  array<string, bool>  $hiddenVisitors
     */
    public static function deliver(array &$state, array $effective, array $hiddenVisitors): void
    {
        if (! isset($state['fae'])) {
            return;
        }
        foreach ($effective as $id => $action) {
            $target = $action['target'] ?? null;
            if (! in_array($state['players'][$id]['role'], ['fae_broker', 'fae_collector'], true) || $target === null) {
                continue;
            }
            $promise = $action['promise_target'] ?? null;
            $gift = $action['gift_target'] ?? null;
            $valid = $state['players'][$target]['alive'] && ($promise === null || $state['players'][$promise]['alive'])
                && ($gift === null || $state['players'][$gift]['alive']);
            $visited = false;
            foreach ($state['actions'] as $visitor => $visit) {
                if ($visitor !== $target && ! isset($hiddenVisitors[$visitor]) && $promise !== null && ($visit['target'] ?? null) === $promise) {
                    $visited = true;
                }
            }
            $state['fae']['bargains'][] = ['id' => (string) Str::uuid(), 'day' => $state['day'], 'sender_id' => $id,
                'renewal_id' => $action['renewal_id'] ?? null, 'recipient_id' => $target, 'kind' => $action['bargain_kind'], 'promise_target' => $promise, 'gift_target' => $gift,
                'status' => $valid ? 'offered' : 'void', 'visited' => $visited];
        }
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $action
     */
    public static function respond(array &$state, string $id, array $action): void
    {
        self::ensure($state['phase'] === 'bargains' && $state['players'][$id]['alive'], 'Respond to your bargain during the private bargain window.');
        self::ensure(is_bool($action['accept'] ?? null), 'Accept or decline the bargain.');
        foreach ($state['fae']['bargains'] ?? [] as $index => $bargain) {
            if ($bargain['id'] !== ($action['bargain_id'] ?? null) || $bargain['recipient_id'] !== $id) {
                continue;
            }
            self::ensure($bargain['status'] === 'offered' && $bargain['day'] === $state['day'], 'This offer is already answered or has expired.');
            $state['fae']['bargains'][$index]['status'] = $action['accept'] ? 'accepted' : 'declined';
            if ($action['accept']) {
                if ($bargain['kind'] === 'thorn') {
                    $state['players'][$id]['fae_protection_day'] = $state['day'] + 1;
                } elseif ($bargain['kind'] === 'voice' && empty($bargain['gift_target'])) {
                    // Honor cleansing offers already issued before the ballot-report change.
                    $state['players'][$id]['curse'] = null;
                    $state['players'][$id]['haunting'] = null;
                    $state['players'][$id]['curse_notice'] = null;
                } elseif ($bargain['kind'] === 'lantern') {
                    $state['players'][$id]['results'][] = ['kind' => 'visits', 'day' => $state['day'],
                        'target' => $state['players'][$bargain['promise_target']]['name'], 'visited' => $bargain['visited']];
                }
            }

            return;
        }
        self::ensure(false, 'This offer is unavailable.');
    }

    /** @param array<string, mixed> $state */
    public static function expire(array &$state): void
    {
        foreach ($state['fae']['bargains'] ?? [] as $index => $bargain) {
            if ($bargain['status'] === 'offered') {
                $state['fae']['bargains'][$index]['status'] = 'expired';
            }
        }
    }

    /** Actual submitted ballots count, including redirection; missing ballots never fulfill a promise.
     * @param  array<string, mixed>  $state
     */
    public static function settle(array &$state): void
    {
        foreach ($state['fae']['bargains'] ?? [] as $index => $bargain) {
            if ($bargain['day'] !== $state['day'] || $bargain['status'] !== 'accepted') {
                continue;
            }
            $id = $bargain['recipient_id'];
            $ballot = $state['actions'][$id] ?? null;
            $kept = match ($bargain['kind']) {
                'thorn', 'passage' => ($ballot['type'] ?? null) === 'vote' && ($ballot['target'] ?? null) === $bargain['promise_target'],
                'voice' => ($ballot['type'] ?? null) === 'vote' && ($ballot['target'] ?? null) === null,
                'lantern' => array_filter($state['rounds'][$state['day']]['last_words']['accusations'] ?? [],
                    fn (array $accusation): bool => $accusation['player_id'] === $id && $accusation['target_id'] === $bargain['promise_target']) !== [],
                default => false,
            };
            $state['fae']['bargains'][$index]['status'] = $kept ? 'fulfilled' : 'broken';
            if ($bargain['kind'] === 'voice' && isset($bargain['gift_target'])) {
                // Read the resolved ballot, never the intended target or the player's role.
                // Accepted gifts remain theirs even when a promise is broken.
                $vote = $state['actions'][$bargain['gift_target']] ?? null;
                $submitted = ($vote['type'] ?? null) === 'vote';
                $votedFor = $submitted ? ($vote['target'] ?? null) : null;
                $state['players'][$id]['results'][] = ['kind' => 'ballot', 'day' => $state['day'],
                    'target' => $state['players'][$bargain['gift_target']]['name'], 'submitted' => $submitted,
                    'voted_for' => $votedFor === null ? null : $state['players'][$votedFor]['name']];
            }
            if ($kept) {
                if ($bargain['kind'] === 'passage') {
                    $state['players'][$id]['fae_passage_day'] = $state['day'] + 1;
                    $state['players'][$id]['results'][] = ['kind' => 'passage', 'day' => $state['day'], 'target' => $state['players'][$id]['name']];
                }
                $state['log'][] = 'A bargain was fulfilled. The Fae Court earned a seal.';
            }
        }
    }

    /** @param array<string, mixed> $state
     * @return list<array<string, mixed>>
     */
    private static function fulfilled(array $state): array
    {
        return array_values(array_filter($state['fae']['bargains'] ?? [], fn (array $b): bool => $b['status'] === 'fulfilled'));
    }

    /** @param array<string, mixed> $state */
    public static function finish(array &$state): void
    {
        $state['winners'] = [$state['winner']];
        if (! isset($state['fae'])) {
            return;
        }
        self::expire($state);
        foreach ($state['fae']['bargains'] as $index => $bargain) {
            if ($bargain['status'] === 'accepted') {
                $state['fae']['bargains'][$index]['status'] = 'void';
            }
        }
        $fulfilled = self::fulfilled($state);
        $partners = array_unique(array_column($fulfilled, 'recipient_id'));
        $days = array_unique(array_column($fulfilled, 'day'));
        $winningPartner = array_filter($partners, fn (string $id): bool => $state['players'][$id]['alignment'] === $state['winner']) !== [];
        if (count($partners) >= $state['fae']['rules']['seals_to_win'] && count($days) >= $state['fae']['rules']['rounds_to_win'] && $winningPartner) {
            $state['winners'][] = 'fae';
            $state['win_reason'] .= ' The Fae Court shares the victory: its bargains included a partner on the winning side.';
        }
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    public static function view(array $state, string $id): ?array
    {
        if (! isset($state['fae'])) {
            return null;
        }
        $finished = $state['phase'] === 'finished';
        $court = $state['players'][$id]['alignment'] === 'fae';
        $bargains = [];
        foreach ($state['fae']['bargains'] as $bargain) {
            if (! $finished && ! $court && $bargain['recipient_id'] !== $id) {
                continue;
            }
            $public = array_intersect_key($bargain, array_flip(['id', 'day', 'recipient_id', 'kind', 'promise_target', 'gift_target', 'status']));
            if ($finished) {
                $public['sender_id'] = $bargain['sender_id'];
                $public['renewal_id'] = $bargain['renewal_id'] ?? null;
            }
            $bargains[] = $public;
        }

        return ['seals' => count(array_unique(array_column(self::fulfilled($state), 'recipient_id'))),
            'goal' => $state['fae']['rules']['seals_to_win'], 'minimum_rounds' => $state['fae']['rules']['rounds_to_win'],
            'bargains' => $bargains,
            ...($state['players'][$id]['role'] === 'fae_collector' ? [
                'collector_used' => $state['players'][$id]['collector_used'] ?? false,
                'renewable_ids' => ($state['players'][$id]['collector_used'] ?? false) ? [] : array_column(self::renewable($state), 'id'),
            ] : [])];
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
