<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\ProgressionController;
use App\Http\Middleware\EnsureVerifiedAccount;
use App\Http\Middleware\PrivateGameResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->middleware(EnsureVerifiedAccount::class)->name('home');

Route::inertia('/tutorial', 'Tutorial')->middleware(PrivateGameResponse::class)->name('tutorial');

Route::middleware([PrivateGameResponse::class, EnsureVerifiedAccount::class])->group(function (): void {
    Route::post('/rooms', [GameController::class, 'create'])->middleware('throttle:10,1')->block();
    Route::post('/rooms/join', [GameController::class, 'join'])->middleware(['throttle:20,1', 'throttle:game-join'])->block();
    Route::get('/rooms/{code}', [GameController::class, 'show'])->name('game');
    Route::get('/rooms/{code}/state', [GameController::class, 'state'])->middleware('throttle:game-state')->block();
    Route::post('/rooms/{code}/actions', [GameController::class, 'action'])->middleware('throttle:game-action')->block();
    Route::post('/rooms/{code}/broadcast-auth', [GameController::class, 'broadcastAuth'])->middleware('throttle:game-action')->block();
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GameController::class, 'dashboard'])->middleware(PrivateGameResponse::class)->name('dashboard');
    Route::get('progression', [ProgressionController::class, 'show'])->middleware(PrivateGameResponse::class)->name('progression');
    Route::post('account/customization', [ProgressionController::class, 'customize'])->middleware([PrivateGameResponse::class, 'throttle:30,1'])->block()->name('account.customization');
});

require __DIR__.'/settings.php';
