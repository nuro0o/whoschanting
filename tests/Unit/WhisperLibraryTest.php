<?php

namespace Tests\Unit;

use App\Game\WhisperLibrary;
use PHPUnit\Framework\TestCase;

class WhisperLibraryTest extends TestCase
{
    public function test_audio_recordings_are_discovered_without_including_other_files(): void
    {
        $directory = sys_get_temp_dir().'/chanting-whispers-'.bin2hex(random_bytes(8));
        mkdir($directory);
        mkdir($directory.'/oneoff');
        touch($directory.'/oneoff/behind you.mp3');
        $files = ['whisper10.mp3', 'whisper1.ogg', 'WHISPER2.WAV', 'ghost whisper.mp3', 'whisper3.txt', 'credits.md'];
        try {
            foreach ($files as $file) {
                touch($directory.'/'.$file);
            }
            mkdir($directory.'/whisper4.mp3');
            $library = new WhisperLibrary;
            $this->assertSame([
                'ambient' => [
                    '/assets/Sounds/Whispers/ghost%20whisper.mp3',
                    '/assets/Sounds/Whispers/whisper1.ogg',
                    '/assets/Sounds/Whispers/WHISPER2.WAV',
                    '/assets/Sounds/Whispers/whisper10.mp3',
                ],
                'oneoff' => ['/assets/Sounds/Whispers/oneoff/behind%20you.mp3'],
            ], $library->tracks($directory));
            $this->assertSame(['ambient' => [], 'oneoff' => []], $library->tracks($directory.'/missing'));
        } finally {
            foreach ($files as $file) {
                unlink($directory.'/'.$file);
            }
            rmdir($directory.'/whisper4.mp3');
            unlink($directory.'/oneoff/behind you.mp3');
            rmdir($directory.'/oneoff');
            rmdir($directory);
        }
    }
}
