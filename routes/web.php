<?php

use App\Http\Controllers\GameController;
use App\Http\Middleware\EnsureVerifiedAccount;
use App\Http\Middleware\PrivateGameResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->middleware(EnsureVerifiedAccount::class)->name('home');

Route::middleware([PrivateGameResponse::class, EnsureVerifiedAccount::class])->group(function (): void {
    Route::post('/rooms', [GameController::class, 'create'])->middleware('throttle:10,1')->block();
    Route::post('/rooms/join', [GameController::class, 'join'])->middleware('throttle:20,1')->block();
    Route::get('/rooms/{code}', [GameController::class, 'show'])->name('game');
    Route::get('/rooms/{code}/state', [GameController::class, 'state'])->middleware('throttle:game-state')->block();
    Route::post('/rooms/{code}/actions', [GameController::class, 'action'])->middleware('throttle:game-action')->block();
    Route::post('/rooms/{code}/broadcast-auth', [GameController::class, 'broadcastAuth'])->middleware('throttle:game-action')->block();
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
