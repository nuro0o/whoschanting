<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use Illuminate\Console\Command;

class GameStats extends Command
{
    protected $signature = 'game:stats {--json : Output aggregate results as JSON}';

    protected $description = 'Compare completed matches by rules version, starting player count, and mission';

    public function handle(): int
    {
        $groups = GameMatch::query()
            ->select(['rules_version', 'player_count', 'mission', 'recap_complete'])
            ->selectRaw('COUNT(*) AS matches, SUM(CASE WHEN winner = ? THEN 1 ELSE 0 END) AS cult_wins', ['cult'])
            ->selectRaw('AVG(nights) AS average_nights, AVG(duration_seconds) AS average_seconds')
            ->selectRaw('SUM(missed_night_actions) AS missed_night_actions, SUM(missed_votes) AS missed_votes')
            ->groupBy('rules_version', 'player_count', 'mission', 'recap_complete')
            ->orderBy('rules_version')->orderBy('player_count')->orderBy('mission')->orderBy('recap_complete')
            ->get()->map(fn (GameMatch $row): array => [
                'rules_version' => $row->getAttribute('rules_version'),
                'player_count' => (int) $row->getAttribute('player_count'),
                'mission' => $row->getAttribute('mission'),
                'tracking' => $row->getAttribute('recap_complete') ? 'complete' : 'partial',
                'matches' => (int) $row->getAttribute('matches'),
                'cult_wins' => (int) $row->getAttribute('cult_wins'),
                'cult_win_percent' => round(100 * $row->getAttribute('cult_wins') / $row->getAttribute('matches'), 1),
                'average_nights' => round((float) $row->getAttribute('average_nights'), 1),
                'average_seconds' => $row->getAttribute('average_seconds') === null ? null : round((float) $row->getAttribute('average_seconds'), 1),
                'missed_night_actions' => (int) $row->getAttribute('missed_night_actions'),
                'missed_votes' => (int) $row->getAttribute('missed_votes'),
            ])->all();

        if ($this->option('json')) {
            $this->line(json_encode($groups, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif ($groups === []) {
            $this->info('No completed matches recorded yet.');
        } else {
            $this->table(['Rules', 'Players', 'Mission', 'Tracking', 'Matches', 'Cult wins', 'Cult win %', 'Avg nights', 'Avg seconds', 'Missed night actions', 'Missed votes'], $groups);
        }

        return self::SUCCESS;
    }
}
