<?php

namespace App\Game;

use App\Models\GameMatch;
use App\Models\PlayerProfile;
use Carbon\CarbonImmutable;

class CrownEarningGuard
{
    /** Called once per eligible match, after receipt deduplication, with the account profile locked.
     * Returns the end of a crown-only cooldown; the caller saves the profile in the match transaction.
     */
    public function checkLocked(PlayerProfile $profile, GameMatch $match): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();
        if ($profile->crown_cooldown_until?->isAfter($now)) {
            return $profile->crown_cooldown_until;
        }

        if ($profile->crown_cooldown_until !== null) {
            $profile->crown_cooldown_until = null;
            $profile->rapid_crown_matches = [];
        }

        $cutoff = $now->subMinutes((int) config('store.earning_guard.window_minutes'));
        $recent = array_values(array_filter($profile->rapid_crown_matches ?? [],
            fn (string $at): bool => CarbonImmutable::parse($at)->isAfter($cutoff)));
        // Use server-recorded match timestamps, never a client's claimed duration.
        $duration = $match->started_at === null ? null : CarbonImmutable::parse($match->started_at)->diffInSeconds($match->finished_at);
        if ($duration !== null && $duration >= 0 && $duration < (int) config('store.earning_guard.rapid_match_seconds')) {
            $recent[] = $now->toISOString();
        }

        if (count($recent) >= (int) config('store.earning_guard.rapid_match_limit')) {
            $profile->rapid_crown_matches = [];
            $profile->crown_cooldown_until = $now->addMinutes((int) config('store.earning_guard.cooldown_minutes'));

            return $profile->crown_cooldown_until;
        }

        $profile->rapid_crown_matches = $recent;

        return null;
    }
}
