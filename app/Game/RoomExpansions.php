<?php

namespace App\Game;

use Illuminate\Validation\ValidationException;

class RoomExpansions
{
    /** @param array<string,mixed> $state */
    public static function authorize(array $state): void
    {
        $id = $state['mode_setup']['expansion'] ?? null;
        if ($id === null) {
            return;
        }
        self::ensure(in_array($id, FactionExpansions::ADDITIONAL, true) && FactionExpansions::active($id), 'This faction expansion is currently inactive.');
        self::ensure(FactionExpansions::available($state, $id), 'One seated, verified player must own this faction expansion.');
    }

    /** Snapshot mechanics and ownership after roles are dealt.
     * @param  array<string,mixed>  $state
     */
    public static function start(array &$state): void
    {
        $id = $state['mode_setup']['expansion'] ?? null;
        if ($id === null) {
            return;
        }
        self::authorize($state);
        $state['expansion'] = ['id' => $id, 'rules' => config('factions.'.$id), 'marks' => [], 'relics' => [],
            'echoes' => 0, 'acts' => [], 'plans' => [], 'secured' => false, 'events' => []];
        if ($id === 'gilded-hand') {
            $holders = array_keys(array_filter($state['players'], fn (array $p): bool => $p['alignment'] !== 'gilded'));
            shuffle($holders);
            foreach (array_keys($state['expansion']['rules']['relics']) as $index => $relic) {
                $state['expansion']['relics'][$relic] = $holders[$index];
                self::event($state, $state['expansion']['rules']['relics'][$relic].' began with '.$state['players'][$holders[$index]]['name'].'.');
            }
        }
    }

    /** @param array<string,mixed> $state
     * @return list<array<string,mixed>>
     */
    public static function choices(array $state, string $id): array
    {
        if (! isset($state['expansion']) || ! $state['players'][$id]['alive']) {
            return [];
        }
        $p = $state['players'][$id];
        $choices = match ($p['role']) {
            'drowned_tidecaller' => [self::choice('mark', 'Lay a Drowned mark', 'Secretly mark an unmarked living villager outside the Drowned.', 'other')],
            'drowned_ferryman' => [self::choice('ferry', 'Ferry a mark', 'Move an existing mark to another unmarked living villager outside the Drowned.', 'other', secondary: true)],
            'gilded_lifter' => [self::choice('steal', 'Steal a relic', 'Take the selected relic if this player holds it. Conflicting moves fail.', 'other', relic: 'any')],
            'gilded_appraiser' => [self::choice('locate', 'Locate a relic', 'Privately learn who holds the selected relic after tonight’s transfers.', 'none', relic: 'any')],
            'choir_cantor' => [self::choice('siphon', 'Siphon the ritual', 'Divert one newly earned Cult step into an echo. The Cult must keep at least one step.', 'none')],
            'choir_resonant' => [self::choice('listen', 'Listen for a chant', 'Privately learn whether another player chanted tonight. This reveals no role.', 'other'),
                ...(($p['expansion_ability_used'] ?? false) ? [] : [self::choice('resonate', 'Resonate once', 'Amplify an effective Cantor siphon by one if enough steps were earned. Spent even if disrupted or no echo is gained.', 'none')])],
            'carnival_harlequin' => [self::choice('taunt', 'Survive an accusation', 'Receive another player’s accusation today, submit a ballot and survive the vote.', 'none'),
                self::choice('tie', 'Provoke a tied vote', 'Vote for one of at least two named candidates tied for the highest tally, with no banishment.', 'none')],
            'carnival_augur' => [self::choice('foretell', 'Foretell a banishment', 'Choose who will be banished in today’s vote. The prediction is sealed tonight.', 'other')],
            default => [],
        };
        if ($state['expansion']['id'] === 'drowned' && $p['alignment'] !== 'drowned') {
            $choices[] = self::choice('sound', 'Sound for a mark', 'Spend your night privately checking one living player, including yourself, for a Drowned mark.', 'living');
            $choices[] = self::choice('cleanse', 'Wash away a mark', 'Spend your night removing a Drowned mark and preventing it from returning tonight. You may choose yourself.', 'living');
        }
        if ($state['expansion']['id'] === 'gilded-hand' && in_array($id, $state['expansion']['relics'], true)) {
            $choices[] = self::choice('give', 'Hand over a relic', 'Give one of your relics to another living player instead of using your normal ability or chanting.', 'other', relic: 'owned');
        }

        return $choices;
    }

    /** @return array<string,mixed> */
    private static function choice(string $id, string $label, string $description, string $target, bool $secondary = false, ?string $relic = null): array
    {
        return ['id' => $id, 'label' => $label, 'description' => $description, 'target' => $target,
            ...($secondary ? ['secondary' => true] : []), ...($relic === null ? [] : ['relic' => $relic])];
    }

    /** @param array<string,mixed> $state
     * @param  array<string,mixed>  $action
     */
    public static function validate(array $state, string $id, array $action): bool
    {
        $kind = $action['expansion_action'] ?? null;
        $secondary = $action['secondary_target'] ?? null;
        $relic = $action['relic_id'] ?? null;
        if ($kind === null) {
            self::ensure($secondary === null && $relic === null, 'Choose an expansion action first.');

            return false;
        }
        $choice = collect(self::choices($state, $id))->firstWhere('id', $kind);
        self::ensure($choice !== null, 'This expansion action is unavailable to your role.');
        self::ensure(! ($action['use_ability'] ?? false) && ($action['forged_alignment'] ?? null) === null && ($action['curse_type'] ?? null) === null,
            'An expansion action replaces your normal ability or chant.');
        $target = $action['target'] ?? null;
        self::ensure($target === null || ($state['players'][$id]['curse']['type'] ?? null) !== 'misdirection', 'Break Misdirection before using a targeted expansion action, or keep watch.');
        self::ensure($choice['target'] === 'none' ? $target === null
            : isset($state['players'][$target]) && $state['players'][$target]['alive'] && ($choice['target'] === 'living' || $target !== $id), 'Choose a valid living target for this action.');
        self::ensure(($choice['secondary'] ?? false) ? isset($state['players'][$secondary]) && $state['players'][$secondary]['alive'] && $secondary !== $target : $secondary === null,
            'Choose a different living destination for the mark.');
        if (isset($choice['relic'])) {
            self::ensure(isset($state['expansion']['rules']['relics'][$relic]), 'Choose a known relic.');
            self::ensure($choice['relic'] !== 'owned' || $state['expansion']['relics'][$relic] === $id, 'You do not hold that relic.');
        } else {
            self::ensure($relic === null, 'This action does not use a relic.');
        }
        if (in_array($kind, ['mark', 'ferry'], true)) {
            self::ensure($state['players'][$target]['alignment'] !== 'drowned', 'Drowned marks belong on villagers outside your faction.');
            self::ensure($kind === 'ferry' ? isset($state['expansion']['marks'][$target]) : ! isset($state['expansion']['marks'][$target]),
                $kind === 'ferry' ? 'Choose an existing mark to move.' : 'That villager is already marked.');
            if ($kind === 'ferry') {
                self::ensure($state['players'][$secondary]['alignment'] !== 'drowned' && ! isset($state['expansion']['marks'][$secondary]), 'Choose an unmarked destination outside your faction.');
            }
        }

        return true;
    }

    /** @param array<string,mixed> $state
     * @param  array<string,mixed>  $effective
     */
    public static function siphon(array &$state, array $effective, int $gained): int
    {
        if (($state['expansion']['id'] ?? null) !== 'hollow-choir') {
            return $gained;
        }
        $cantors = array_keys(array_filter($effective, fn (array $a): bool => ($a['expansion_action'] ?? null) === 'siphon'));
        $resonants = array_keys(array_filter($effective, fn (array $a): bool => ($a['expansion_action'] ?? null) === 'resonate'));
        $taken = $cantors === [] ? 0 : min(max(0, $gained - 1), 1 + (int) ($resonants !== []));
        $state['tokens'] -= $taken;
        $state['expansion']['echoes'] += $taken;
        $state['expansion']['secured'] = $state['expansion']['echoes'] >= $state['expansion']['rules']['goal'];
        foreach ([...$cantors, ...$resonants] as $id) {
            self::result($state, $id, 'The Choir gathered '.$taken.' echo'.($taken === 1 ? '' : 'es').' from tonight’s ritual.');
        }
        if ($taken > 0) {
            $state['log'][] = 'The Hollow Choir diverted '.$taken.' ritual step'.($taken === 1 ? '' : 's').' into echoes.';
            self::event($state, 'The Choir siphoned '.$taken.' newly earned steps; the Cult kept '.($gained - $taken).'.');
        }

        return $gained - $taken;
    }

    /** Runs after simultaneous night departures.
     * @param  array<string,mixed>  $state
     * @param  array<string,mixed>  $effective
     * @param  array<string,mixed>  $chants
     */
    public static function night(array &$state, array $effective, array $chants): void
    {
        if (! isset($state['expansion'])) {
            return;
        }
        self::dropRelics($state);
        $washed = [];
        foreach ($effective as $id => $a) {
            if (($a['expansion_action'] ?? null) === 'cleanse' && $state['players'][$a['target']]['alive']) {
                $washed[$a['target']] = true;
                unset($state['expansion']['marks'][$a['target']]);
                self::result($state, $id, 'Salt washed '.$state['players'][$a['target']]['name'].'. Any Drowned mark is gone.');
            }
        }
        // New marks settle before ferries, so conflicting destinations never depend on submission order.
        foreach ($effective as $a) {
            if (($a['expansion_action'] ?? null) === 'mark' && $state['players'][$a['target']]['alive'] && ! isset($washed[$a['target']])) {
                $state['expansion']['marks'][$a['target']] = true;
            }
        }
        foreach ($effective as $id => $a) {
            $kind = $a['expansion_action'] ?? null;
            $target = $a['target'] ?? null;
            if ($kind === null) {
                continue;
            }
            self::event($state, $state['players'][$id]['name'].' chose '.$kind.($target === null ? '' : ' toward '.$state['players'][$target]['name']).'.');
            if ($kind === 'ferry') {
                $destination = $a['secondary_target'];
                if (isset($state['expansion']['marks'][$target]) && $state['players'][$target]['alive'] && $state['players'][$destination]['alive']
                    && ! isset($state['expansion']['marks'][$destination]) && ! isset($washed[$destination])) {
                    unset($state['expansion']['marks'][$target]);
                    $state['expansion']['marks'][$destination] = true;
                    self::result($state, $id, 'The mark moved to '.$state['players'][$destination]['name'].'.');
                } else {
                    self::result($state, $id, 'The mark could not be ferried tonight.');
                }
            }
            if ($kind === 'listen') {
                self::result($state, $id, $state['players'][$target]['name'].(isset($chants[$target]) ? ' chanted tonight.' : ' did not chant tonight.'));
            }
            if (in_array($kind, ['taunt', 'tie', 'foretell'], true)) {
                $state['expansion']['plans'][$state['day']][$id] = ['action' => $kind, 'target_id' => $target];
                self::result($state, $id, 'Your Carnival act is sealed: '.$kind.($target === null ? '.' : ' the banishment of '.$state['players'][$target]['name'].'.'));
            }
        }
        // Soundings describe the final marks after cleansings and movement, without revealing roles.
        foreach ($effective as $id => $a) {
            if (($a['expansion_action'] ?? null) === 'sound') {
                self::result($state, $id, $state['players'][$a['target']]['name'].(isset($state['expansion']['marks'][$a['target']]) ? ' carries a Drowned mark.' : ' carries no Drowned mark.'));
            }
        }
        self::transferRelics($state, $effective);
    }

    /** @param array<string,mixed> $state
     * @param  array<string,mixed>  $effective
     */
    private static function transferRelics(array &$state, array $effective): void
    {
        if ($state['expansion']['id'] !== 'gilded-hand') {
            return;
        }
        $moves = [];
        foreach ($effective as $id => $a) {
            $kind = $a['expansion_action'] ?? null;
            if (! in_array($kind, ['steal', 'give'], true)) {
                continue;
            }
            $relic = $a['relic_id'];
            $source = $kind === 'give' ? $id : $a['target'];
            $destination = $kind === 'give' ? $a['target'] : $id;
            if ($state['expansion']['relics'][$relic] === $source && $state['players'][$source]['alive'] && $state['players'][$destination]['alive']) {
                $moves[$relic][] = ['actor' => $id, 'source' => $source, 'destination' => $destination];
            } else {
                self::result($state, $id, 'The '.$state['expansion']['rules']['relics'][$relic].' could not be moved.');
            }
        }
        foreach ($moves as $relic => $proposals) {
            $name = $state['expansion']['rules']['relics'][$relic];
            if (count($proposals) > 1) {
                foreach ($proposals as $move) {
                    self::result($state, $move['actor'], 'Conflicting attempts left the '.$name.' where it was.');
                }

                continue;
            }
            $move = $proposals[0];
            $state['expansion']['relics'][$relic] = $move['destination'];
            self::result($state, $move['source'], 'The '.$name.' left your possession.');
            self::result($state, $move['destination'], 'You now hold the '.$name.'.');
            self::event($state, $name.' moved from '.$state['players'][$move['source']]['name'].' to '.$state['players'][$move['destination']]['name'].'.');
        }
        foreach ($effective as $id => $a) {
            if (($a['expansion_action'] ?? null) === 'locate') {
                $holder = $state['expansion']['relics'][$a['relic_id']];
                self::result($state, $id, 'The '.$state['expansion']['rules']['relics'][$a['relic_id']].' is held by '.$state['players'][$holder]['name'].'.');
            }
        }
    }

    /** @param array<string,mixed> $state */
    public static function dropRelics(array &$state): void
    {
        if (($state['expansion']['id'] ?? null) !== 'gilded-hand') {
            return;
        }
        $living = array_filter($state['players'], fn (array $p): bool => $p['alive']);
        $outside = array_filter($living, fn (array $p): bool => $p['alignment'] !== 'gilded');
        $pool = array_keys($outside ?: $living);
        foreach ($state['expansion']['relics'] as $relic => $holder) {
            if (! $state['players'][$holder]['alive'] && $pool !== []) {
                $next = $pool[array_rand($pool)];
                $state['expansion']['relics'][$relic] = $next;
                self::result($state, $next, 'You found the dropped '.$state['expansion']['rules']['relics'][$relic].'.');
                self::event($state, $state['expansion']['rules']['relics'][$relic].' was dropped and found by '.$state['players'][$next]['name'].'.');
            }
        }
    }

    /** @param array<string,mixed> $state */
    public static function cleanse(array &$state, string $target): void
    {
        if (($state['expansion']['id'] ?? null) === 'drowned') {
            unset($state['expansion']['marks'][$target]);
        }
    }

    /** @param array<string,mixed> $state */
    public static function settle(array &$state): void
    {
        if (! isset($state['expansion'])) {
            return;
        }
        self::dropRelics($state);
        $e = &$state['expansion'];
        if ($e['id'] === 'drowned' && $state['day'] % $e['rules']['tide_every'] === 0) {
            $marks = self::progress($state);
            $e['secured'] = $e['secured'] || $marks >= $e['rules']['goal'];
            $state['log'][] = 'The tide rose over '.$marks.' living marked villagers.';
            self::event($state, 'The tide found '.$marks.' living marked villagers.');
        }
        if ($e['id'] !== 'carnival') {
            return;
        }
        $vote = $state['rounds'][$state['day']]['vote'];
        $tallies = [];
        foreach ($vote['ballots'] as $ballot) {
            $key = $ballot['target_id'] ?? 'abstain';
            $tallies[$key] = ($tallies[$key] ?? 0) + 1;
        }
        $leaders = $tallies === [] ? [] : array_keys(array_filter($tallies, fn (int $n): bool => $n === max($tallies)));
        $candidates = array_values(array_diff($leaders, ['abstain']));
        foreach ($e['plans'][$state['day']] ?? [] as $id => $plan) {
            $action = $plan['action'];
            $ballot = $state['actions'][$id] ?? null;
            $success = match ($action) {
                'taunt' => $ballot !== null && $state['players'][$id]['alive'] && array_filter($state['rounds'][$state['day']]['last_words']['accusations'] ?? [],
                    fn (array $a): bool => $a['target_id'] === $id && $a['player_id'] !== $id) !== [],
                'tie' => $vote['banished_id'] === null && count($candidates) >= 2 && in_array($ballot['target'] ?? null, $candidates, true),
                'foretell' => $plan['target_id'] !== null && $vote['banished_id'] === $plan['target_id'],
                default => false,
            };
            if ($success && ! isset($e['acts'][$action])) {
                $e['acts'][$action] = $state['day'];
                self::result($state, $id, 'Your '.$action.' act earned the Carnival a new finale act.');
                $state['log'][] = 'The Carnival completed a new act.';
                self::event($state, $state['players'][$id]['name'].' completed '.$action.'.');
            }
        }
        $e['secured'] = count($e['acts']) >= $e['rules']['goal'] && count(array_unique(array_values($e['acts']))) >= $e['rules']['minimum_days'];
    }

    /** @param array<string,mixed> $state */
    private static function progress(array $state): int
    {
        $e = $state['expansion'];

        return match ($e['id']) {
            'drowned' => count(array_filter(array_keys($e['marks']), fn (int|string $id): bool => $state['players'][$id]['alive'] && $state['players'][$id]['alignment'] !== 'drowned')),
            'gilded-hand' => count(array_filter($e['relics'], fn (int|string $id): bool => $state['players'][$id]['alive'] && $state['players'][$id]['alignment'] === 'gilded')),
            'hollow-choir' => $e['echoes'],
            'carnival' => count($e['acts']),
            default => 0,
        };
    }

    /** @param array<string,mixed> $state */
    public static function finish(array &$state): void
    {
        if (! isset($state['expansion'])) {
            return;
        }
        self::dropRelics($state);
        $e = &$state['expansion'];
        if ($e['id'] === 'gilded-hand') {
            $e['secured'] = self::progress($state) >= $e['rules']['goal'];
        }
        if ($e['secured']) {
            $state['winners'][] = $e['rules']['alignment'];
            $state['win_reason'] .= ' '.$e['rules']['name'].' shares the victory after completing its objective.';
        }
    }

    /** @param array<string,mixed> $state
     * @return array<string,mixed>|null
     */
    public static function view(array $state, string $id, bool $recap = false): ?array
    {
        if (! isset($state['expansion'])) {
            return null;
        }
        $e = $state['expansion'];
        $court = $state['players'][$id]['alignment'] === $e['rules']['alignment'];
        $view = [...FactionExpansions::metadata($e['id'], $e['rules']), 'progress' => self::progress($state), 'secured' => $e['secured'],
            'night_choices' => ! $recap && $state['phase'] === 'night' ? self::choices($state, $id) : []];
        if ($e['id'] === 'drowned') {
            $view['tide_day'] = max(1, (int) ceil($state['day'] / $e['rules']['tide_every'])) * $e['rules']['tide_every'];
            if ($court || $recap) {
                $view['marks'] = array_values(array_filter(array_keys($e['marks']), fn (int|string $pid): bool => $state['players'][$pid]['alive']));
            }
        }
        if ($e['id'] === 'gilded-hand') {
            $view['inventory'] = array_keys(array_filter($e['relics'], fn (string $holder): bool => $holder === $id));
            if ($court || $recap) {
                $view['court_inventory'] = [];
                foreach ($e['relics'] as $relic => $holder) {
                    if ($recap || ($state['players'][$holder]['alignment'] === 'gilded' && $state['players'][$holder]['alive'])) {
                        $view['court_inventory'][] = ['relic_id' => $relic, 'holder_id' => $holder];
                    }
                }
            }
        }
        if ($e['id'] === 'carnival') {
            $view['acts'] = array_keys($e['acts']);
            $view['plan'] = $e['plans'][$state['day']][$id] ?? null;
        }
        if ($recap) {
            $view['events'] = $e['events'];
        }

        return $view;
    }

    /** @param array<string,mixed> $state */
    private static function result(array &$state, string $id, string $text): void
    {
        $state['players'][$id]['results'][] = ['kind' => 'expansion', 'day' => $state['day'], 'target' => '', 'text' => $text];
    }

    /** @param array<string,mixed> $state */
    private static function event(array &$state, string $text): void
    {
        $state['expansion']['events'][] = ['day' => $state['day'], 'text' => $text];
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
