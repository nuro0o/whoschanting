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
            if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\A(day|night)[1-9][0-9]*\.(mp3|ogg|wav|m4a|aac|opus)\z/i', $file->getFilename(), $match)) {
                $period = strtolower($match[1]) === 'day' ? 'day' : 'night';
                $tracks[$period][] = $file->getFilename();
            }
        }

        foreach ($tracks as $period => $files) {
            natcasesort($files);
            $tracks[$period] = array_values(array_map(fn (string $file): string => '/assets/Music/'.rawurlencode($file), $files));
        }

        return ['day' => $tracks['day'], 'night' => $tracks['night']];
    }
}
