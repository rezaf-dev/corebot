<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Models\Tenant;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

class OpenAIService
{
    public function __construct(private readonly HttpFactory $http) {}

    public function createEmbedding(Tenant $tenant, string $text, array $context = []): array
    {
        $settings = $this->usableSettings($tenant);

        try {
            $response = $this->http->withToken($settings->api_key)
                ->baseUrl(rtrim($settings->base_url, '/'))
                ->timeout(45)
                ->post('/embeddings', [
                    'model' => $settings->embedding_model,
                    'input' => $text,
                ])
                ->throw()
                ->json();

            $this->logUsage($tenant, 'embedding', $settings->embedding_model, $response['usage'] ?? [], $context);

            return $response['data'][0]['embedding'] ?? throw new RuntimeException('OpenAI did not return an embedding.');
        } catch (Throwable $e) {
            $this->logUsage($tenant, 'embedding', $settings->embedding_model, [], $context, $e->getMessage());
            throw new RuntimeException('Embedding generation failed.');
        }
    }

    public function createChatCompletion(Tenant $tenant, array $messages, array $options = []): array
    {
        $settings = $this->usableSettings($tenant);
        $model = $options['model'] ?? $settings->chat_model;

        try {
            $response = $this->http->withToken($settings->api_key)
                ->baseUrl(rtrim($settings->base_url, '/'))
                ->timeout(60)
                ->post('/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.2,
                ])
                ->throw()
                ->json();

            $this->logUsage($tenant, 'chat', $model, $response['usage'] ?? [], $options);

            return $response;
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

        $this->http->withToken($settings->api_key)
            ->baseUrl(rtrim($settings->base_url, '/'))
            ->timeout(20)
            ->post('/embeddings', [
                'model' => $settings->embedding_model,
                'input' => 'connection test',
            ])
            ->throw();

        return true;
    }

    private function usableSettings(Tenant $tenant)
    {
        $settings = $tenant->aiSetting;

        if (! $settings || ! $settings->canUseAi()) {
            throw new RuntimeException('Tenant AI settings are not active.');
        }

        return $settings;
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
