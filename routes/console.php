<?php

use App\Game\MatchEngine;
use App\Game\PurchasePolicy;
use App\Models\GameRoom;
use App\Models\PaidOrder;
use App\Models\WithdrawalRequest;
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

Artisan::command('support:prune', function (): void {
    $deleted = WithdrawalRequest::where('created_at', '<', now()->subDays((int) config('legal.request_retention_days')))->delete();
    $this->info('Removed '.$deleted.' expired withdrawal requests.');
})->purpose('Remove withdrawal form records after their published retention period');
Schedule::command('support:prune')->daily()->withoutOverlapping();

Artisan::command('purchases:review {order}', function (): void {
    $order = PaidOrder::whereKey($this->argument('order'))->firstOrFail();
    $this->line(json_encode((new PurchasePolicy)->review($order), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Inspect consent, receipt status and match usage for a purchase');

Artisan::command('purchases:allow-repurchase {order}', function (): void {
    $order = PaidOrder::whereKey($this->argument('order'))->firstOrFail();
    PaidOrder::where('user_id', $order->user_id)->where('bundle_id', $order->bundle_id)
        ->where('status', 'refunded')->update(['repurchase_reviewed_at' => now()]);
    $this->info('Reviewed prior refunds for this account and pack. Repurchasing is available again; future refunds are assessed separately.');
})->purpose('Allow repurchase after support has reviewed repeated refunds');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
