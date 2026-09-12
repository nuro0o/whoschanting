<?php

namespace App\Game;

class ProfanityFilter
{
    private ?string $pattern = null;

    public function mask(string $text): string
    {
        $this->pattern ??= $this->pattern();

        return preg_replace_callback($this->pattern, fn (array $match): string => str_repeat('*', mb_strlen($match[0])), $text) ?? $text;
    }

    private function pattern(): string
    {
        /** @var list<string> $words */
        $words = config('moderation.words', []);
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

        return $alternatives === [] ? '~(?!)~u' : '~(?<![\p{L}\p{N}\p{M}])(?:'.implode('|', $alternatives).')(?![\p{L}\p{N}\p{M}])~iu';
    }

    /** Mask existing room text too, without changing identifiers or game data.
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function roomText(array $state): array
    {
        array_walk_recursive($state, function (mixed &$value, string|int $key): void {
            if (in_array($key, ['name', 'body'], true) && is_string($value)) {
                $value = $this->mask($value);
            }
        });
        foreach ($state['players'] as &$player) {
            foreach ($player['results'] ?? [] as $index => $result) {
                foreach (['target', 'visited_target'] as $key) {
                    if (is_string($result[$key] ?? null)) {
                        $player['results'][$index][$key] = $this->mask($result[$key]);
                    }
                }
            }
            if (is_string($player['curse_notice'] ?? null)) {
                $player['curse_notice'] = $this->mask($player['curse_notice']);
            }
        }
        unset($player);
        $state['log'] = array_map($this->mask(...), $state['log']);

        return $state;
    }
}
