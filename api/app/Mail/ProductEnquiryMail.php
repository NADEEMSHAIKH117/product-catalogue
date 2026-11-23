<?php

namespace App\Mail;

use App\Enquiries\Enquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email sent to the sales inbox. The transport (Mailpit locally, SES or any
 * SMTP/API provider later) is chosen purely by MAIL_* env vars.
 */
class ProductEnquiryMail extends Mailable
{
    public function __construct(public readonly Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Reply-To lets sales answer the customer directly from their inbox.
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
            subject: 'New enquiry: '.$this->enquiry->product->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enquiry',
            text: 'emails.enquiry-text',
        );
    }
}
