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
        self::ensure(self::available($state), 'The Fae Court needs one seated player who owns the expansion.');
        $state['fae'] = ['rules' => config('fae'), 'bargains' => []];
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $action
     */
    public static function validateOffer(array $state, string $id, array $action): void
    {
        $target = $action['target'] ?? null;
        $kind = $action['bargain_kind'] ?? null;
        $promise = $action['promise_target'] ?? null;
        if ($state['players'][$id]['role'] !== 'fae_broker') {
            self::ensure($kind === null && $promise === null, 'Only the Fae Broker can offer a bargain.');

            return;
        }
        self::ensure(isset($state['fae']), 'This match does not include the Fae Court.');
        if ($target === null) {
            self::ensure($kind === null && $promise === null, 'Choose a recipient for your bargain.');

            return;
        }
        self::ensure(in_array($kind, ['thorn', 'voice', 'lantern'], true), 'Choose a bargain.');
        self::ensure(($state['players'][$target]['alignment'] ?? null) !== 'fae', 'Choose a player outside the Court.');
        // A partner who has already earned a seal cannot be farmed for another.
        foreach ($state['fae']['bargains'] as $bargain) {
            self::ensure($bargain['recipient_id'] !== $target || $bargain['status'] !== 'fulfilled', 'That partner has already earned the Court a seal.');
        }
        if ($kind === 'voice') {
            self::ensure($promise === null, 'A Borrowed Voice asks for abstention, not a target.');
        } else {
            self::ensure($promise !== $target && isset($state['players'][$promise]) && $state['players'][$promise]['alive'], 'Choose a living player other than the recipient for the promise.');
        }
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
            if ($state['players'][$id]['role'] !== 'fae_broker' || $target === null) {
                continue;
            }
            $promise = $action['promise_target'] ?? null;
            $valid = $state['players'][$target]['alive'] && ($promise === null || $state['players'][$promise]['alive']);
            $visited = false;
            foreach ($state['actions'] as $visitor => $visit) {
                if ($visitor !== $target && ! isset($hiddenVisitors[$visitor]) && $promise !== null && ($visit['target'] ?? null) === $promise) {
                    $visited = true;
                }
            }
            $state['fae']['bargains'][] = ['id' => (string) Str::uuid(), 'day' => $state['day'], 'sender_id' => $id,
                'recipient_id' => $target, 'kind' => $action['bargain_kind'], 'promise_target' => $promise,
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
                } elseif ($bargain['kind'] === 'voice') {
                    $state['players'][$id]['curse'] = null;
                    $state['players'][$id]['haunting'] = null;
                    $state['players'][$id]['curse_notice'] = null;
                } else {
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
                'thorn' => ($ballot['type'] ?? null) === 'vote' && ($ballot['target'] ?? null) === $bargain['promise_target'],
                'voice' => ($ballot['type'] ?? null) === 'vote' && ($ballot['target'] ?? null) === null,
                'lantern' => array_filter($state['rounds'][$state['day']]['last_words']['accusations'] ?? [],
                    fn (array $accusation): bool => $accusation['player_id'] === $id && $accusation['target_id'] === $bargain['promise_target']) !== [],
                default => false,
            };
            $state['fae']['bargains'][$index]['status'] = $kept ? 'fulfilled' : 'broken';
            if ($kept) {
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
            $public = array_intersect_key($bargain, array_flip(['id', 'day', 'recipient_id', 'kind', 'promise_target', 'status']));
            if ($finished) {
                $public['sender_id'] = $bargain['sender_id'];
            }
            $bargains[] = $public;
        }

        return ['seals' => count(array_unique(array_column(self::fulfilled($state), 'recipient_id'))),
            'goal' => $state['fae']['rules']['seals_to_win'], 'minimum_rounds' => $state['fae']['rules']['rounds_to_win'],
            'bargains' => $bargains];
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
