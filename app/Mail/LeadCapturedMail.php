<?php

namespace App\Mail;

use App\Models\Bot;
use App\Models\ChatConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadCapturedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Bot $bot,
        public ChatConversation $conversation,
        public ?string $lastUserMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->conversation->visitor_name
            ?: $this->conversation->visitor_email
            ?: 'Visitor';

        return new Envelope(
            subject: "[{$this->bot->name}] New lead — {$label}",
            replyTo: filled($this->conversation->visitor_email)
                ? [$this->conversation->visitor_email]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.lead-captured',
        );
    }
}
