<?php

// Separate PHP process for exercising real MySQL row locks in GameConcurrencyTest.
use App\Game\MatchEngine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['broadcasting.default' => 'null', 'queue.default' => 'sync']);
$engine = app(MatchEngine::class);
try {
    if ($argv[1] === 'resolve') {
        $engine->resolve((int) $argv[2]);
    } elseif ($argv[1] === 'join') {
        $engine->join($argv[2], $argv[3], $argv[3], ipAddress: $argv[4]);
    } else {
        $engine->access($argv[2], $argv[3], ['type' => 'night', 'phase_id' => (int) $argv[4]]);
    }
    echo 'accepted';
} catch (ValidationException) {
    echo 'rejected';
}
