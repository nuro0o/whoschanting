<?php

namespace App\Game;

class SeasonalAchievements
{
    /** Only authoritative resolved match records count. No client-supplied counters.
     * @param  array<string, mixed>  $state
     * @param  array<string, int>  $progress
     * @return array{progress: array<string, int>, completed: list<string>}
     */
    public function evaluate(array $state, string $seatId, array $progress): array
    {
        $role = $state['players'][$seatId]['role'];
        $saved = false;
        $redirected = false;
        $kept = false;
        $broken = false;
        foreach ($state['rounds'] ?? [] as $round) {
            foreach ($round['night']['actions'] ?? [] as $action) {
                if (($action['player_id'] ?? null) === $seatId && $role === 'warden'
                    && ($action['submitted'] ?? false) && ! ($action['disrupted'] ?? false)
                    && ($action['prevented_curse'] ?? false) && ($action['target_id'] ?? null) !== $seatId) {
                    $saved = true;
                }
                if (! ($action['disrupted'] ?? false) && $this->redirectedVigilanteShot($state, $seatId, $action)) {
                    $redirected = true;
                }
            }
            foreach ($round['vote']['ballots'] ?? [] as $ballot) {
                if (($ballot['player_id'] ?? null) === $seatId) {
                    $kept = $kept || ($ballot['oath_kept'] ?? null) === true;
                    $broken = $broken || ($ballot['oath_kept'] ?? null) === false;
                }
            }
        }
        $completed = [];
        if ($saved) {
            $completed[] = 'seasonal_warden';
        }
        if (in_array($role, ['veilweaver', 'acolyte'], true) && $redirected) {
            $completed[] = 'seasonal_cultist';
        }
        // Other roles do not affect the run. Every qualifying Oathkeeper match
        // must contain a kept promise and no broken promises; a new season starts at zero.
        if ($role === 'oathkeeper') {
            $progress['oath_streak'] = $kept && ! $broken ? min(5, ($progress['oath_streak'] ?? 0) + 1) : 0;
            if ($progress['oath_streak'] >= 5) {
                $completed[] = 'seasonal_oathkeeper';
            }
        }

        return ['progress' => $progress, 'completed' => $completed];
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $action
     */
    private function redirectedVigilanteShot(array $state, string $source, array $action): bool
    {
        return ($action['misdirection_source_id'] ?? null) === $source
            && ($action['submitted'] ?? false)
            && ($action['shot_fired'] ?? false)
            && ($state['players'][$action['player_id'] ?? '']['role'] ?? null) === 'vigilante'
            && ($state['players'][$action['player_id'] ?? '']['alignment'] ?? null) === 'town'
            && ($action['chosen_target_id'] ?? null) !== null
            && ($action['target_id'] ?? null) !== null
            && $action['chosen_target_id'] !== $action['target_id'];
    }
}
