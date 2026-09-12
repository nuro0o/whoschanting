<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use Illuminate\Console\Command;

class GameFeedback extends Command
{
    protected $signature = 'game:feedback {--rules-version= : Limit to a rules version}';

    protected $description = 'Summarize voluntary post-match engagement feedback by role';

    public function handle(): int
    {
        $query = GameMatch::query();
        if ($this->option('rules-version') !== null) {
            $query->where('rules_version', $this->option('rules-version'));
        }
        $counts = [];
        foreach ($query->cursor() as $match) {
            foreach ($match->recap['feedback'] ?? [] as $feedback) {
                $role = $feedback['role'] ?? 'unknown';
                $engagement = $feedback['engagement'] ?? null;
                if (! in_array($engagement, ['engaged', 'mixed', 'waiting'], true)) {
                    continue;
                }
                $counts[$role] ??= ['engaged' => 0, 'mixed' => 0, 'waiting' => 0];
                $counts[$role][$engagement]++;
            }
        }
        if ($counts === []) {
            $this->info('No engagement feedback yet. Ask players to answer after a match.');

            return self::SUCCESS;
        }
        ksort($counts);
        $rows = [];
        foreach ($counts as $role => $scores) {
            $total = array_sum($scores);
            $rows[] = [$role, $total, $scores['engaged'], $scores['mixed'], $scores['waiting'], round($scores['waiting'] / $total * 100).'%'];
        }
        $this->table(['Role', 'Responses', 'Engaged', 'Mixed', 'Waiting', 'Waiting share'], $rows);
        $this->line('Voluntary responses, not a controlled playtest. Compare similar rules versions and group sizes before changing balance.');

        return self::SUCCESS;
    }
}
