<?php

namespace App\Game;

use Illuminate\Support\Str;

class CurseEngine
{
    public function level(int $tokens, int $threshold): int
    {
        return min(3, 1 + intdiv(max(0, $tokens) * 3, max(1, $threshold)));
    }

    /** @return list<string> */
    public function availableTypes(int $tokens, int $threshold): array
    {
        return $this->level($tokens, $threshold) === 3 ? ['puzzle', 'mist', 'misdirection'] : ['puzzle', 'mist'];
    }

    /** @return array<string, mixed> */
    public function create(int $tokens, int $threshold, int $day, string $type = 'puzzle'): array
    {
        $level = $this->level($tokens, $threshold);
        if (! in_array($type, $this->availableTypes($tokens, $threshold), true)) {
            throw new \InvalidArgumentException('Choose an unlocked curse type.');
        }
        $puzzles = ['rings', 'towers'];

        return ['id' => (string) Str::uuid(), 'type' => $type, 'level' => $level, 'day' => $day,
            'stage' => 1, 'stages' => 4 - $level,
            ...$this->challenge(match ($type) {
                'mist' => 'lanterns',
                'misdirection' => 'rings',
                default => $puzzles[array_rand($puzzles)],
            }, $level)];
    }

    /** Advance only after the current seal has been checked by MatchEngine.
     * @param  array<string, mixed>  $curse
     * @return array<string, mixed>|null
     */
    public function advance(array $curse): ?array
    {
        if (($curse['stage'] ?? 1) >= ($curse['stages'] ?? 1)) {
            return null;
        }
        $kind = match ($curse['type']) {
            'mist' => 'lanterns',
            'misdirection' => 'rings',
            default => $curse['challenge']['kind'] === 'rings' ? 'towers' : 'rings',
        };

        return [...$curse, 'id' => (string) Str::uuid(), 'stage' => $curse['stage'] + 1,
            ...$this->challenge($kind, $curse['level'])];
    }

    /** Generate new content and opaque option IDs for every affliction.
     * @return array<string, mixed>
     */
    public function challenge(string $kind, int $level): array
    {
        $level = max(1, min(3, $level));
        if (in_array($kind, ['rings', 'towers', 'lanterns'], true)) {
            return $this->spatialChallenge($kind, $level);
        }
        $size = 3 + $level;
        $runes = ['ASH', 'EYE', 'TIDE', 'BONE', 'MOON', 'VOID', 'SALT', 'STAR', 'ROOT', 'MOTH'];
        shuffle($runes);
        $clues = [];
        $labels = [];
        $answers = [];
        switch ($kind) {
            case 'cipher':
                $title = 'The drowned alphabet';
                $instruction = 'Decode each pair with the key, add its two values, then subtract the toll. Enter each result in inscription order.';
                $numbers = range(3, 15);
                shuffle($numbers);
                $key = array_combine(array_slice($runes, 0, $size + 2), array_slice($numbers, 0, $size + 2));
                foreach ($key as $rune => $number) {
                    $clues[] = $rune.' = '.$number;
                }
                $toll = random_int(1, 4 + $level);
                $clues[] = 'Toll: '.$toll;
                $keys = array_keys($key);
                for ($i = 0; $i < $size; $i++) {
                    shuffle($keys);
                    $clues[] = 'Seal '.($i + 1).': '.$keys[0].' + '.$keys[1];
                    $answers[] = (string) ($key[$keys[0]] + $key[$keys[1]] - $toll);
                }
                $labels = $this->answerOptions($answers, 8 + $level * 2);
                break;
            case 'order':
                $title = 'The binding chain';
                $instruction = 'Place every rune once, from first to last. Count positions carefully: two places after means one rune between.';
                $answers = array_slice($runes, 0, $size + 2);
                $clues[] = $answers[0].' is first.';
                for ($i = 1; $i < count($answers); $i++) {
                    $distance = random_int(1, min($i, $level + 1));
                    $previous = $i - $distance;
                    $clues[] = random_int(0, 1) === 0
                        ? $answers[$i].' is '.$distance.' places after '.$answers[$previous].'.'
                        : $answers[$previous].' is '.$distance.' places before '.$answers[$i].'.';
                }
                shuffle($clues);
                $labels = $answers;
                break;
            case 'missing':
                $title = 'The broken tide';
                $instruction = 'Two increasing sequences are woven together: positions 1, 3, 5… follow one fixed step; positions 2, 4, 6… follow another. Fill every gap from left to right.';
                $starts = [random_int(2, 15), random_int(20, 35)];
                $steps = [random_int(3, 7), random_int(8, 12)];
                $length = 8 + 2 * $level;
                $holes = range(4, $length - 1);
                shuffle($holes);
                $holes = array_slice($holes, 0, 2 + $level);
                $sequence = [];
                for ($i = 0; $i < $length; $i++) {
                    $value = (string) ($starts[$i % 2] + intdiv($i, 2) * $steps[$i % 2]);
                    if (in_array($i, $holes, true)) {
                        $answers[] = $value;
                        $sequence[] = '?';
                    } else {
                        $sequence[] = $value;
                    }
                }
                $clues[] = implode(' / ', $sequence);
                $labels = $this->answerOptions($answers, 8 + 2 * $level);
                break;
            case 'arithmetic':
                $title = 'The price of passage';
                $instruction = 'Use the prices to total each boat’s cargo, then subtract its debt. Enter the payments in boat order.';
                $goods = array_slice($runes, 0, 3);
                $prices = [random_int(3, 6), random_int(7, 10), random_int(11, 14)];
                foreach ($goods as $index => $good) {
                    $clues[] = $good.' costs '.$prices[$index].' per offering.';
                }
                for ($i = 0; $i < 2 + $level; $i++) {
                    $counts = [random_int(2, 3 + $level), random_int(2, 3 + $level)];
                    $first = $i % 3;
                    $second = ($i + 1) % 3;
                    $debt = random_int(3, 8 + $level * 2);
                    $clues[] = 'Boat '.($i + 1).': '.$counts[0].' '.$goods[$first].' + '.$counts[1].' '.$goods[$second].'; debt '.$debt.'.';
                    $answers[] = (string) ($counts[0] * $prices[$first] + $counts[1] * $prices[$second] - $debt);
                }
                $labels = $this->answerOptions($answers, 8 + 2 * $level);
                break;
            case 'odd':
                $title = 'The false sigil';
                $instruction = 'Each inscription must follow the ring forward, starting at any rune and wrapping around. Select every broken inscription by its number, from top to bottom.';
                $ring = array_slice($runes, 0, $size + 1);
                $clues[] = 'Ring: '.implode(' / ', $ring).' / '.$ring[0];
                $rows = 4 + 2 * $level;
                $broken = range(0, $rows - 1);
                shuffle($broken);
                $broken = array_slice($broken, 0, $level + 1);
                for ($i = 0; $i < $rows; $i++) {
                    $offset = random_int(0, count($ring) - 1);
                    $row = [...array_slice($ring, $offset), ...array_slice($ring, 0, $offset)];
                    if (in_array($i, $broken, true)) {
                        $swap = random_int(0, count($row) - 2);
                        [$row[$swap], $row[$swap + 1]] = [$row[$swap + 1], $row[$swap]];
                        $answers[] = (string) ($i + 1);
                    }
                    $label = (string) ($i + 1);
                    $labels[] = $label;
                    $clues[] = $label.': '.implode(' / ', $row);
                }
                break;
            case 'reverse':
                $title = 'The mirror below';
                $instruction = 'Ignore whispers marked *. Read the remaining runes from last to first, replacing each with its mirror partner.';
                $labels = array_slice($runes, 0, 6 + ($level > 1 ? 2 : 0) + ($level > 2 ? 2 : 0));
                $mirror = [];
                for ($i = 0; $i < count($labels); $i += 2) {
                    $mirror[$labels[$i]] = $labels[$i + 1];
                    $mirror[$labels[$i + 1]] = $labels[$i];
                    $clues[] = $labels[$i].' mirrors '.$labels[$i + 1].'.';
                }
                $whisper = [];
                for ($i = 0; $i < 3 + 2 * $level; $i++) {
                    $rune = $labels[random_int(0, count($labels) - 1)];
                    $whisper[] = $rune;
                    $answers[] = $mirror[$rune] ?? throw new \LogicException('Every whisper must have a mirror partner.');
                }
                for ($i = 0; $i < $level; $i++) {
                    array_splice($whisper, random_int(0, count($whisper)), 0, [$labels[random_int(0, count($labels) - 1)].'*']);
                }
                $clues[] = 'Whisper: '.implode(' / ', $whisper);
                $answers = array_reverse($answers);
                break;
            default:
                $kind = 'focus';
                $title = 'Find the shore';
                $instruction = 'Clear your head: touch every numbered anchor from lowest to highest. Ignore the whispers.';
                $numbers = range(1, 40 + 20 * $level);
                shuffle($numbers);
                $numbers = array_slice($numbers, 0, 4 + 2 * $level);
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

    /** Generate physical clues; keep the checked answer exclusively on the server.
     * @return array<string, mixed>
     */
    private function spatialChallenge(string $kind, int $level): array
    {
        $runes = ['ASH', 'EYE', 'TIDE', 'BONE', 'MOON', 'VOID', 'SALT', 'STAR', 'ROOT', 'MOTH'];
        shuffle($runes);
        $options = [];
        $solution = [];
        $scene = ['kind' => $kind, 'difficulty' => $level, 'guided' => $level === 1];

        if ($kind === 'rings') {
            $title = 'The astral lock';
            $instruction = 'Rotate each ring until its glowing notch points to the north beacon. Every touch turns a ring one quarter clockwise.';
            $clues = ['Align every notch with NORTH, then break the curse. Rings are numbered from the inside out.'];
            $scene['rings'] = [];
            for ($ring = 0; $ring < [1 => 2, 2 => 3, 3 => 5][$level]; $ring++) {
                $start = $level === 1 ? 3 : random_int($level === 2 ? 2 : 1, 3);
                $ids = [];
                for ($turn = 0; $turn < 4; $turn++) {
                    $id = (string) Str::uuid();
                    $ids[] = $id;
                    $options[] = ['id' => $id, 'label' => 'Ring '.($ring + 1).' · '.$turn.' turns'];
                }
                $scene['rings'][] = ['label' => 'Ring '.($ring + 1), 'start' => $start, 'options' => $ids];
                $solution[] = $ids[(4 - $start) % 4];
            }
        } else {
            if ($kind === 'towers') {
                $title = 'The sunken spires';
                $instruction = 'Wake the stone towers from shortest to tallest. Turn the scene to compare their heights, then touch each tower in order.';
                $clues = ['Judge the height of the stone, not how high its label appears on your screen. Each tower is used once.'];
                $labels = array_slice($runes, 0, [1 => 3, 2 => 4, 3 => 6][$level]);
            } else {
                $title = 'Lanterns in the mist';
                $instruction = $level === 1
                    ? 'Light lantern 1, then 2, then 3. Follow the numbers, not their heights. Each light pushes back the mist.'
                    : 'Light the lanterns by number, smallest number first. Ignore words; lantern height does not matter. Each light pushes back the mist.';
                $clues = ['Orbit the scene to find the lanterns. Follow their numbers, not their heights.'];
                $numbers = range(1, [1 => 3, 2 => 20, 3 => 90][$level]);
                if ($level > 1) {
                    shuffle($numbers);
                }
                $labels = [...array_map(strval(...), array_slice($numbers, 0, [1 => 3, 2 => 5, 3 => 8][$level])), ...array_slice($runes, 0, $level - 1)];
            }
            shuffle($labels);
            $heights = range(0, count($labels) - 1);
            shuffle($heights);
            $scene['objects'] = [];
            $ranked = [];
            foreach ($labels as $index => $label) {
                $id = (string) Str::uuid();
                $options[] = ['id' => $id, 'label' => $label];
                $angle = 2 * M_PI * $index / count($labels);
                $radius = $index % 2 === 0 ? 2.8 : 2.0;
                $height = $kind === 'towers'
                    ? round(0.6 + $heights[$index] * [1 => 0.85, 2 => 0.6, 3 => 0.35][$level], 2)
                    : round(0.9 + $heights[$index] * [1 => 0, 2 => 0.12, 3 => 0.17][$level], 2);
                // Short lanterns in separated rows keep numbers readable on phones.
                $columns = $level === 3 ? 4 : 3;
                $rows = (int) ceil(count($labels) / $columns);
                $scene['objects'][] = ['option_id' => $id,
                    'x' => $kind === 'lanterns' ? ($index % $columns - ($columns - 1) / 2) * 1.6 : round(cos($angle) * $radius, 3),
                    'z' => $kind === 'lanterns' ? (intdiv($index, $columns) - ($rows - 1) / 2) * 1.8 : round(sin($angle) * $radius, 3),
                    'height' => $height];
                if ($kind === 'towers' || is_numeric($label)) {
                    $ranked[] = ['id' => $id, 'rank' => $kind === 'towers' ? $height : (int) $label];
                }
            }
            usort($ranked, fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);
            $solution = array_column($ranked, 'id');
        }

        return ['challenge' => ['kind' => $kind, 'title' => $title, 'instruction' => $instruction,
            'clues' => $clues, 'options' => $options, 'answer_length' => count($solution), 'scene' => $scene],
            'solution' => $solution];
    }

    /** @param list<string> $answers
     * @return list<string>
     */
    private function answerOptions(array $answers, int $count): array
    {
        $options = array_values(array_unique(array_map(intval(...), $answers)));
        while (count($options) < $count) {
            $answer = (int) $answers[array_rand($answers)];
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
        return $curse === null ? null : array_intersect_key($curse, array_flip(['id', 'type', 'level', 'day', 'challenge', 'stage', 'stages']));
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
