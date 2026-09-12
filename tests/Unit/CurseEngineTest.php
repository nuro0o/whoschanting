<?php

namespace Tests\Unit;

use App\Game\CurseEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurseEngineTest extends TestCase
{
    public function test_ritual_tiers_and_late_curse_eligibility(): void
    {
        $engine = new CurseEngine;
        foreach ([[0, 6, 1], [1, 6, 1], [2, 6, 2], [3, 6, 2], [4, 6, 3], [6, 6, 3], [0, 0, 1]] as [$tokens, $goal, $tier]) {
            $this->assertSame($tier, $engine->level($tokens, $goal));
            $types = $tier === 3 ? ['puzzle', 'mist', 'misdirection'] : ['puzzle', 'mist'];
            $this->assertSame($types, $engine->availableTypes($tokens, $goal));
            $this->assertSame('puzzle', $engine->create($tokens, $goal, 1)['type']);
            foreach ($types as $type) {
                $curse = $engine->create($tokens, $goal, 1, $type);
                $this->assertSame($tier, $curse['level']);
                $this->assertSame($type, $curse['type']);
                $this->assertArrayNotHasKey('solution', $engine->view($curse));
                $this->assertContains($curse['challenge']['kind'], match ($type) {
                    'mist' => ['lanterns', 'focus', 'odd'],
                    'misdirection' => ['rings', 'reverse', 'odd'],
                    default => ['rings', 'towers', 'cipher'],
                });
                $this->assertNotEmpty($curse['set_name']);
                if (isset($curse['challenge']['scene'])) {
                    $this->assertSame($curse['challenge']['kind'], $curse['challenge']['scene']['kind']);
                }
            }
        }
    }

    public function test_misdirection_cannot_be_created_before_it_unlocks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CurseEngine)->create(3, 6, 1, 'misdirection');
    }

    /** @return iterable<string, array{string, int}> */
    public static function puzzles(): iterable
    {
        foreach (['cipher', 'order', 'missing', 'arithmetic', 'odd', 'reverse', 'focus'] as $kind) {
            foreach ([1, 2, 3] as $level) {
                yield $kind.'-'.$level => [$kind, $level];
            }
        }
    }

    #[DataProvider('puzzles')]
    public function test_generated_challenges_are_solvable_from_their_public_clues(string $kind, int $level): void
    {
        $engine = new CurseEngine;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $generated = $engine->challenge($kind, $level);
            $challenge = $generated['challenge'];
            $clues = $challenge['clues'];
            $options = array_column($challenge['options'], 'label', 'id');
            $actual = array_map(fn (string $id): string => $options[$id], $generated['solution']);
            $expected = [];
            switch ($kind) {
                case 'cipher':
                    $key = [];
                    foreach ($clues as $clue) {
                        if (preg_match('/^(\w+) = (\d+)$/', $clue, $match)) {
                            $key[$match[1]] = (int) $match[2];
                        } elseif (preg_match('/^Toll: (\d+)$/', $clue, $match)) {
                            $toll = (int) $match[1];
                        }
                    }
                    foreach ($clues as $clue) {
                        if (preg_match('/^Seal \d+: (\w+) \+ (\w+)$/', $clue, $match)) {
                            $expected[] = (string) ($key[$match[1]] + $key[$match[2]] - $toll);
                        }
                    }
                    break;
                case 'order':
                    $solutions = $this->ordersMatchingClues(array_values($options), $clues);
                    $this->assertCount(1, $solutions, 'The ordering clues must admit exactly one permutation.');
                    $expected = $solutions[0];
                    break;
                case 'missing':
                    $sequence = explode(' / ', $clues[0]);
                    $steps = [(int) $sequence[2] - (int) $sequence[0], (int) $sequence[3] - (int) $sequence[1]];
                    foreach ($sequence as $position => $value) {
                        $predicted = (string) ((int) $sequence[$position % 2] + intdiv($position, 2) * $steps[$position % 2]);
                        if ($value === '?') {
                            $expected[] = $predicted;
                        } else {
                            $this->assertSame($value, $predicted);
                        }
                    }
                    break;
                case 'arithmetic':
                    $prices = [];
                    foreach ($clues as $clue) {
                        if (preg_match('/^(\w+) costs (\d+) per offering\.$/', $clue, $match)) {
                            $prices[$match[1]] = (int) $match[2];
                        } elseif (preg_match('/^Boat \d+: (\d+) (\w+) \+ (\d+) (\w+); debt (\d+)\.$/', $clue, $match)) {
                            $expected[] = (string) ((int) $match[1] * $prices[$match[2]] + (int) $match[3] * $prices[$match[4]] - (int) $match[5]);
                        }
                    }
                    break;
                case 'odd':
                    $ring = explode(' / ', substr(array_shift($clues), strlen('Ring: ')));
                    $next = [];
                    for ($index = 0; $index < count($ring) - 1; $index++) {
                        $next[$ring[$index]] = $ring[$index + 1];
                    }
                    foreach ($clues as $clue) {
                        [$label, $inscription] = explode(': ', $clue);
                        $row = explode(' / ', $inscription);
                        foreach ($row as $index => $rune) {
                            if ($next[$rune] !== $row[($index + 1) % count($row)]) {
                                $expected[] = $label;
                                break;
                            }
                        }
                    }
                    break;
                case 'reverse':
                    $whisper = explode(' / ', substr(array_pop($clues), strlen('Whisper: ')));
                    $mirror = [];
                    foreach ($clues as $clue) {
                        [$left, $right] = explode(' mirrors ', rtrim($clue, '.'));
                        $mirror[$left] = $right;
                        $mirror[$right] = $left;
                    }
                    foreach ($whisper as $rune) {
                        if (! str_ends_with($rune, '*')) {
                            array_unshift($expected, $mirror[$rune]);
                        }
                    }
                    break;
                case 'focus':
                    $expected = array_values(array_filter($options, is_numeric(...)));
                    sort($expected, SORT_NUMERIC);
                    break;
            }
            $this->assertSame($expected, $actual);
            $this->assertCount(count($expected), $generated['solution']);
            $this->assertSame(count($expected), $challenge['answer_length']);
            $this->assertGreaterThanOrEqual(2, $challenge['answer_length'], 'Even level one requires multiple correct steps.');
            $this->assertLessThanOrEqual(12, $challenge['answer_length'], 'Answers must fit the HTTP validation limit.');
            $this->assertCount(count(array_unique($options)), $options, 'Option labels must be unambiguous.');
            $this->assertNotSame($challenge, $engine->challenge($kind, $level)['challenge']);
        }
    }

    /** @param list<string> $runes
     * @param  list<string>  $clues
     * @param  list<string>  $prefix
     * @return list<list<string>>
     */
    private function ordersMatchingClues(array $runes, array $clues, array $prefix = []): array
    {
        $positions = array_flip($prefix);
        foreach ($clues as $clue) {
            if (preg_match('/^(\w+) is first\.$/', $clue, $match)) {
                if ($prefix !== [] && $prefix[0] !== $match[1]) {
                    return [];
                }
            } elseif (preg_match('/^(\w+) is (\d+) places (after|before) (\w+)\.$/', $clue, $match)) {
                if (isset($positions[$match[1]], $positions[$match[4]])) {
                    $distance = $positions[$match[1]] - $positions[$match[4]];
                    if ($distance !== (int) $match[2] * ($match[3] === 'after' ? 1 : -1)) {
                        return [];
                    }
                }
            } else {
                $this->fail('Unrecognized ordering clue: '.$clue);
            }
        }
        if ($runes === []) {
            return [$prefix];
        }
        $solutions = [];
        foreach ($runes as $rune) {
            array_push($solutions, ...$this->ordersMatchingClues(array_values(array_diff($runes, [$rune])), $clues, [...$prefix, $rune]));
        }

        return $solutions;
    }

    public function test_each_tier_adds_more_required_reasoning_steps(): void
    {
        $engine = new CurseEngine;
        foreach (['cipher', 'order', 'missing', 'arithmetic', 'odd', 'reverse', 'focus', 'rings', 'towers', 'lanterns'] as $kind) {
            $lengths = array_map(fn (int $level): int => $engine->challenge($kind, $level)['challenge']['answer_length'], [1, 2, 3]);
            $this->assertGreaterThan($lengths[0], $lengths[1], $kind);
            $this->assertGreaterThan($lengths[1], $lengths[2], $kind);
        }
    }

    public function test_spatial_challenges_are_solvable_using_only_visible_geometry_and_labels(): void
    {
        $engine = new CurseEngine;
        foreach (['rings', 'towers', 'lanterns'] as $kind) {
            foreach ([1, 2, 3] as $level) {
                for ($attempt = 0; $attempt < 20; $attempt++) {
                    $generated = $engine->challenge($kind, $level);
                    $challenge = $generated['challenge'];
                    $options = array_column($challenge['options'], 'label', 'id');
                    $expected = [];
                    if ($kind === 'rings') {
                        foreach ($challenge['scene']['rings'] as $ring) {
                            $this->assertContains($ring['start'], [1, 2, 3], 'No ring starts solved.');
                            $this->assertCount(4, $ring['options']);
                            $direction = $ring['start'];
                            $turns = 0;
                            while ($direction !== 0) {
                                $direction = ($direction + 1) % 4;
                                $turns++;
                            }
                            $expected[] = $ring['options'][$turns];
                        }
                    } else {
                        $objects = $challenge['scene']['objects'];
                        $this->assertCount(count($options), $objects);
                        if ($kind === 'towers') {
                            $this->assertCount(count($objects), array_unique(array_column($objects, 'height')));
                        }
                        foreach ($objects as $object) {
                            $this->assertArrayHasKey($object['option_id'], $options);
                            $this->assertLessThanOrEqual(3, abs($object['x']));
                            $this->assertLessThanOrEqual(3, abs($object['z']));
                            $this->assertGreaterThan(0, $object['height']);
                        }
                        if ($kind === 'towers') {
                            usort($objects, fn (array $a, array $b): int => $a['height'] <=> $b['height']);
                        } else {
                            $objects = array_filter($objects, fn (array $o): bool => is_numeric($options[$o['option_id']]));
                            $this->assertCount($level, array_diff(array_keys($options), array_column($objects, 'option_id')));
                            usort($objects, fn (array $a, array $b): int => (int) $options[$a['option_id']] <=> (int) $options[$b['option_id']]);
                        }
                        $expected = array_column($objects, 'option_id');
                    }
                    $this->assertSame($expected, $generated['solution']);
                    $this->assertSame(count($expected), $challenge['answer_length']);
                    $this->assertLessThanOrEqual(12, count($expected));
                    $this->assertEmpty(array_diff($expected, array_keys($options)));
                    $this->assertCount(count($options), array_unique(array_values($options)));
                    $next = $engine->challenge($kind, $level);
                    $this->assertEmpty(array_intersect(array_keys($options), array_column($next['challenge']['options'], 'id')));
                }
            }
        }
    }

    /** @return iterable<string, array{string, string, string, list<string>}> */
    public static function puzzleSets(): iterable
    {
        yield 'astral-binding' => ['puzzle', 'astral-binding', 'Astral Binding', ['rings', 'order', 'cipher']];
        yield 'drowned-tribute' => ['puzzle', 'drowned-tribute', 'Drowned Tribute', ['towers', 'arithmetic', 'missing']];
        yield 'rune-prison' => ['puzzle', 'rune-prison', 'Rune Prison', ['cipher', 'towers', 'order']];
        yield 'lost-shore' => ['mist', 'lost-shore', 'Lost Shore', ['lanterns', 'focus', 'missing']];
        yield 'echoing-fog' => ['mist', 'echoing-fog', 'Echoing Fog', ['focus', 'reverse', 'lanterns']];
        yield 'false-lights' => ['mist', 'false-lights', 'False Lights', ['odd', 'lanterns', 'order']];
        yield 'crooked-compass' => ['misdirection', 'crooked-compass', 'Crooked Compass', ['rings', 'reverse', 'odd']];
        yield 'mirror-trail' => ['misdirection', 'mirror-trail', 'Mirror Trail', ['reverse', 'order', 'rings']];
        yield 'false-oracle' => ['misdirection', 'false-oracle', 'False Oracle', ['odd', 'cipher', 'reverse']];
    }

    /** @param list<string> $kinds */
    #[DataProvider('puzzleSets')]
    public function test_each_set_persists_through_three_distinct_seals(string $type, string $set, string $name, array $kinds): void
    {
        $engine = new CurseEngine;
        $this->assertCount(3, array_unique($kinds));
        foreach ([1 => 0, 2 => 2, 3 => 4] as $level => $tokens) {
            if ($type === 'misdirection' && $level < 3) {
                continue;
            }
            $curse = $engine->create($tokens, 6, 2, $type, $set);
            for ($stage = 1; $stage <= 3; $stage++) {
                // Match state is stored as JSON between actions and page reloads.
                $curse = json_decode(json_encode($curse, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
                $view = $engine->view($curse);
                $this->assertSame($stage, $view['stage']);
                $this->assertSame(3, $view['stages']);
                $this->assertSame($set, $curse['puzzle_set']);
                $this->assertSame($name, $view['set_name']);
                $this->assertSame($kinds[$stage - 1], $view['challenge']['kind']);
                $this->assertArrayNotHasKey('solution', $view);
                $this->assertArrayNotHasKey('puzzle_set', $view);
                $this->assertSame($level, $curse['level']);
                $this->assertSame(2, $curse['day']);
                if (isset($view['challenge']['scene'])) {
                    $this->assertSame($level, $view['challenge']['scene']['difficulty']);
                    $this->assertFalse($view['challenge']['scene']['guided']);
                }
                $next = $engine->advance($curse);
                if ($stage === 3) {
                    $this->assertNull($next);
                } else {
                    $this->assertNotSame($curse['id'], $next['id']);
                    $this->assertEmpty(array_intersect($curse['solution'], $next['solution']));
                    $curse = $next;
                }
            }
        }
    }

    public function test_sets_cannot_be_used_for_another_curse_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CurseEngine)->create(4, 6, 1, 'mist', 'astral-binding');
    }

    public function test_unknown_sets_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CurseEngine)->create(4, 6, 1, 'puzzle', 'unknown');
    }

    public function test_legacy_curses_continue_their_original_sequence(): void
    {
        $engine = new CurseEngine;
        foreach (['puzzle' => 'rings', 'mist' => 'lanterns', 'misdirection' => 'rings'] as $type => $kind) {
            $curse = ['id' => 'legacy', 'type' => $type, 'day' => 1, 'level' => 3, 'stage' => 1, 'stages' => 3,
                ...$engine->challenge($kind, 3)];
            $next = $engine->advance($curse);
            $this->assertSame($type === 'puzzle' ? 'towers' : $kind, $next['challenge']['kind']);
            $next = $engine->advance($next);
            $this->assertSame($kind, $next['challenge']['kind']);
            $this->assertNull($engine->advance($next));
        }
        $this->assertNull($engine->advance(['id' => 'legacy', ...$engine->challenge('focus', 1)]));
    }

    public function test_easy_spatial_challenges_remain_unguided(): void
    {
        $engine = new CurseEngine;
        $easy = $engine->challenge('lanterns', 1)['challenge'];
        $labels = array_column($easy['options'], 'label');
        sort($labels);
        $this->assertCount(5, array_filter($labels, is_numeric(...)));
        $this->assertCount(1, array_filter($labels, fn (string $label): bool => ! is_numeric($label)));
        $this->assertSame([0.9], array_values(array_unique(array_column($easy['scene']['objects'], 'height'))));
        $rings = $engine->challenge('rings', 1)['challenge']['scene']['rings'];
        $this->assertCount(3, $rings);
        foreach ($rings as $ring) {
            $this->assertContains($ring['start'], [1, 2], 'Each ring needs at least two turns.');
        }
    }
}
