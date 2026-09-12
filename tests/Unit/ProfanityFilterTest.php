<?php

namespace Tests\Unit;

use App\Game\ProfanityFilter;
use App\Game\VillageNames;
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

    public function test_toxic_names_including_compounds_and_obfuscation_are_replaced(): void
    {
        $filter = new ProfanityFilter;
        $pool = (new VillageNames)->all();
        foreach (['shithead', 'SHITHEAD', 'sh1thead', 's.h.i.t.h.e.a.d', 'shiiithead', "sh\u{200B}ithead", 'ｓｈｉｔｈｅａｄ', 'xxshithead99', 'CaptainFuckface', 'dipshit', 'b!tch', 'cunt'] as $name) {
            $this->assertTrue($filter->containsInName($name), $name);
            $this->assertContains($filter->playerName($name), $pool, $name);
        }
        foreach (['Scunthorpe', 'Assistant', 'Classy', 'Cassie', 'Dick', 'Cocktail', 'Captain', 'Café'] as $name) {
            $this->assertSame($name, $filter->playerName(' '.$name.' '));
        }
    }

    public function test_village_name_pool_is_large_unique_safe_and_fits_the_name_limit(): void
    {
        $filter = new ProfanityFilter;
        $names = (new VillageNames)->all();
        $this->assertCount(4096, array_unique($names));
        foreach ($names as $name) {
            $this->assertLessThanOrEqual(24, mb_strlen($name));
            $this->assertFalse($filter->containsInName($name), $name);
        }
    }

    public function test_replacement_excludes_taken_names_case_insensitively(): void
    {
        $names = (new VillageNames)->all();
        $remaining = array_pop($names);
        $taken = array_map(mb_strtolower(...), $names);
        $this->assertSame($remaining, (new ProfanityFilter)->playerName('shithead', $taken));
    }
}
