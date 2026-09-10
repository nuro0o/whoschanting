<?php

namespace Tests\Unit;

use App\Game\MusicLibrary;
use PHPUnit\Framework\TestCase;

class MusicLibraryTest extends TestCase
{
    public function test_numbered_tracks_are_discovered_on_each_load_and_other_files_are_ignored(): void
    {
        $directory = sys_get_temp_dir().'/chanting-music-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $files = ['day10.mp3', 'day1.mp3', 'night3.ogg', 'night1.mp3', 'DAY4.MP3', 'day0.mp3', 'day2.txt', 'notes.mp3'];
        try {
            foreach ($files as $file) {
                touch($directory.'/'.$file);
            }
            mkdir($directory.'/night9.mp3');
            $library = new MusicLibrary;
            $this->assertSame([
                'day' => ['/assets/Music/day1.mp3', '/assets/Music/DAY4.MP3', '/assets/Music/day10.mp3'],
                'night' => ['/assets/Music/night1.mp3', '/assets/Music/night3.ogg'],
            ], $library->tracks($directory));

            $files[] = 'day2.mp3';
            touch($directory.'/day2.mp3');
            $this->assertSame('/assets/Music/day2.mp3', $library->tracks($directory)['day'][1]);
            $this->assertSame(['day' => [], 'night' => []], $library->tracks($directory.'/missing'));
        } finally {
            foreach ($files as $file) {
                unlink($directory.'/'.$file);
            }
            rmdir($directory.'/night9.mp3');
            rmdir($directory);
        }
    }
}
