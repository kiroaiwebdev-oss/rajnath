<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your '.config('brand')['brand.name'].' code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp', text: 'mail.otp-text');
    }
}
