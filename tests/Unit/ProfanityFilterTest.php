<?php

namespace Tests\Unit;

use App\Game\ProfanityFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfanityFilterTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function examples(): array
    {
        return [
            'case and punctuation' => ['That is SHIT, really!', 'That is ****, really!'],
            'several words' => ['Fuck this bullshit.', '**** this ********.'],
            'leet' => ['sh1t b!tch @ssh0le', '**** ***** *******'],
            'separators' => ['s.h.i.t f u c k', '******* *******'],
            'repeated letters' => ['shiiit fuuuck', '****** ******'],
            'invisible separator' => ["sh\u{200B}it", '*****'],
            'combining mark' => ["shi\u{0301}t", '*****'],
            'fullwidth letters' => ['ＳＨＩＴ', '****'],
            'safe substrings' => ['Scunthorpe, assistant, class, assignment, cocktail, Dick and hell.', 'Scunthorpe, assistant, class, assignment, cocktail, Dick and hell.'],
            'unicode boundaries' => ['éshit shité', 'éshit shité'],
            'unicode surroundings' => ['Hello 🌊 shit — café!', 'Hello 🌊 **** — café!'],
            'already masked' => ['**** and *****', '**** and *****'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('examples')]
    public function test_masks_words_while_preserving_other_text(string $input, string $expected): void
    {
        $filter = new ProfanityFilter;
        $this->assertSame($expected, $filter->mask($input));
        $this->assertSame($expected, $filter->mask($expected));
    }

    public function test_word_list_is_configurable_and_entries_are_literal(): void
    {
        config(['moderation.words' => ['curse', 'bad(word)']]);
        $this->assertSame('***** ********* badword shit', (new ProfanityFilter)->mask('curse bad(word) badword shit'));
        config(['moderation.words' => []]);
        $this->assertSame('curse', (new ProfanityFilter)->mask('curse'));
    }
}
