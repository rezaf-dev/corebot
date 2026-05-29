<?php

namespace App\Services\Rag;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\RetrievalLog;
use App\Services\AI\OpenAIService;
use App\Support\BotContactConfig;
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
        $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $retrieval = $this->retrieve($bot, $conversation, $userMessage);

        if (is_array($retrieval)) {
            return $retrieval;
        }

        $chunks = $retrieval->chunks;
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

        return $this->withContactMeta($bot, $conversation, [
            'message' => $answer,
            'fallback' => false,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'distance' => $chunk->distance,
            ])->values(),
        ], $retrieval->confident);
    }

    public function stream(Bot $bot, ChatConversation $conversation, string $userMessage): Generator
    {
        $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $retrieval = $this->retrieve($bot, $conversation, $userMessage);

        if (is_array($retrieval)) {
            yield $this->streamEvent('text_delta', ['delta' => $retrieval['message']]);
            yield $this->streamEvent('meta', [
                'fallback' => true,
                'needs_contact' => $retrieval['needs_contact'],
                'sources' => [],
            ]);
            yield $this->streamEvent('done');

            return;
        }

        $chunks = $retrieval->chunks;
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
            yield $this->streamEvent('meta', [
                'fallback' => true,
                'needs_contact' => $fallback['needs_contact'],
                'sources' => [],
            ]);
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

        $meta = $this->withContactMeta($bot, $conversation, [
            'fallback' => false,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'distance' => $chunk->distance,
            ])->values(),
        ], $retrieval->confident);

        yield $this->streamEvent('meta', [
            'fallback' => $meta['fallback'],
            'needs_contact' => $meta['needs_contact'],
            'sources' => $meta['sources'],
        ]);
        yield $this->streamEvent('done');
    }

    private function retrieve(Bot $bot, ChatConversation $conversation, string $userMessage): array|SearchResult
    {
        try {
            return $this->search->searchWithMeta($bot, $userMessage);
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

        return $this->withContactMeta($bot, $conversation, [
            'message' => $bot->fallback_message,
            'fallback' => true,
            'sources' => [],
        ], confident: false);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function withContactMeta(Bot $bot, ChatConversation $conversation, array $response, bool $confident): array
    {
        $needsContact = $this->shouldRequestContact($bot, $conversation, $confident);

        if ($needsContact) {
            $conversation->update(['status' => 'escalated']);
        }

        $response['needs_contact'] = $needsContact;

        return $response;
    }

    private function shouldRequestContact(Bot $bot, ChatConversation $conversation, bool $confident): bool
    {
        if (BotContactConfig::fields($bot) === []) {
            return false;
        }

        if (BotContactConfig::hasCompleteContact($bot, $conversation)) {
            return false;
        }

        return ! $confident;
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
