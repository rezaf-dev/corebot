<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, company: ?string, type: string, message: string}  $request
     */
    public function __construct(public array $request) {}

    public function envelope(): Envelope
    {
        $type = ucfirst(str_replace('_', ' ', $this->request['type']));

        return new Envelope(
            subject: "[corebot] {$type} — {$this->request['name']}",
            replyTo: [$this->request['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.support-request',
        );
    }
}
