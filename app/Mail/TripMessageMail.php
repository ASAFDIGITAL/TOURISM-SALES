<?php

namespace App\Mail;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public string $subjectLine,
        public string $bodyText,
        public string $localeCode = 'he',
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
        public ?string $senderName = null,
        public ?string $trackingUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $tenant = $this->trip->tenant;
        $fromAddress = (string) config('mail.from.address', 'info@asafdigital.com');
        $fromName = trim((string) ($this->senderName ?: ($tenant?->name ?: config('mail.from.name', 'TOURISM-SALES'))));

        $replyTo = [];
        if ($tenant && !empty($tenant->email) && filter_var($tenant->email, FILTER_VALIDATE_EMAIL)) {
            $replyTo[] = new Address($tenant->email, $fromName);
        }

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: $replyTo,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trip-message',
            with: [
                'trip' => $this->trip,
                'tenant' => $this->trip->tenant,
                'subjectLine' => $this->subjectLine,
                'bodyText' => $this->bodyText,
                'localeCode' => $this->localeCode,
                'trackingUrl' => $this->trackingUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (!$this->pdfContent || !$this->pdfFilename) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
