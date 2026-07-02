<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends the 6-digit password reset PIN to the user's email.
 */
class PasswordResetPinMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $pin;
    public string $clinicName;

    public function __construct(string $pin)
    {
        $this->pin        = $pin;
        $this->clinicName = config('app.name', 'Dentfluence');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Password Reset PIN — ' . $this->clinicName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-pin',
        );
    }
}
