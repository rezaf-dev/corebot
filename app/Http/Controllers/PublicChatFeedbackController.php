<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageFeedbackRequest;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\ChatMessageFeedback;
use Illuminate\Http\JsonResponse;

class PublicChatFeedbackController extends Controller
{
    public function store(StoreChatMessageFeedbackRequest $request): JsonResponse
    {
        $data = $request->validated();
        $bot = Bot::query()->with('tenant')->where('public_key', $data['bot_public_key'])->firstOrFail();
        abort_unless($bot->isActive() && $bot->tenant->isActive(), 404);
        $conversation = ChatConversation::query()
            ->where('id', $data['conversation_id'])
            ->where('bot_id', $bot->id)
            ->where('public_session_token', hash('sha256', $data['conversation_token']))
            ->firstOrFail();
        abort_unless($conversation->messages()->whereKey($data['chat_message_id'])->where('role', 'assistant')->exists(), 404);

        ChatMessageFeedback::updateOrCreate(
            ['chat_message_id' => $data['chat_message_id'], 'session_token' => hash('sha256', $data['conversation_token'])],
            [
                'tenant_id' => $bot->tenant_id,
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'vote' => $data['vote'],
                'reason' => $data['reason'] ?? null,
                'comment' => $data['comment'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }
}
