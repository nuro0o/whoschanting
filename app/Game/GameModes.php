<?php

namespace App\Game;

use Illuminate\Validation\ValidationException;

class GameModes
{
    public function discussionBonus(int $count): int
    {
        return max(0, $count - config('game.discussion_base_players')) * config('game.discussion_extra_seconds_per_player');
    }

    /** @return array<string, int> */
    public function phaseSeconds(int $count): array
    {
        $seconds = config('game.seconds');
        $seconds['discussion'] += $this->discussionBonus($count);

        return $seconds;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalize(array $input = []): array
    {
        $mode = $input['mode'] ?? 'classic';
        $classic = $input['classic_variant'] ?? 'classic';
        $chaos = $input['chaos_variant'] ?? 'wildcards';
        $fae = $input['fae_court'] ?? false;
        $expansion = $input['expansion'] ?? null;
        $this->ensure($expansion === null || (is_string($expansion) && in_array($expansion, FactionExpansions::ADDITIONAL, true)), 'Choose a known faction expansion.');
        $this->ensure($expansion === null || ($mode === 'classic' && ! $fae), 'Choose one faction expansion in Classic or Classic Illusions.');
        $this->ensure(is_bool($fae), 'Choose whether to enable the Fae Court.');
        $this->ensure(! $fae || $mode === 'classic', 'The Fae Court currently supports Classic and Classic Illusions.');
        $this->ensure(in_array($mode, ['classic', 'hard', 'chaos', 'paranoia', 'custom'], true), 'Choose a valid mode.');
        $this->ensure(in_array($classic, ['classic', 'illusions'], true), 'Choose a valid Classic variant.');
        $this->ensure(in_array($chaos, ['wildcards', 'maelstrom'], true), 'Choose a valid Chaos variant.');
        $roles = [];
        if ($mode === 'custom') {
            $selected = $input['roles'] ?? ['oracle' => 1, 'veilweaver' => 1, 'townsperson' => 1];
            $this->ensure(is_array($selected), 'Choose roles and their counts.');
            foreach ($selected as $role => $count) {
                $this->ensure(isset(config('game.role_alignments')[$role]), 'Choose a known role.');
                $this->ensure($role !== 'fae_broker', 'Enable the Fae Court expansion in Classic to include the Broker.');
                $this->ensure(in_array(config('game.role_alignments')[$role], ['town', 'cult'], true), 'Paid faction roles must be enabled through their room expansion.');
                $this->ensure(is_int($count) && $count >= 0 && $count <= config('game.max_players'), 'Role counts must be whole numbers between 0 and '.config('game.max_players').'.');
                if ($count > 0) {
                    $roles[$role] = $count;
                }
            }
            $total = array_sum($roles);
            $this->ensure($total >= config('game.min_players') && $total <= config('game.max_players'), 'Choose between '.config('game.min_players').' and '.config('game.max_players').' total roles.');
            $sides = array_unique(array_map(fn (string $role): string => config('game.role_alignments')[$role], array_keys($roles)));
            $this->ensure(count($sides) === 2, 'Custom games need at least one Town role and one Cult role.');
            ksort($roles);
        }

        return ['mode' => $mode, 'classic_variant' => $classic, 'chaos_variant' => $chaos, 'roles' => $roles, 'fae_court' => $fae, 'expansion' => $expansion];
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function setup(array $state): array
    {
        return $state['mode_setup'] ?? $this->normalize(['classic_variant' => $state['roster'] ?? 'classic']);
    }

    /** @param array<string, mixed> $setup
     * @return list<string>
     */
    public function roster(array $setup, int $count): array
    {
        if (isset($setup['expansion'])) {
            $this->ensure(in_array($setup['expansion'], FactionExpansions::ADDITIONAL, true), 'Choose a known faction expansion.');
            $rules = config('factions.'.$setup['expansion']);
            $this->ensure($setup['mode'] === 'classic' && ! ($setup['fae_court'] ?? false) && $count >= $rules['min_players'],
                $rules['name'].' needs '.$rules['min_players'].' or more players in Classic or Classic Illusions, with one expansion selected.');
            $roles = $this->roster([...$setup, 'expansion' => null], $count);
            foreach ($rules['roles'] as $index => $role) {
                $seat = array_search($rules['replace'][$index], $roles, true);
                $this->ensure($seat !== false, 'This roster has no room for '.$rules['name'].'.');
                $roles[$seat] = $role;
            }

            return array_values($roles);
        }
        if ($setup['fae_court'] ?? false) {
            $this->ensure($setup['mode'] === 'classic' && $count >= config('fae.min_players'), 'The Fae Court needs at least '.config('fae.min_players').' players in Classic.');
            $roles = $this->roster([...$setup, 'fae_court' => false], $count);
            // One randomly dealt Fae seat replaces a Townsperson, never the Oracle.
            $index = array_search('townsperson', $roles, true);
            $this->ensure($index !== false, 'This roster has no Townsperson seat for the Fae Court.');
            $roles[$index] = 'fae_broker';
            $cultSeat = array_search('acolyte', $roles, true);
            if ($cultSeat === false) {
                $cultSeat = array_search('dreamweaver', $roles, true);
            }
            $this->ensure($cultSeat !== false, 'This roster has no spare Cult seat for the Collector.');
            $roles[$cultSeat] = 'fae_collector';

            return array_values($roles);
        }
        $this->ensure($count >= config('game.min_players') && $count <= config('game.max_players'), 'Gather between '.config('game.min_players').' and '.config('game.max_players').' players.');
        if ($setup['mode'] === 'custom') {
            $this->ensure(array_sum($setup['roles']) === $count, 'Custom setup requires exactly '.array_sum($setup['roles']).' players. Adjust the roles or invite the remaining players.');
            $roles = [];
            foreach ($setup['roles'] as $role => $copies) {
                array_push($roles, ...array_fill(0, $copies, $role));
            }

            return $roles;
        }
        $expanded = $setup['mode'] !== 'classic' || $setup['classic_variant'] === 'illusions';
        $this->ensure(! $expanded || $count >= 5, 'This mode needs at least 5 players.');
        $cultCount = config('game.cultists_by_player_count.'.$count);
        $this->ensure(is_int($cultCount) && $cultCount >= 1 && $cultCount < $count, 'No valid role roster is configured for this room size.');
        if (in_array($setup['mode'], ['chaos', 'paranoia'], true)) {
            $roles = [];
            foreach (['cult' => $cultCount, 'town' => $count - $cultCount] as $side => $seats) {
                $pool = array_keys(array_filter(config('game.role_alignments'), fn (string $alignment): bool => $alignment === $side));
                for ($i = 0; $i < $seats; $i++) {
                    $roles[] = $pool[random_int(0, count($pool) - 1)];
                }
            }

            return $roles;
        }
        $illusions = $setup['mode'] === 'classic' && $setup['classic_variant'] === 'illusions';
        $prefix = $setup['mode'] === 'hard' ? 'hard_' : ($illusions ? 'illusion_' : '');
        $cult = [$illusions ? 'phantasm' : 'veilweaver'];
        $town = ['oracle'];
        foreach (config('game.'.$prefix.'cult_roles_min_players') as $role => $minimum) {
            if ($count >= $minimum && count($cult) < $cultCount) {
                $cult[] = $role;
            }
        }
        foreach (config('game.'.$prefix.'town_roles_min_players') as $role => $minimum) {
            if ($count >= $minimum && count($town) < $count - $cultCount) {
                $town[] = $role;
            }
        }

        return [...array_pad($cult, $cultCount, 'acolyte'), ...array_pad($town, $count - $cultCount, 'townsperson')];
    }

    /** @param array<string, mixed> $setup
     * @return array<string, mixed>
     */
    public function preview(array $setup, int $count): array
    {
        $required = $setup['mode'] === 'custom' ? array_sum($setup['roles']) : null;
        try {
            // Preview random rosters without consuming randomness or implying a guaranteed deal.
            if (in_array($setup['mode'], ['chaos', 'paranoia'], true)) {
                $this->ensure($count >= 5 && $count <= config('game.max_players'), ucfirst($setup['mode']).' needs 5–'.config('game.max_players').' players.');
                $roles = null;
            } else {
                $roles = array_count_values($this->roster($setup, $count));
            }
            $error = null;
        } catch (ValidationException $exception) {
            $roles = $setup['mode'] === 'custom' ? $setup['roles'] : null;
            $error = $exception->errors()['game'][0];
        }

        $preview = ['roles' => $roles, 'required_players' => $required, 'error' => $error];
        if ($setup['mode'] === 'paranoia') {
            $preview['possible_roles'] = [];
            foreach (['town', 'cult'] as $side) {
                $preview['possible_roles'][$side] = array_keys(array_filter(config('game.role_alignments'), fn (string $alignment): bool => $alignment === $side));
            }
            $cultCount = config('game.cultists_by_player_count.'.$count);
            $preview['team_counts'] = $error === null ? ['town' => $count - $cultCount, 'cult' => $cultCount] : null;
            $preview['discussion_seconds'] = array_map(fn (int $seconds): int => $seconds + $this->discussionBonus($count), config('game.paranoia_discussion_seconds'));
        }

        return $preview;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['game' => $message]);
        }
    }
}
