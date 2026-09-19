<?php

namespace App\Services\Conversations;

use App\Mail\ManualConversationHandoffMail;
use App\Models\Bot;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\Mail;

class ManualHandoffNotifier
{
    public function escalate(Bot $bot, ChatConversation $conversation): void
    {
        if ($conversation->status !== 'escalated') {
            $conversation->update(['status' => 'escalated']);
        }

        if ($conversation->manual_handoff_notified_at !== null) {
            return;
        }

        $recipient = $bot->notification_email;

        if (! filled($recipient)) {
            return;
        }

        $lastUserMessage = $conversation->messages()
            ->where('role', 'user')
            ->latest()
            ->value('content');

        Mail::to($recipient)->queue(
            (new ManualConversationHandoffMail($bot, $conversation, $lastUserMessage))->afterCommit(),
        );

        $conversation->update(['manual_handoff_notified_at' => now()]);
    }
}
