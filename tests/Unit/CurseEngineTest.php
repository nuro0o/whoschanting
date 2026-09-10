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
            for ($i = 0; $i < 20; $i++) {
                $curse = $engine->create($tokens, $goal, 1);
                $this->assertSame($tier, $curse['level']);
                $this->assertContains($curse['type'], $tier === 3 ? ['puzzle', 'mist', 'misdirection'] : ['puzzle', 'mist']);
                $this->assertArrayNotHasKey('solution', $engine->view($curse));
            }
        }
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
        foreach (['cipher', 'order', 'missing', 'arithmetic', 'odd', 'reverse', 'focus'] as $kind) {
            $lengths = array_map(fn (int $level): int => $engine->challenge($kind, $level)['challenge']['answer_length'], [1, 2, 3]);
            $this->assertGreaterThan($lengths[0], $lengths[1], $kind);
            $this->assertGreaterThan($lengths[1], $lengths[2], $kind);
        }
    }
}
