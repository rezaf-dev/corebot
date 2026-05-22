<?php

namespace App\Services\Rag;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\RetrievalLog;
use App\Services\AI\OpenAIService;
use Generator;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class ChatAnswerService
{
    public function __construct(
        private readonly SemanticSearchService $search,
        private readonly OpenAIService $openAI,
    ) {}

    public function answer(Bot $bot, ChatConversation $conversation, string $userMessage): array
    {
        $user = $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $chunks = $this->chunks($bot, $conversation, $userMessage);

        if (is_array($chunks)) {
            return $chunks;
        }

        $context = $this->context($chunks);

        $messages = [
            ['role' => 'system', 'content' => $this->prompt($bot, $context)],
            ['role' => 'user', 'content' => $userMessage],
        ];

        try {
            $response = $this->openAI->createChatCompletion($bot->tenant, $messages, [
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'temperature' => (float) $bot->temperature,
            ]);

            $answer = trim($response['choices'][0]['message']['content'] ?? '') ?: $bot->fallback_message;
        } catch (Throwable) {
            return $this->fallback($bot, $conversation, $userMessage, 'Chat completion failed.');
        }

        $assistant = $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => ['source_chunk_ids' => $chunks->pluck('id')->all()],
        ]);

        RetrievalLog::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'chat_message_id' => $assistant->id,
            'query' => $userMessage,
            'selected_chunk_ids' => $chunks->pluck('id')->all(),
            'distances' => $chunks->mapWithKeys(fn ($chunk) => [$chunk->id => $chunk->distance])->all(),
            'context_text' => $context,
        ]);

        return [
            'message' => $answer,
            'fallback' => false,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'distance' => $chunk->distance,
            ])->values(),
        ];
    }

    public function stream(Bot $bot, ChatConversation $conversation, string $userMessage): Generator
    {
        $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $chunks = $this->chunks($bot, $conversation, $userMessage);

        if (is_array($chunks)) {
            yield $this->streamEvent('text_delta', ['delta' => $chunks['message']]);
            yield $this->streamEvent('meta', ['fallback' => true, 'sources' => []]);
            yield $this->streamEvent('done');

            return;
        }

        $context = $this->context($chunks);
        $messages = [
            ['role' => 'system', 'content' => $this->prompt($bot, $context)],
            ['role' => 'user', 'content' => $userMessage],
        ];

        try {
            $stream = $this->openAI->streamChatCompletion($bot->tenant, $messages, [
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'temperature' => (float) $bot->temperature,
            ]);

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield $this->streamEvent('text_delta', ['delta' => $event->delta]);
                }
            }

            $answer = trim($stream->text ?? '') ?: $bot->fallback_message;
        } catch (Throwable) {
            $fallback = $this->fallback($bot, $conversation, $userMessage, 'Chat completion failed.');

            yield $this->streamEvent('text_delta', ['delta' => $fallback['message']]);
            yield $this->streamEvent('meta', ['fallback' => true, 'sources' => []]);
            yield $this->streamEvent('done');

            return;
        }

        $assistant = $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => ['source_chunk_ids' => $chunks->pluck('id')->all()],
        ]);

        RetrievalLog::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'chat_message_id' => $assistant->id,
            'query' => $userMessage,
            'selected_chunk_ids' => $chunks->pluck('id')->all(),
            'distances' => $chunks->mapWithKeys(fn ($chunk) => [$chunk->id => $chunk->distance])->all(),
            'context_text' => $context,
        ]);

        yield $this->streamEvent('meta', [
            'fallback' => false,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'distance' => $chunk->distance,
            ])->values(),
        ]);
        yield $this->streamEvent('done');
    }

    private function chunks(Bot $bot, ChatConversation $conversation, string $userMessage): mixed
    {
        try {
            return $this->search->search($bot, $userMessage);
        } catch (Throwable) {
            return $this->fallback($bot, $conversation, $userMessage, 'AI settings are unavailable.');
        }
    }

    private function context($chunks): ?string
    {
        if ($chunks->isEmpty()) {
            return null;
        }

        return $chunks->map(fn ($chunk) => "[Source: {$chunk->metadata['source_title']}, Chunk ID: {$chunk->id}]\n{$chunk->content}")->implode("\n\n");
    }

    private function streamEvent(string $type, array $payload = []): string
    {
        return 'data: '.json_encode(['type' => $type, ...$payload])."\n\n";
    }

    private function fallback(Bot $bot, ChatConversation $conversation, string $query, string $reason): array
    {
        $conversation->update(['status' => 'escalated']);

        $assistant = $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'assistant',
            'content' => $bot->fallback_message,
            'metadata' => ['fallback_reason' => $reason],
        ]);

        RetrievalLog::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'chat_message_id' => $assistant->id,
            'query' => $query,
            'selected_chunk_ids' => [],
            'distances' => [],
            'context_text' => null,
        ]);

        return ['message' => $bot->fallback_message, 'fallback' => true, 'sources' => []];
    }

    private function prompt(Bot $bot, ?string $context): string
    {
        if ($context === null) {
            return <<<PROMPT
You are a CRM support assistant for {$bot->name}.

Rules:
- No matching knowledge base context was found for this message.
- You may answer greetings, thanks, short conversational replies, and general product-neutral questions.
- For business-specific questions, pricing, policies, workflows, payment rules, legal/medical advice, or account-specific facts, say you do not have enough information and suggest contacting support.
- Keep the answer clear and practical.
PROMPT;
        }

        return <<<PROMPT
You are a CRM support assistant for {$bot->name}.

Rules:
- Use only the provided knowledge base context.
- If the context does not contain the answer, say you do not know.
- Do not invent CRM workflows, policies, pricing, payment rules, legal/medical advice, or account-specific facts.
- Keep the answer clear and practical.
- If the user needs help from staff, suggest contacting support.

CONTEXT:
{$context}
PROMPT;
    }
}
