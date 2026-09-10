<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeToVillage extends Mailable
{
    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Welcome to Who's Chanting?");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome', text: 'emails.welcome-text');
    }
}
