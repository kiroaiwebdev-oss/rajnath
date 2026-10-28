<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProviderTestMail extends Mailable
{
    public function __construct(public string $sentBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.config('brand')['brand.name']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.provider-test');
    }
}
