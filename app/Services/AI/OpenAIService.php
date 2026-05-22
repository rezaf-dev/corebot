<?php

namespace App\Services\AI;

use App\Ai\Agents\TenantChatAgent;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Throwable;

class OpenAIService
{
    public function createEmbedding(Tenant $tenant, string $text, array $context = []): array
    {
        $settings = $this->usableSettings($tenant);
        $this->configureOpenAiProvider($settings);

        try {
            $response = Embeddings::for([$text])
                ->timeout(45)
                ->generate('openai', $settings->embedding_model);

            $this->logUsage($tenant, 'embedding', $response->meta->model ?? $settings->embedding_model, [
                'prompt_tokens' => $response->tokens,
                'total_tokens' => $response->tokens,
            ], $context);

            return $response->first();
        } catch (Throwable $e) {
            $this->logUsage($tenant, 'embedding', $settings->embedding_model, [], $context, $e->getMessage());
            throw new RuntimeException('Embedding generation failed.');
        }
    }

    public function createChatCompletion(Tenant $tenant, array $messages, array $options = []): array
    {
        $settings = $this->usableSettings($tenant);
        $model = $options['model'] ?? $settings->chat_model;
        $this->configureOpenAiProvider($settings);

        try {
            $response = $this->prompt($messages, (float) ($options['temperature'] ?? 0.2))
                ->prompt(
                    $this->currentPrompt($messages),
                    provider: 'openai',
                    model: $model,
                    timeout: 60,
                );

            $this->logAgentUsage($tenant, 'chat', $response, $options);

            return [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $response->text,
                        ],
                    ],
                ],
                'usage' => $response->usage->toArray(),
            ];
        } catch (Throwable $e) {
            $this->logUsage($tenant, 'chat', $model, [], $options, $e->getMessage());
            throw new RuntimeException('Chat completion failed.');
        }
    }

    public function streamChatCompletion(Tenant $tenant, array $messages, array $options = []): StreamableAgentResponse
    {
        $settings = $this->usableSettings($tenant);
        $model = $options['model'] ?? $settings->chat_model;
        $this->configureOpenAiProvider($settings);

        try {
            return $this->prompt($messages, (float) ($options['temperature'] ?? 0.2))
                ->stream(
                    $this->currentPrompt($messages),
                    provider: 'openai',
                    model: $model,
                    timeout: 60,
                )
                ->then(fn ($response) => $this->logAgentUsage($tenant, 'chat', $response, $options));
        } catch (Throwable $e) {
            $this->logUsage($tenant, 'chat', $model, [], $options, $e->getMessage());
            throw new RuntimeException('Chat completion failed.');
        }
    }

    public function testApiKey(Tenant $tenant): bool
    {
        $settings = $tenant->aiSetting;

        if (! $settings || ! $settings->api_key) {
            throw new RuntimeException('API key is missing.');
        }

        $this->configureOpenAiProvider($settings);

        Embeddings::for(['connection test'])
            ->timeout(20)
            ->generate('openai', $settings->embedding_model);

        return true;
    }

    private function prompt(array $messages, float $temperature): TenantChatAgent
    {
        return new TenantChatAgent(
            instructions: $this->instructions($messages),
            messages: $this->contextMessages($messages),
            temperature: $temperature,
        );
    }

    private function instructions(array $messages): string
    {
        return collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->filter()
            ->implode("\n\n");
    }

    private function currentPrompt(array $messages): string
    {
        return collect($messages)
            ->where('role', 'user')
            ->last()['content'] ?? '';
    }

    /**
     * @return Message[]
     */
    private function contextMessages(array $messages): array
    {
        $currentUserIndex = collect($messages)->keys()->reverse()->first(
            fn ($index) => ($messages[$index]['role'] ?? null) === 'user'
        );

        return collect($messages)
            ->reject(fn (array $message, int $index) => ($message['role'] ?? null) === 'system' || $index === $currentUserIndex)
            ->map(fn (array $message) => new Message($message['role'], $message['content']))
            ->values()
            ->all();
    }

    private function usableSettings(Tenant $tenant)
    {
        $settings = $tenant->aiSetting;

        if (! $settings || ! $settings->canUseAi()) {
            throw new RuntimeException('Tenant AI settings are not active.');
        }

        return $settings;
    }

    private function configureOpenAiProvider($settings): void
    {
        config([
            'ai.providers.openai.key' => $settings->api_key,
            'ai.providers.openai.url' => rtrim($settings->base_url, '/'),
            'ai.providers.openai.models.text.default' => $settings->chat_model,
            'ai.providers.openai.models.embeddings.default' => $settings->embedding_model,
        ]);

        Ai::forgetInstance('openai');
    }

    private function logAgentUsage(Tenant $tenant, string $type, AgentResponse $response, array $context = []): void
    {
        $usage = $response->usage->toArray();
        $usage['total_tokens'] = ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0);

        $this->logUsage($tenant, $type, $response->meta->model ?? $context['model'] ?? 'unknown', $usage, $context);
    }

    private function logUsage(Tenant $tenant, string $type, string $model, array $usage, array $context = [], ?string $error = null): void
    {
        AiUsageLog::create([
            'tenant_id' => $tenant->id,
            'bot_id' => $context['bot_id'] ?? null,
            'conversation_id' => $context['conversation_id'] ?? null,
            'knowledge_source_id' => $context['knowledge_source_id'] ?? null,
            'type' => $type,
            'provider' => 'openai',
            'model' => $model,
            'input_tokens' => $usage['prompt_tokens'] ?? null,
            'output_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'error_message' => $error ? str($error)->limit(500)->toString() : null,
        ]);
    }
}
