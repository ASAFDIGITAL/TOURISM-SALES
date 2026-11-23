<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $highlights
     */
    public function __construct(
        public Tenant $tenant,
        public string $step,
        public string $subjectLine,
        public string $heading,
        public string $introText,
        public array $highlights,
        public string $ctaLabel,
        public string $ctaUrl,
        public string $localeCode = 'he',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-onboarding',
            with: [
                'tenant' => $this->tenant,
                'step' => $this->step,
                'heading' => $this->heading,
                'introText' => $this->introText,
                'highlights' => $this->highlights,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
                'localeCode' => $this->localeCode,
            ],
        );
    }
}
