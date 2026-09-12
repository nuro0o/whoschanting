<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PurchaseReceipt extends Mailable
{
    /** @param array<string,mixed> $receipt */
    public function __construct(public array $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [new Address($this->receipt['support_email'])],
            subject: 'Thank you for supporting the village — '.$this->receipt['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.purchase-receipt', text: 'mail.purchase-receipt-text');
    }
}
