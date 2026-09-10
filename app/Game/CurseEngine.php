<?php

namespace App\Game;

use Illuminate\Support\Str;

class CurseEngine
{
    public function level(int $tokens, int $threshold): int
    {
        return min(3, 1 + intdiv(max(0, $tokens) * 3, max(1, $threshold)));
    }

    /** @return array<string, mixed> */
    public function create(int $tokens, int $threshold, int $day): array
    {
        $level = $this->level($tokens, $threshold);
        $types = $level === 3 ? ['puzzle', 'mist', 'misdirection'] : ['puzzle', 'mist'];
        $type = $types[random_int(0, count($types) - 1)];
        $puzzles = ['cipher', 'order', 'missing', 'arithmetic', 'odd', 'reverse'];

        return ['id' => (string) Str::uuid(), 'type' => $type, 'level' => $level, 'day' => $day,
            ...($type === 'misdirection' ? ['challenge' => null, 'solution' => []]
                : $this->challenge($type === 'mist' ? 'focus' : $puzzles[random_int(0, 5)], $level))];
    }

    /** Generate new content and opaque option IDs for every affliction.
     * @return array<string, mixed>
     */
    public function challenge(string $kind, int $level): array
    {
        $size = 2 + $level;
        $runes = ['ASH', 'EYE', 'TIDE', 'BONE', 'MOON', 'VOID', 'SALT', 'STAR', 'ROOT', 'MOTH'];
        shuffle($runes);
        $clues = [];
        $labels = [];
        $answers = [];
        switch ($kind) {
            case 'cipher':
                $title = 'The drowned alphabet';
                $instruction = 'Decode the inscription. Choose the matching numbers in inscription order.';
                $numbers = range(1, 9);
                shuffle($numbers);
                $key = array_combine(array_slice($runes, 0, $size + 2), array_slice($numbers, 0, $size + 2));
                foreach ($key as $rune => $number) {
                    $clues[] = $rune.' = '.$number;
                    $labels[] = (string) $number;
                }
                $inscription = [];
                for ($i = 0; $i < $size; $i++) {
                    $rune = array_rand($key);
                    $inscription[] = $rune;
                    $answers[] = (string) $key[$rune];
                }
                $clues[] = 'Inscription: '.implode(' / ', $inscription);
                break;
            case 'order':
                $title = 'The binding chain';
                $instruction = 'Rebuild the chain from first to last using the clues.';
                $answers = array_slice($runes, 0, $size + 1);
                for ($i = 0; $i < count($answers) - 1; $i++) {
                    $clues[] = $answers[$i].' comes immediately before '.$answers[$i + 1].'.';
                }
                shuffle($clues);
                $labels = $answers;
                break;
            case 'missing':
                $title = 'The broken tide';
                $instruction = 'Find the missing number. Each step adds the same amount.';
                $start = random_int(1, 10 * $level);
                $step = random_int(2, 4 + 3 * $level);
                $hole = random_int(1, $size);
                $sequence = [];
                for ($i = 0; $i < $size + 2; $i++) {
                    $sequence[] = $i === $hole ? '?' : (string) ($start + $i * $step);
                }
                $clues[] = implode(' / ', $sequence);
                $answers = [(string) ($start + $hole * $step)];
                $labels = $this->numberOptions((int) $answers[0], $size + 2);
                break;
            case 'arithmetic':
                $title = 'The price of passage';
                $instruction = 'Pay the exact total. Add each offering, then subtract the debt.';
                $offerings = [];
                for ($i = 0; $i < $size; $i++) {
                    $offerings[] = random_int(1, 5 * $level);
                }
                $debt = random_int(1, min(array_sum($offerings) - 1, 5 * $level));
                $clues = ['Offerings: '.implode(' + ', $offerings), 'Debt: '.$debt];
                $answers = [(string) (array_sum($offerings) - $debt)];
                $labels = $this->numberOptions((int) $answers[0], $size + 2);
                break;
            case 'odd':
                $title = 'The false sigil';
                $instruction = 'Choose the only inscription that differs from the others.';
                $base = implode('-', array_slice($runes, 0, $size));
                $changed = array_slice($runes, 0, $size);
                $changed[random_int(0, $size - 1)] = $runes[$size];
                $odd = random_int(0, $size + 1);
                for ($i = 0; $i < $size + 2; $i++) {
                    $label = (string) ($i + 1);
                    $labels[] = $label;
                    $clues[] = $label.': '.($i === $odd ? implode('-', $changed) : $base);
                }
                $answers = [(string) ($odd + 1)];
                break;
            case 'reverse':
                $title = 'The mirror below';
                $instruction = 'Return the whisper in reverse order, from last rune to first.';
                $whisper = [];
                for ($i = 0; $i < $size; $i++) {
                    $whisper[] = $runes[random_int(0, $size)];
                }
                $clues = [implode(' / ', $whisper)];
                $answers = array_reverse($whisper);
                $labels = array_slice($runes, 0, $size + 2);
                break;
            default:
                $kind = 'focus';
                $title = 'Find the shore';
                $instruction = 'Clear your head: touch every numbered anchor from lowest to highest. Ignore the whispers.';
                $numbers = range(1, 9 * $level);
                shuffle($numbers);
                $numbers = array_slice($numbers, 0, $size + 1);
                sort($numbers);
                $answers = array_map(strval(...), $numbers);
                $labels = [...$answers, ...array_slice($runes, 0, $level + 1)];
                $clues = ['Follow the numbers. The words are only echoes.'];
                break;
        }
        shuffle($labels);
        $options = array_map(fn (string $label): array => ['id' => (string) Str::uuid(), 'label' => $label], $labels);
        $ids = array_column($options, 'id', 'label');

        return ['challenge' => ['kind' => $kind, 'title' => $title, 'instruction' => $instruction,
            'clues' => $clues, 'options' => $options, 'answer_length' => count($answers)],
            'solution' => array_map(fn (string $answer): string => $ids[$answer] ?? throw new \LogicException('A curse answer must have a matching option.'), $answers)];
    }

    /** @return list<string> */
    private function numberOptions(int $answer, int $count): array
    {
        $options = [$answer];
        while (count($options) < $count) {
            $candidate = max(0, $answer + random_int(-$count, $count));
            if (! in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }

        return array_map(strval(...), $options);
    }

    /** @param array<string, mixed>|null $curse
     * @return array<string, mixed>|null
     */
    public function view(?array $curse): ?array
    {
        return $curse === null ? null : array_intersect_key($curse, array_flip(['id', 'type', 'level', 'day', 'challenge']));
    }

    public function garble(string $body, string $salt): string
    {
        $syllables = ['ka', 'zul', 'oth', 'ia', 'vyr', 'esh', 'nak', 'ul'];
        $hash = hash('sha256', $salt.$body);
        $index = 0;

        return preg_replace_callback('/[\p{L}\p{N}]+/u', function (array $word) use ($syllables, $hash, &$index): string {
            $result = '';
            for ($i = 0; $i < max(1, (int) ceil(mb_strlen($word[0]) / 3)); $i++) {
                $result .= $syllables[hexdec($hash[$index++ % 64]) % count($syllables)];
            }

            return $result;
        }, $body) ?? '';
    }
}
