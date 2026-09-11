<?php

namespace App\Game;

use FilesystemIterator;
use SplFileInfo;

class MusicLibrary
{
    /** @return array{day: list<string>, night: list<string>} */
    public function tracks(string $directory): array
    {
        $tracks = ['day' => [], 'night' => []];
        if (! is_dir($directory) || ! is_readable($directory)) {
            return $tracks;
        }

        foreach (new FilesystemIterator($directory) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\A(day|night)(?:[1-9][0-9]*|[-_ ][^.].*)\.(mp3|ogg|wav|m4a|aac|opus)\z/i', $file->getFilename(), $match)) {
                $period = strtolower($match[1]) === 'day' ? 'day' : 'night';
                $tracks[$period][] = $file->getFilename();
            }
        }

        foreach (['day' => 'Day', 'night' => 'Night'] as $period => $folder) {
            $path = $directory.'/'.$folder;
            if (! is_dir($path) || ! is_readable($path)) {
                continue;
            }
            foreach (new FilesystemIterator($path) as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\.(mp3|ogg|wav|m4a|aac|opus)\z/i', $file->getFilename())) {
                    $tracks[$period][] = $folder.'/'.$file->getFilename();
                }
            }
        }

        foreach ($tracks as $period => $files) {
            natcasesort($files);
            $tracks[$period] = array_values(array_map(fn (string $file): string => '/assets/Music/'.implode('/', array_map('rawurlencode', explode('/', $file))), $files));
        }

        return ['day' => $tracks['day'], 'night' => $tracks['night']];
    }
}
