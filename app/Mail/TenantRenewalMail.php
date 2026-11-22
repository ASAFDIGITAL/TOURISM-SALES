<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantRenewalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $subjectLine,
        public string $bodyText,
        public ?string $paymentUrl = null,
        public string $localeCode = 'he',
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address', 'tourism@asafdigital.com');
        $fromName = (string) config('mail.from.name', 'TOURISM-SALES');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-renewal',
            with: [
                'tenant' => $this->tenant,
                'subjectLine' => $this->subjectLine,
                'bodyText' => $this->bodyText,
                'paymentUrl' => $this->paymentUrl,
                'localeCode' => $this->localeCode,
            ],
        );
    }
}
