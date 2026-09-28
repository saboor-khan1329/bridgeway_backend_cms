<?php

namespace App\Mail;

use App\Models\OnboardingFormConfig;
use App\Models\OnboardingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingApplicantMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OnboardingSubmission $submission,
        public readonly OnboardingFormConfig $formConfig,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->formConfig->getSetting(
                'applicant_confirmation_subject',
                'Your Application Has Been Received - '.config('app.name', 'Bridgeway Digital CMS')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding.applicant_confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
