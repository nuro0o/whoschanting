<?php

namespace App\Game;

class ProfanityFilter
{
    private ?string $pattern = null;

    private ?string $namePattern = null;

    public function containsInName(string $name): bool
    {
        $this->pattern ??= $this->pattern();
        $this->namePattern ??= $this->pattern('name_fragments', false);

        return preg_match($this->pattern, $name) === 1 || preg_match($this->namePattern, $name) === 1;
    }

    /** @param list<string> $taken */
    public function playerName(string $name, array $taken = [], ?string $seed = null): string
    {
        $name = trim($name);

        return $this->containsInName($name) ? (new VillageNames)->pick($taken, $seed) : $name;
    }

    public function mask(string $text): string
    {
        $this->pattern ??= $this->pattern();

        return preg_replace_callback($this->pattern, fn (array $match): string => str_repeat('*', mb_strlen($match[0])), $text) ?? $text;
    }

    private function pattern(string $list = 'words', bool $boundaries = true): string
    {
        /** @var list<string> $words */
        $words = config('moderation.'.$list, []);
        usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $alternatives = [];
        $substitutions = ['a' => '@4', 'e' => '3', 'i' => '1!', 'o' => '0', 's' => '$5', 't' => '7', 'l' => '1'];
        foreach ($words as $word) {
            $letters = mb_str_split(mb_strtolower(trim($word)));
            if ($letters === []) {
                continue;
            }
            $parts = [];
            foreach ($letters as $letter) {
                $variants = $letter.($substitutions[$letter] ?? '');
                if ($letter >= 'a' && $letter <= 'z') {
                    $variants .= mb_chr(mb_ord($letter) + 0xFEE0);
                }
                $parts[] = '['.preg_quote($variants, '~').']+';
            }
            // Unicode boundaries avoid censoring parts of ordinary names.
            $alternatives[] = implode('[\p{Z}\p{P}\p{Cf}\p{M}\s]*', $parts);
        }

        $pattern = '(?:'.implode('|', $alternatives).')';
        if ($boundaries) {
            $pattern = '(?<![\p{L}\p{N}\p{M}])'.$pattern.'(?![\p{L}\p{N}\p{M}])';
        }

        return $alternatives === [] ? '~(?!)~u' : '~'.$pattern.'~iu';
    }

    /** Mask existing room text too, without changing identifiers or game data.
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function roomText(array $state): array
    {
        $replacements = [];
        $taken = array_column($state['players'], 'name');
        foreach ($state['players'] as $id => &$player) {
            $old = $player['name'];
            $player['name'] = $this->playerName($old, $taken, (string) $id);
            if ($player['name'] !== $old) {
                $replacements[$old] = $player['name'];
                $taken[] = $player['name'];
            }
        }
        unset($player);
        array_walk_recursive($state, function (mixed &$value, string|int $key) use ($replacements): void {
            if ($key === 'name' && is_string($value)) {
                $value = $replacements[$value] ?? $this->mask($value);
            } elseif ($key === 'body' && is_string($value)) {
                $value = $this->mask($value);
            }
        });
        foreach ($state['players'] as &$player) {
            foreach ($player['results'] ?? [] as $index => $result) {
                foreach (['target', 'visited_target'] as $key) {
                    if (is_string($result[$key] ?? null)) {
                        $player['results'][$index][$key] = $this->mask(strtr($result[$key], $replacements));
                    }
                }
            }
            if (is_string($player['curse_notice'] ?? null)) {
                $player['curse_notice'] = $this->mask(strtr($player['curse_notice'], $replacements));
            }
        }
        unset($player);
        $state['log'] = array_map(fn (string $entry): string => $this->mask(strtr($entry, $replacements)), $state['log']);

        return $state;
    }
}
