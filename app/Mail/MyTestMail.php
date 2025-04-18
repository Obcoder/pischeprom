<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MyTestMail extends Mailable
{
    use Queueable, SerializesModels;

//    public $details;
    public string $subjectLine;
    public $details;
//    public array $products;

    /**
     * Create a new message instance.
     */
//    public function __construct($details, $subjectLine)
    public function __construct($details)
    {
        $this->details = $details;
//        $this->subjectLine = $subjectLine;
//        $this->products = $products;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
//            ->subject($this->subjectLine)
            ->subject('ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки')
            ->view('emails.funEmail')
            ->text('emails.funEmail_plain')
            ->with('details', $this->details);
    }

    /**
     * Get the message envelope.
     */

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
