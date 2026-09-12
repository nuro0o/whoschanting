<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WithdrawalAcknowledgment extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param array<string,string|null> $details */
    public function __construct(public array $details, public bool $forSupport = false)
    {
        // Queue inserts and the submission marker commit together in the application's database.
        $this->onConnection('database')->beforeCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->forSupport ? (string) $this->details['email'] : (string) config('legal.support_email'))],
            subject: ($this->forSupport ? 'Withdrawal request: ' : 'We received your withdrawal request: ').$this->details['reference'],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.withdrawal-acknowledgment');
    }
}
