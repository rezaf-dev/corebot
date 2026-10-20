<?php

namespace App\Services\Conversations;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Services\AI\OpenAIService;
use Throwable;

class ConversationLanguageResolver
{
    public function __construct(
        private readonly OpenAIService $openAI,
    ) {}

    public function resolve(Bot $bot, ChatConversation $conversation, string $message): void
    {
        $language = $conversation->language;
        $detected = $conversation->language_detected;

        try {
            $resolved = $this->openAI->detectConversationLanguage(
                $bot->tenant,
                $message,
                $detected ? $language : null,
                ['bot_id' => $bot->id, 'conversation_id' => $conversation->id],
            );
        } catch (Throwable) {
            return;
        }

        if ($resolved === null || ! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $resolved) || strlen($resolved) > 32) {
            return;
        }

        ChatConversation::query()->whereKey($conversation->id)
            ->where('language', $language)
            ->where('language_detected', $detected)
            ->update(['language' => $resolved, 'language_detected' => true]);
        $conversation->refresh();
    }
}
