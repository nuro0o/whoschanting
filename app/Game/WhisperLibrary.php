<?php

namespace App\Game;

use FilesystemIterator;
use SplFileInfo;

class WhisperLibrary
{
    /** @return array{ambient: list<string>, oneoff: list<string>} */
    public function tracks(string $directory): array
    {
        return [
            'ambient' => $this->recordings($directory, '/assets/Sounds/Whispers/'),
            'oneoff' => $this->recordings($directory.'/oneoff', '/assets/Sounds/Whispers/oneoff/'),
        ];
    }

    /** @return list<string> */
    private function recordings(string $directory, string $urlPrefix): array
    {
        if (! is_dir($directory) || ! is_readable($directory)) {
            return [];
        }
        $tracks = [];
        foreach (new FilesystemIterator($directory) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\.(mp3|ogg|wav|m4a|aac|opus)\z/i', $file->getFilename())) {
                $tracks[] = $file->getFilename();
            }
        }
        natcasesort($tracks);

        return array_values(array_map(fn (string $file): string => $urlPrefix.rawurlencode($file), $tracks));
    }
}
