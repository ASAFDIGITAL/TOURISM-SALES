<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $alertSubject,
        public string $alertBody,
        public string $badgeText = '',
        public array $details = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public string $tone = 'info',
        public string $lang = 'he',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->alertSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-alert',
        );
    }
}
