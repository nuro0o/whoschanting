<?php

use App\Game\MatchEngine;
use App\Models\GameRoom;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('game:tick', function (MatchEngine $engine): void {
    GameRoom::where(fn ($query) => $query->where('deadline', '<=', now())->orWhere('maintenance_at', '<=', now()))->select('id')->chunkById(100, function ($rooms) use ($engine): void {
        foreach ($rooms as $room) {
            $engine->resolve($room->id);
        }
    });
})->purpose('Resolve expired game phases under a database row lock');

Schedule::command('game:tick')->everySecond()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
