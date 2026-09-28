<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $subject = $this->inquiry->subject
            ? 'New Inquiry: '.$this->inquiry->subject
            : 'New Inquiry from '.$this->inquiry->name;

        $replyTo = filter_var($this->inquiry->email, FILTER_VALIDATE_EMAIL)
            ? [new Address($this->inquiry->email, $this->inquiry->name ?: null)]
            : [];

        return new Envelope(
            subject: $subject,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiry_received',
            with: ['inquiry' => $this->inquiry],
        );
    }
}
