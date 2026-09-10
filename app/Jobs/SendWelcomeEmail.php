<?php

namespace App\Jobs;

use App\Mail\WelcomeToVillage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $user = User::whereKey($this->userId)->lockForUpdate()->first();
            if ($user === null || ! $user->hasVerifiedEmail() || $user->welcome_email_sent_at !== null) {
                return;
            }

            Mail::to($user->email)->send(new WelcomeToVillage($user->name));
            $user->forceFill(['welcome_email_sent_at' => now()])->save();
        });
    }
}
