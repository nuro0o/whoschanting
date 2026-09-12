<?php

namespace App\Game;

use App\Models\GameMatch;
use App\Models\MatchReward;
use App\Models\PlayerProfile;
use App\Models\PlayerSeason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountProgression
{
    public function __construct(private CharacterCreator $creator = new CharacterCreator) {}

    /** @return array<string, mixed> */
    public function season(?CarbonImmutable $at = null): array
    {
        $at = ($at ?? CarbonImmutable::now())->utc();
        $start = $at->startOfQuarter();

        return ['id' => $at->year.'-Q'.$at->quarter, 'name' => 'Season '.$at->quarter.' · '.$at->year,
            'starts_at' => $start->toISOString(), 'ends_at' => $start->addMonths(3)->toISOString()];
    }

    public function level(int $xp): int
    {
        return (int) floor((1 + sqrt(1 + 8 * max(0, $xp) / config('progression.level_step'))) / 2);
    }

    /** Original ordered cast, used for random assignments and legacy portrait fallbacks.
     * @return list<string>
     */
    public function starterCharacterIds(): array
    {
        return array_values(array_filter(array_column(config('game.characters'), 'id'),
            fn (string $id): bool => ! array_key_exists($id, config('progression.character_unlocks', []))));
    }

    /** @param array<string, string> $achievements
     * @return list<array<string, mixed>>
     */
    private function characters(int $level, array $achievements, bool $account): array
    {
        return array_map(function (array $character) use ($level, $achievements, $account): array {
            $unlock = config('progression.character_unlocks.'.$character['id']);
            if ($unlock === null) {
                return [...$character, 'unlocked' => true, 'requirement' => 'Available from the start'];
            }
            $unlocked = $account && (isset($unlock['achievement'])
                ? isset($achievements[$unlock['achievement']]) : $level >= $unlock['level']);
            $requirement = isset($unlock['achievement'])
                ? 'Earn '.config('progression.achievements.'.$unlock['achievement'].'.name').' — '.($unlock['description'] ?? config('progression.achievements.'.$unlock['achievement'].'.description'))
                : 'Reach level '.$unlock['level'].' ('.number_format((int) (config('progression.level_step') * $unlock['level'] * ($unlock['level'] - 1) / 2)).' lifetime XP)';

            return [...$character, 'unlocked' => $unlocked, 'requirement' => $requirement];
        }, array_values(config('game.characters')));
    }

    /** @return list<array<string, mixed>> */
    public function characterCatalog(?int $userId = null): array
    {
        $eligible = $userId !== null && User::whereKey($userId)->whereNotNull('email_verified_at')->exists();
        $profile = $eligible ? PlayerProfile::find($userId) : null;

        $level = $this->level($profile->xp ?? 0);
        $characters = $this->characters($level, $profile->achievements ?? [], $eligible);
        $recipe = $this->creator->saved($profile?->customization['creator'] ?? null, $level);
        if (config('character_creator.enabled') && $eligible && $recipe !== null) {
            $characters[] = ['id' => 'custom', 'name' => 'Your creation', 'unlocked' => true,
                'requirement' => 'Created in the mirror', 'creator' => $recipe];
        }

        return $characters;
    }

    public function canUseCharacter(?int $userId, string $character): bool
    {
        return (bool) (collect($this->characterCatalog($userId))->firstWhere('id', $character)['unlocked'] ?? false);
    }

    public function assertCharacterUnlocked(?int $userId, string $character): void
    {
        if (! $this->canUseCharacter($userId, $character)) {
            throw ValidationException::withMessages(['character' => 'Choose an unlocked village character.']);
        }
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return ['xp' => 0, 'matches' => 0, 'wins' => 0, 'town_wins' => 0, 'cult_wins' => 0, 'roles_played' => [], 'achievements' => [],
            'customization' => ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'background' => 'plain', 'character' => null, 'creator' => null]];
    }

    private function lockProfile(int $userId): PlayerProfile
    {
        $defaults = $this->defaults();
        PlayerProfile::query()->insertOrIgnore(['user_id' => $userId, ...array_replace($defaults, [
            'roles_played' => json_encode($defaults['roles_played']), 'achievements' => json_encode($defaults['achievements']),
            'customization' => json_encode($defaults['customization']), 'created_at' => now(), 'updated_at' => now(),
        ])]);

        return PlayerProfile::whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    /** Called inside the locked room/archive transaction. Account locks are always ordered.
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, mixed>>
     */
    public function award(GameMatch $match, array $state): array
    {
        if (! $match->recap_complete || ! isset($state['match_id'])) {
            return [];
        }
        $accounts = [];
        foreach ($state['players'] as $seatId => $player) {
            if (isset($player['user_id'])) {
                $accounts[$player['user_id']] ??= $seatId;
            }
        }
        ksort($accounts);
        $rewards = [];
        foreach ($accounts as $userId => $seatId) {
            if (! User::whereKey($userId)->whereNotNull('email_verified_at')->exists()) {
                continue;
            }
            $night = 0;
            $votes = 0;
            $opportunities = 0;
            $oath = false;
            foreach ($state['rounds'] ?? [] as $round) {
                foreach ($round['night']['actions'] ?? [] as $action) {
                    if ($action['player_id'] !== $seatId) {
                        continue;
                    }
                    $opportunities++;
                    if ($action['submitted']) {
                        $night++;
                    }
                }
                foreach ($round['vote']['ballots'] ?? [] as $ballot) {
                    if ($ballot['player_id'] !== $seatId) {
                        continue;
                    }
                    $opportunities++;
                    if ($ballot['submitted']) {
                        $votes++;
                    }
                    $oath = $oath || ($ballot['oath_kept'] ?? false);
                }
            }
            if ($night === 0 || $votes === 0) {
                continue;
            }
            $profile = $this->lockProfile($userId);
            $existing = MatchReward::where('user_id', $userId)->where('match_id', $match->id)->first();
            if ($existing !== null) {
                $rewards[$seatId] = $existing->data;

                continue;
            }
            $finishedAt = CarbonImmutable::parse($match->finished_at);
            $season = $this->season($finishedAt);
            $seasonRecord = PlayerSeason::firstOrCreate(['user_id' => $userId, 'season_id' => $season['id']]);
            $won = $state['players'][$seatId]['alignment'] === $state['winner'];
            $xp = (int) config('progression.match_xp') + ($won ? (int) config('progression.win_xp') : 0)
                + ($night + $votes === $opportunities ? (int) config('progression.attendance_xp') : 0);
            $before = $this->level($profile->xp);
            $charactersBefore = array_column(array_filter($this->characters($before, $profile->achievements, true), fn (array $item): bool => $item['unlocked']), 'id');
            $profile->xp += $xp;
            $profile->matches++;
            $profile->wins += (int) $won;
            if ($won) {
                $field = $state['winner'] === 'town' ? 'town_wins' : 'cult_wins';
                $profile->$field++;
            }
            $profile->roles_played = array_values(array_unique([...$profile->roles_played, $state['players'][$seatId]['role']]));
            $seasonRecord->xp += $xp;
            $seasonRecord->matches++;
            $seasonRecord->wins += (int) $won;
            $seasonRecord->save();
            $earned = $profile->achievements;
            $new = [];
            foreach (config('progression.achievements') as $key => $definition) {
                $current = match ($definition['stat']) {
                    'roles' => count($profile->roles_played), 'oath' => (int) $oath,
                    'season_matches' => $seasonRecord->matches, default => $profile->{$definition['stat']},
                };
                if (! isset($earned[$key]) && $current >= $definition['target']) {
                    $earned[$key] = $finishedAt->toISOString();
                    $new[] = $key;
                }
            }
            $profile->achievements = $earned;
            $profile->save();
            $charactersAfter = array_column(array_filter($this->characters($this->level($profile->xp), $profile->achievements, true), fn (array $item): bool => $item['unlocked']), 'id');
            $reward = ['match_id' => $match->id, 'season_id' => $season['id'], 'xp' => $xp, 'won' => $won,
                'characters' => array_values(array_diff($charactersAfter, $charactersBefore)),
                'earned_at' => $finishedAt->toISOString(), 'achievements' => $new, 'level_before' => $before, 'level_after' => $this->level($profile->xp)];
            MatchReward::create(['user_id' => $userId, 'match_id' => $match->id, 'data' => $reward]);
            $rewards[$seatId] = $reward;
        }

        return $rewards;
    }

    /** @return array<string, mixed> */
    public function view(int $userId): array
    {
        $profile = array_replace($this->defaults(), PlayerProfile::find($userId)?->toArray() ?? []);
        $level = $this->level($profile['xp']);
        $characters = $this->characterCatalog($userId);
        $equipped = array_replace($this->defaults()['customization'], $profile['customization']);
        $equipped['creator'] = config('character_creator.enabled') ? $this->creator->saved($equipped['creator'], $level) : null;
        if ($equipped['character'] !== null && ! (collect($characters)->firstWhere('id', $equipped['character'])['unlocked'] ?? false)) {
            $equipped['character'] = null;
        }
        $season = $this->season();
        $seasons = PlayerSeason::where('user_id', $userId)->orderByDesc('season_id')->get();
        $current = $seasons->firstWhere('season_id', $season['id']);
        $bestXp = (int) ($seasons->max('xp') ?? 0);
        $xp = (int) ($current->xp ?? 0);
        $tier = $this->tier($xp);
        $tiers = array_map(fn (array $t): array => [...$t, 'unlocked' => $xp >= $t['xp']], config('progression.season_tiers'));
        $next = array_values(array_filter($tiers, fn (array $t): bool => ! $t['unlocked']))[0]['xp'] ?? null;
        $achievements = [];
        foreach (config('progression.achievements') as $id => $definition) {
            $earned = $profile['achievements'][$id] ?? null;
            $progress = match ($definition['stat']) {
                'roles' => count($profile['roles_played']), 'oath' => $earned ? 1 : 0,
                'season_matches' => (int) ($seasons->max('matches') ?? 0), default => $profile[$definition['stat']],
            };
            $achievements[] = ['id' => $id, 'name' => $definition['name'], 'description' => $definition['description'],
                'current' => $earned ? $definition['target'] : min($progress, $definition['target']), 'target' => $definition['target'], 'earned_at' => $earned];
        }

        return [
            'profile' => [...array_intersect_key($profile, array_flip(['xp', 'matches', 'wins', 'town_wins', 'cult_wins', 'roles_played'])),
                'level' => $level, 'level_xp' => $profile['xp'] - (int) (config('progression.level_step') * $level * ($level - 1) / 2),
                'next_level_xp' => config('progression.level_step') * $level, 'equipped' => $equipped],
            'characters' => $characters,
            ...(config('character_creator.enabled') ? ['creator' => $this->creator->catalog($level)] : []),
            'season' => [...$season, 'xp' => $xp, 'matches' => $current->matches ?? 0, 'wins' => $current->wins ?? 0,
                'tier' => ['id' => $tier['id'], 'name' => $tier['name']], 'next_tier_xp' => $next, 'tiers' => $tiers,
                'history' => $seasons->filter(fn (PlayerSeason $s): bool => $s->season_id !== $season['id'])->map(fn (PlayerSeason $s): array => [
                    'id' => $s->season_id, 'xp' => $s->xp, 'matches' => $s->matches, 'wins' => $s->wins, 'tier' => $this->tier($s->xp)['name'],
                ])->values()->all()],
            'achievements' => $achievements, 'cosmetics' => $this->catalog($level, $profile['achievements'], $bestXp),
            'recent_rewards' => MatchReward::where('user_id', $userId)->latest('id')->limit(10)->pluck('data')->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function tier(int $xp): array
    {
        $tier = config('progression.season_tiers')[0];
        foreach (config('progression.season_tiers') as $candidate) {
            if ($xp >= $candidate['xp']) {
                $tier = $candidate;
            }
        }

        return $tier;
    }

    /** @param array<string, string> $achievements
     * @return array<string, list<array<string, mixed>>>
     */
    private function catalog(int $level, array $achievements, int $seasonXp): array
    {
        $catalog = [];
        foreach (config('progression.cosmetics') as $category => $items) {
            $catalog[$category] = [];
            foreach ($items as $item) {
                $unlocked = isset($item['achievement']) ? isset($achievements[$item['achievement']])
                    : (isset($item['season_xp']) ? $seasonXp >= $item['season_xp'] : $level >= $item['level']);
                $requirement = isset($item['achievement']) ? 'Earn '.config('progression.achievements.'.$item['achievement'].'.name')
                    : (isset($item['season_xp']) ? 'Earn '.$item['season_xp'].' XP in any one season' : ($item['level'] === 1 ? 'Available to every account' : 'Reach level '.$item['level']));

                $catalog[$category][] = ['id' => $item['id'], 'name' => $item['name'], 'unlocked' => $unlocked, 'requirement' => $requirement];
            }
        }

        return $catalog;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function customize(int $userId, array $input): array
    {
        if (! config('character_creator.enabled') && (($input['character'] ?? null) === 'custom' || ($input['creator'] ?? null) !== null)) {
            throw ValidationException::withMessages(['character' => 'Character creation is temporarily unavailable. Choose a village character.']);
        }

        return DB::transaction(function () use ($userId, $input): array {
            $profile = $this->lockProfile($userId);
            $catalog = $this->catalog($this->level($profile->xp), $profile->achievements,
                (int) (PlayerSeason::where('user_id', $userId)->max('xp') ?? 0));
            $equipped = array_replace($this->defaults()['customization'], $profile->customization);
            foreach (['title' => 'titles', 'frame' => 'frames', 'accent' => 'accents', 'background' => 'backgrounds'] as $field => $category) {
                // Older clients omit backgrounds; keep the player's saved choice.
                $value = $field === 'background' && ! array_key_exists($field, $input) ? $equipped[$field] : ($input[$field] ?? null);
                $item = collect($catalog[$category])->firstWhere('id', $value);
                if ($item === null || ! $item['unlocked']) {
                    throw ValidationException::withMessages([$field => 'Choose an unlocked '.$field.'.']);
                }
                $equipped[$field] = $item['id'];
            }
            // While paused, retain stored designs even when the wardrobe sends null.
            if (config('character_creator.enabled')) {
                if (array_key_exists('creator', $input)) {
                    $equipped['creator'] = $input['creator'] === null ? null : $this->creator->validate($input['creator'], $this->level($profile->xp));
                } else {
                    $equipped['creator'] = $this->creator->saved($equipped['creator'], $this->level($profile->xp));
                }
            }
            $character = $input['character'] ?? null;
            if ($character === 'custom') {
                if ($equipped['creator'] === null || ! User::whereKey($userId)->whereNotNull('email_verified_at')->exists()) {
                    throw ValidationException::withMessages(['creator' => 'Create and save your villager before wearing it.']);
                }
            } elseif ($character !== null) {
                $this->assertCharacterUnlocked($userId, $character);
            }
            $equipped['character'] = $character;
            $profile->customization = $equipped;
            $profile->save();

            return $this->view($userId);
        });
    }

    public function rememberCharacter(int $userId, string $character): void
    {
        DB::transaction(function () use ($userId, $character): void {
            $this->assertCharacterUnlocked($userId, $character);
            $profile = $this->lockProfile($userId);
            $profile->customization = [...$profile->customization, 'character' => $character];
            $profile->save();
        });
    }

    public function preferredCharacter(int $userId): ?string
    {
        $character = PlayerProfile::find($userId)?->customization['character'] ?? null;

        return $character !== null && $this->canUseCharacter($userId, $character) ? $character : null;
    }

    /** Safe public allowlist; no account identifier or private statistics.
     * @return array<string, mixed>
     */
    public function appearance(int $userId, ?string $character = null): array
    {
        $profile = PlayerProfile::find($userId);
        $equipped = array_replace($this->defaults()['customization'], $profile->customization ?? []);
        $titleName = 'Newcomer';
        foreach (config('progression.cosmetics.titles') as $title) {
            if ($title['id'] === $equipped['title']) {
                $titleName = $title['name'];
            }
        }

        $appearance = ['level' => $this->level($profile->xp ?? 0), 'title' => $equipped['title'], 'title_name' => $titleName,
            'frame' => $equipped['frame'], 'accent' => $equipped['accent'], 'background' => $equipped['background']];
        if ($character === 'custom') {
            $recipe = $this->creator->saved($equipped['creator'], $appearance['level']);
            if ($recipe !== null) {
                $appearance['creator'] = $recipe;
            }
        }

        return $appearance;
    }
}
