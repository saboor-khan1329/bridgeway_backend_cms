<?php

namespace App\Mail;

use App\Models\OnboardingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OnboardingSubmission $submission,
        private readonly string $pdfContent,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Onboarding Application — ' . $this->submission->reference_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding.admin_notification',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                'submission-' . $this->submission->reference_number . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
