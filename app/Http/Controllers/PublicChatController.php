<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Services\Rag\ChatAnswerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicChatController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'visitor_id' => ['nullable', 'string', 'max:100'],
            'source_url' => ['nullable', 'url', 'max:2000'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $this->ensureAllowedDomain($bot, $data['source_url'] ?? $request->headers->get('referer'));

        $conversation = ChatConversation::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'visitor_id' => $data['visitor_id'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'welcome_message' => $bot->welcome_message,
            'collect_visitor_email' => $bot->collect_visitor_email,
            'collect_visitor_phone' => $bot->collect_visitor_phone,
        ]);
    }

    public function message(Request $request, ChatAnswerService $answerService): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = ChatConversation::query()
            ->where('id', $data['conversation_id'])
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->firstOrFail();

        return response()->json($answerService->answer($bot->load('tenant.aiSetting'), $conversation, $data['message']));
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = ChatConversation::query()
            ->where('id', $data['conversation_id'])
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->firstOrFail();

        $conversation->update(collect($data)->only(['visitor_name', 'visitor_email', 'visitor_phone'])->all());

        return response()->json(['ok' => true]);
    }

    private function activeBot(string $key): Bot
    {
        $bot = Bot::query()->with('tenant.aiSetting')->where('public_key', $key)->firstOrFail();

        abort_unless($bot->isActive() && $bot->tenant->isActive(), 404);

        return $bot;
    }

    private function ensureAllowedDomain(Bot $bot, ?string $sourceUrl): void
    {
        $domains = $bot->allowed_domains ?? [];

        if ($domains === []) {
            return;
        }

        $host = strtolower(parse_url((string) $sourceUrl, PHP_URL_HOST) ?: '');
        abort_unless($host && in_array($host, $domains, true), 403);
    }
}
