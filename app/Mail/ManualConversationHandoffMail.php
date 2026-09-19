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

class ManualConversationHandoffMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Bot $bot,
        public ChatConversation $conversation,
        public ?string $lastUserMessage = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->bot->name}] Reply needed — conversation #{$this->conversation->id}",
            replyTo: filled($this->conversation->visitor_email)
                ? [$this->conversation->visitor_email]
                : [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.manual-conversation-handoff',
        );
    }
}
