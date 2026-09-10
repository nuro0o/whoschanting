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
                    $inscription = explode(' / ', substr(array_pop($clues), strlen('Inscription: ')));
                    $key = [];
                    foreach ($clues as $clue) {
                        [$rune, $value] = explode(' = ', $clue);
                        $key[$rune] = $value;
                    }
                    $expected = array_map(fn (string $rune): string => $key[$rune], $inscription);
                    break;
                case 'order':
                    $chain = [];
                    foreach ($clues as $clue) {
                        [$before, $after] = explode(' comes immediately before ', rtrim($clue, '.'));
                        $chain[$before] = $after;
                    }
                    $head = array_values(array_diff(array_keys($chain), array_values($chain)))[0];
                    $expected[] = $head;
                    while (isset($chain[$head])) {
                        $head = $chain[$head];
                        $expected[] = $head;
                    }
                    break;
                case 'missing':
                    $sequence = explode(' / ', $clues[0]);
                    $hole = array_search('?', $sequence, true);
                    $expected = [(string) (((int) $sequence[$hole - 1] + (int) $sequence[$hole + 1]) / 2)];
                    break;
                case 'arithmetic':
                    $total = array_sum(array_map(intval(...), explode(' + ', substr($clues[0], strlen('Offerings: ')))));
                    $expected = [(string) ($total - (int) substr($clues[1], strlen('Debt: ')))];
                    break;
                case 'odd':
                    $inscriptions = array_map(fn (string $clue): string => explode(': ', $clue)[1], $clues);
                    $counts = array_count_values($inscriptions);
                    foreach ($inscriptions as $index => $inscription) {
                        if ($counts[$inscription] === 1) {
                            $expected = [(string) ($index + 1)];
                        }
                    }
                    break;
                case 'reverse':
                    $expected = array_reverse(explode(' / ', $clues[0]));
                    break;
                case 'focus':
                    $expected = array_values(array_filter($options, is_numeric(...)));
                    sort($expected, SORT_NUMERIC);
                    break;
            }
            $this->assertSame($expected, $actual);
            $this->assertCount(count($expected), $generated['solution']);
            $this->assertSame(count($expected), $challenge['answer_length']);
            $this->assertNotSame($challenge, $engine->challenge($kind, $level)['challenge']);
        }
    }
}
