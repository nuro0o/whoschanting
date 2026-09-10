<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyVillageEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $email)
    {
        $this->afterCommit();
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return ! $notifiable->hasVerifiedEmail() && $notifiable->email === $this->email;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Confirm your email · Who's Chanting?")
            ->view(['emails.verify', 'emails.verify-text'], [
                'name' => $notifiable->name,
                'verificationUrl' => $this->verificationUrl($notifiable),
                'expiresIn' => config('auth.verification.expire', 60),
            ]);
    }
}
