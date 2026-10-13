<?php

namespace App\Services\Rag;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\RetrievalLog;
use App\Services\AI\OpenAIService;
use App\Services\Conversations\ManualHandoffNotifier;
use App\Services\Conversations\PotentialCustomerDetector;
use App\Services\Integrations\IntegrationToolFactory;
use App\Support\BotContactConfig;
use App\Support\Localization;
use Generator;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

use function Illuminate\Support\defer;

class ChatAnswerService
{
    public function __construct(
        private readonly Localization $localization,
        private readonly SemanticSearchService $search,
        private readonly OpenAIService $openAI,
        private readonly IntegrationToolFactory $integrationTools,
        private readonly ManualHandoffNotifier $manualHandoffNotifier,
        private readonly PotentialCustomerDetector $potentialCustomerDetector,
    ) {}

    public function answer(Bot $bot, ChatConversation $conversation, string $userMessage, array $pageContext = []): array
    {
        $this->receiveVisitorMessage($bot, $conversation, $userMessage);

        if ($conversation->support_mode === 'human') {
            return $this->humanResponse();
        }

        $retrieval = $this->retrieve($bot, $conversation, $userMessage, $pageContext);

        if (is_array($retrieval)) {
            return $retrieval;
        }

        $chunks = $retrieval->chunks;
        $context = $this->context($chunks);
        $messages = $this->buildMessages($bot, $conversation, $userMessage, $context);

        try {
            $response = $this->openAI->createChatCompletion($bot->tenant, $messages, $this->chatOptions($bot, $conversation));

            $answer = trim($response['choices'][0]['message']['content'] ?? '') ?: $this->fallbackMessage($bot, $conversation);
        } catch (Throwable) {
            return $this->fallback($bot, $conversation, $userMessage, 'Chat completion failed.');
        }

        $assistant = $this->publishAssistant($bot, $conversation, $answer, ['source_chunk_ids' => $chunks->pluck('id')->all()]);
        if (! $assistant) {
            return $this->humanResponse();
        }

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

        $handoffReason = $this->handoffReason($userMessage, $answer);

        return $this->withContactMeta($bot, $conversation, [
            'message' => $answer,
            'fallback' => false,
            'chat_message_id' => $assistant->id,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'url' => $chunk->metadata['source_url'] ?? null,
                'distance' => $chunk->distance,
            ])->values(),
        ], $handoffReason);
    }

    public function stream(Bot $bot, ChatConversation $conversation, string $userMessage, array $pageContext = []): Generator
    {
        $this->receiveVisitorMessage($bot, $conversation, $userMessage);

        if ($conversation->support_mode === 'human') {
            yield $this->streamEvent('meta', $this->humanResponse());
            yield $this->streamEvent('done');

            return;
        }

        $retrieval = $this->retrieve($bot, $conversation, $userMessage, $pageContext);

        if (is_array($retrieval)) {
            if (! ($retrieval['human_support'] ?? false)) {
                yield $this->streamEvent('text_delta', ['delta' => $retrieval['message']]);
            }
            yield $this->streamEvent('meta', [
                'human_support' => $retrieval['human_support'] ?? false,
                'fallback' => true,
                'needs_contact' => $retrieval['needs_contact'],
                'chat_message_id' => $retrieval['chat_message_id'],
                'sources' => [],
            ]);
            yield $this->streamEvent('done');

            return;
        }

        $chunks = $retrieval->chunks;
        $context = $this->context($chunks);
        $messages = $this->buildMessages($bot, $conversation, $userMessage, $context);

        $deliveredText = '';

        try {
            $stream = $this->openAI->streamChatCompletion($bot->tenant, $messages, $this->chatOptions($bot, $conversation));

            foreach ($stream as $event) {
                if ($conversation->fresh()->support_mode === 'human') {
                    $this->recordDeliveredText($bot, $conversation, $deliveredText);
                    yield $this->streamEvent('meta', $this->humanResponse());
                    yield $this->streamEvent('done');

                    return;
                }
                if ($event instanceof TextDelta) {
                    $deliveredText .= $event->delta;
                    yield $this->streamEvent('text_delta', ['delta' => $event->delta]);
                }
            }

            $answer = trim($stream->text ?? '') ?: $this->fallbackMessage($bot, $conversation);
        } catch (Throwable) {
            $fallback = $this->fallback($bot, $conversation, $userMessage, 'Chat completion failed.');

            if ($fallback['human_support'] ?? false) {
                $this->recordDeliveredText($bot, $conversation, $deliveredText);
            }
            if (! ($fallback['human_support'] ?? false)) {
                yield $this->streamEvent('text_delta', ['delta' => $fallback['message']]);
            }
            yield $this->streamEvent('meta', [
                'human_support' => $fallback['human_support'] ?? false,
                'fallback' => true,
                'needs_contact' => $fallback['needs_contact'],
                'chat_message_id' => $fallback['chat_message_id'],
                'sources' => [],
            ]);
            yield $this->streamEvent('done');

            return;
        }

        $assistant = $this->publishAssistant($bot, $conversation, $answer, ['source_chunk_ids' => $chunks->pluck('id')->all()]);
        if (! $assistant) {
            $this->recordDeliveredText($bot, $conversation, $deliveredText);
            yield $this->streamEvent('meta', $this->humanResponse());
            yield $this->streamEvent('done');

            return;
        }

        $retrievalLog = [
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'chat_message_id' => $assistant->id,
            'query' => $userMessage,
            'selected_chunk_ids' => $chunks->pluck('id')->all(),
            'distances' => $chunks->mapWithKeys(fn ($chunk) => [$chunk->id => $chunk->distance])->all(),
            'context_text' => $context,
        ];

        defer(fn () => RetrievalLog::create($retrievalLog));

        $meta = $this->withContactMeta($bot, $conversation, [
            'fallback' => false,
            'chat_message_id' => $assistant->id,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'title' => $chunk->metadata['source_title'] ?? 'Knowledge source',
                'url' => $chunk->metadata['source_url'] ?? null,
                'distance' => $chunk->distance,
            ])->values(),
        ], $this->handoffReason($userMessage, $answer));

        yield $this->streamEvent('meta', [
            'fallback' => $meta['fallback'],
            'needs_contact' => $meta['needs_contact'],
            'chat_message_id' => $meta['chat_message_id'],
            'sources' => $meta['sources'],
        ]);
        yield $this->streamEvent('done');
    }

    private function receiveVisitorMessage(Bot $bot, ChatConversation $conversation, string $message): void
    {
        DB::transaction(function () use ($bot, $conversation, $message): void {
            $current = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $current->messages()->create([
                'tenant_id' => $bot->tenant_id, 'bot_id' => $bot->id, 'role' => 'user', 'content' => $message,
            ]);
            $current->update(['status' => $current->status === 'resolved' ? 'open' : $current->status, 'resolved_at' => null, 'updated_at' => now()]);
            $conversation->setRawAttributes($current->getAttributes(), true);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function publishAssistant(Bot $bot, ChatConversation $conversation, string $answer, array $metadata): ?ChatMessage
    {
        return DB::transaction(function () use ($bot, $conversation, $answer, $metadata): ?ChatMessage {
            $current = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($current->support_mode === 'human') {
                return null;
            }
            $message = $current->messages()->create([
                'tenant_id' => $bot->tenant_id, 'bot_id' => $bot->id, 'role' => 'assistant', 'content' => $answer, 'metadata' => $metadata,
            ]);
            $current->touch();

            return $message;
        });
    }

    private function recordDeliveredText(Bot $bot, ChatConversation $conversation, string $text): void
    {
        if (trim($text) === '') {
            return;
        }
        $conversation->messages()->create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'role' => 'assistant',
            'content' => $text,
            'metadata' => ['interrupted_by_handoff' => true],
        ]);
        $conversation->touch();
    }

    /** @return array<string, mixed> */
    private function humanResponse(): array
    {
        return ['human_support' => true, 'support_mode' => 'human', 'message' => null, 'chat_message_id' => null, 'needs_contact' => false, 'fallback' => false, 'sources' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function chatOptions(Bot $bot, ChatConversation $conversation): array
    {
        return [
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'temperature' => (float) $bot->temperature,
            'tools' => $this->integrationTools->forConversation($bot, $conversation),
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(Bot $bot, ChatConversation $conversation, string $userMessage, ?string $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->prompt($bot, $conversation, $context)],
        ];

        $history = $conversation->messages()
            ->select(['role', 'content'])
            ->orderByDesc('id')
            ->limit(max(0, (int) config('rag.history_message_limit')) + 1)
            ->get()
            ->slice(1)
            ->reverse()
            ->values();

        foreach ($history as $message) {
            if (! in_array($message->role, ['user', 'assistant'], true)) {
                continue;
            }

            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    private function retrieve(Bot $bot, ChatConversation $conversation, string $userMessage, array $pageContext): array|SearchResult
    {
        try {
            return $this->search->searchWithMeta($bot, $this->pageAwareQuery($userMessage, $pageContext));
        } catch (Throwable) {
            return $this->fallback($bot, $conversation, $userMessage, 'AI settings are unavailable.');
        }
    }

    /**
     * @param  array{url?: string, title?: string}  $pageContext
     */
    private function pageAwareQuery(string $userMessage, array $pageContext): string
    {
        if (! isset($pageContext['url'])) {
            return $userMessage;
        }

        $title = isset($pageContext['title']) ? "\nPage title: {$pageContext['title']}" : '';

        return "{$userMessage}\n\nCurrent page URL: {$pageContext['url']}{$title}";
    }

    private function context($chunks): ?string
    {
        if ($chunks->isEmpty()) {
            return null;
        }

        return $chunks->map(function ($chunk): string {
            $sourceUrl = filled($chunk->metadata['source_url'] ?? null)
                ? ", Source URL: {$chunk->metadata['source_url']}"
                : '';

            return "[Source: {$chunk->metadata['source_title']}, Chunk ID: {$chunk->id}{$sourceUrl}]\n{$chunk->content}";
        })->implode("\n\n");
    }

    private function streamEvent(string $type, array $payload = []): string
    {
        return 'data: '.json_encode(['type' => $type, ...$payload])."\n\n";
    }

    private function fallback(Bot $bot, ChatConversation $conversation, string $query, string $reason): array
    {
        return DB::transaction(function () use ($bot, $conversation, $query, $reason): array {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($conversation->support_mode === 'human') {
                return $this->humanResponse();
            }

            $this->manualHandoffNotifier->escalate($bot, $conversation);
            $message = $this->fallbackMessage($bot, $conversation);

            $assistant = $conversation->messages()->create([
                'tenant_id' => $bot->tenant_id,
                'bot_id' => $bot->id,
                'role' => 'assistant',
                'content' => $message,
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
                'message' => $message,
                'fallback' => true,
                'chat_message_id' => $assistant->id,
                'sources' => [],
            ], 'unable_to_answer');
        });
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function withContactMeta(Bot $bot, ChatConversation $conversation, array $response, ?string $handoffReason): array
    {
        $needsContact = $this->shouldRequestContact($bot, $conversation, $handoffReason);

        if ($handoffReason !== null) {
            $this->manualHandoffNotifier->escalate($bot, $conversation);
            $conversation->update(['handoff_reason' => $handoffReason]);
        }

        $response['needs_contact'] = $needsContact;

        return $response;
    }

    private function shouldRequestContact(Bot $bot, ChatConversation $conversation, ?string $handoffReason): bool
    {
        if (BotContactConfig::fields($bot) === []) {
            return false;
        }

        if (BotContactConfig::hasCompleteContact($bot, $conversation)) {
            return false;
        }

        return $handoffReason !== null;
    }

    private function handoffReason(string $userMessage, string $answer): ?string
    {
        if (preg_match('/\b(human|person|agent|representative|support team|call me|talk to support)\b/i', $userMessage)) {
            return 'human_requested';
        }

        if ($this->potentialCustomerDetector->isPotentialCustomer($userMessage)) {
            return 'potential_customer';
        }

        if (preg_match('/\b(i (do not|don\'t) know|cannot answer|can\'t answer|unable to answer|no information)\b/i', $answer)) {
            return 'unable_to_answer';
        }

        return null;
    }

    private function prompt(Bot $bot, ChatConversation $conversation, ?string $context): string
    {
        $base = trim((string) $bot->system_prompt) ?: Bot::DEFAULT_SYSTEM_PROMPT;
        $companyName = trim((string) $bot->tenant->name);
        $toolInstructions = $this->integrationToolInstructions($bot);
        $locale = $this->localization->widgetLocalization($conversation->language, $bot)['locale'];
        $languageName = config('localization.languages.'.$locale.'.name');
        $base .= "\n\nVisitor language: {$languageName} ({$locale}). Respond in this language, including when the knowledge base is written in another language.";

        $identityInstructions = <<<PROMPT

Identity and voice:
- You are a customer support representative for {$companyName}. Speak as a member of {$companyName}, not as an outside observer.
- Refer to {$companyName} with first-person language such as "we", "our", and "us" when appropriate.
- Never describe {$companyName} as "this company" or refer to it as "they" or "them".
- Answer the visitor directly. Do not begin with phrases such as "Based on the provided context" or mention the knowledge base, sources, or supplied context.
PROMPT;

        if ($context === null) {
            return trim(<<<PROMPT
{$base}
{$identityInstructions}

Additional rules for this turn:
- No matching knowledge base context was found for this message.
- You may answer greetings, thanks, short conversational replies, and general product-neutral questions.
- For business-specific questions, pricing, policies, workflows, payment rules, legal/medical advice, or account-specific facts, say you do not have enough information and suggest contacting support.
{$toolInstructions}
PROMPT);
        }

        return trim(<<<PROMPT
{$base}
{$identityInstructions}

Additional rules for this turn:
- Use only the provided knowledge base context below.
- If the context does not contain the answer, say you do not know.
- When the context includes a relevant page or download URL, include a descriptive clickable Markdown link to help the visitor complete their task.
- Never invent or alter a URL. Only use URLs that appear in the context.
- If the user needs help from staff, suggest contacting support.

CONTEXT:
{$context}
{$toolInstructions}
PROMPT);
    }

    private function fallbackMessage(Bot $bot, ChatConversation $conversation): string
    {
        $locale = $this->localization->widgetLocalization($conversation->language, $bot)['locale'];

        return $this->localization->widgetConfig($bot, $locale)['fallback_message'];
    }

    private function integrationToolInstructions(Bot $bot): string
    {
        $bot->loadMissing('integrations');

        $enabled = $bot->integrations->where('enabled', true);

        if ($enabled->isEmpty()) {
            return '';
        }

        $lines = $enabled->map(fn ($integration) => "- {$integration->toolName()}: {$integration->description}")->implode("\n");

        return <<<PROMPT

Integration tools (call only when the visitor clearly wants an action performed):
{$lines}
- Never invent contact details for lead capture; ask the visitor for missing required fields first.
- Prefer suggesting the contact form when unsure instead of calling tools.
PROMPT;
    }
}
