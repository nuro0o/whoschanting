<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProgressionController;
use App\Http\Controllers\StoreCheckoutController;
use App\Http\Middleware\EnsureVerifiedAccount;
use App\Http\Middleware\PrivateGameResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->middleware(EnsureVerifiedAccount::class)->name('home');

foreach (['contact', 'privacy', 'terms', 'refunds'] as $document) {
    Route::get('/'.$document, [LegalController::class, 'show'])->defaults('document', $document)
        ->middleware(PrivateGameResponse::class)->name($document);
}
Route::post('/refunds', [LegalController::class, 'withdraw'])->middleware([PrivateGameResponse::class, 'throttle:5,1'])->name('refunds.submit');

Route::inertia('/tutorial', 'Tutorial')->middleware(PrivateGameResponse::class)->name('tutorial');

Route::post('/stripe/webhook', [StoreCheckoutController::class, 'webhook'])->middleware(PrivateGameResponse::class)->name('stripe.webhook');

Route::middleware([PrivateGameResponse::class, EnsureVerifiedAccount::class])->group(function (): void {
    Route::get('/rooms', [GameController::class, 'browser'])->name('rooms.browser');
    Route::get('/rooms/public', [GameController::class, 'publicRooms'])->middleware('throttle:game-browser');
    Route::post('/rooms', [GameController::class, 'create'])->middleware('throttle:10,1')->block();
    Route::post('/rooms/join', [GameController::class, 'join'])->middleware(['throttle:20,1', 'throttle:game-join'])->block();
    Route::get('/rooms/{code}', [GameController::class, 'show'])->name('game');
    Route::get('/rooms/{code}/state', [GameController::class, 'state'])->middleware('throttle:game-state')->block();
    Route::post('/rooms/{code}/actions', [GameController::class, 'action'])->middleware('throttle:game-action')->block();
    Route::post('/rooms/{code}/presence', [GameController::class, 'presence'])->middleware('throttle:game-action')->block();
    Route::post('/rooms/{code}/broadcast-auth', [GameController::class, 'broadcastAuth'])->middleware('throttle:game-action')->block();
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GameController::class, 'dashboard'])->middleware(PrivateGameResponse::class)->name('dashboard');
    Route::get('progression', [ProgressionController::class, 'show'])->middleware(PrivateGameResponse::class)->name('progression');
    Route::post('account/customization', [ProgressionController::class, 'customize'])->middleware([PrivateGameResponse::class, 'throttle:30,1'])->block()->name('account.customization');
    Route::post('account/store/purchase', [ProgressionController::class, 'purchase'])->middleware([PrivateGameResponse::class, 'throttle:30,1'])->block()->name('account.store.purchase');
    Route::post('account/store/checkout', [StoreCheckoutController::class, 'checkout'])->middleware([PrivateGameResponse::class, 'throttle:10,1'])->block()->name('account.store.checkout');
    Route::get('account/store/status', [StoreCheckoutController::class, 'status'])->middleware([PrivateGameResponse::class, 'throttle:30,1'])->name('account.store.status');
});

require __DIR__.'/settings.php';
