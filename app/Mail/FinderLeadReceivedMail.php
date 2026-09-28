<?php

namespace App\Mail;

use App\Models\FinderLead;
use App\Support\ServiceFinderSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FinderLeadReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FinderLead $lead)
    {
    }

    public function envelope(): Envelope
    {
        // Subject is a configurator template so the sales team can tune what
        // shows in their inbox list without a deploy.
        $subject = ServiceFinderSettings::render('lead_subject_template', [
            'service' => $this->lead->service_label ?: 'Security services',
            'area' => $this->lead->area?->name ?: $this->lead->postcode_or_area,
            'name' => $this->lead->name,
        ]);

        return new Envelope(
            subject: $subject !== '' ? $subject : 'New quote request',
            replyTo: [new Address($this->lead->email, $this->lead->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finder_lead_received',
            with: ['lead' => $this->lead],
        );
    }
}
