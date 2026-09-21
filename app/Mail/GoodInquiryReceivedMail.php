<?php

namespace App\Mail;

use App\Models\GoodInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoodInquiryReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GoodInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->inquiry->customer_email, $this->inquiry->customer_name)],
            subject: $this->inquiry->kindLabel().' · '.$this->inquiry->number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.good-inquiry-received');
    }
}
