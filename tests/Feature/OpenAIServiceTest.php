<?php

use App\Ai\Agents\TenantChatAgent;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Crypt;
use Laravel\Ai\Embeddings;

it('generates embeddings through laravel ai', function () {
    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ])->preventStrayEmbeddings();

    $tenant = tenantWithAiSettings();

    $embedding = app(OpenAIService::class)->createEmbedding($tenant, 'How do I create a lead?');

    expect($embedding)->toBe([0.1, 0.2, 0.3]);

    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('How do I create a lead?'));
});

it('generates chat completions through laravel ai', function () {
    TenantChatAgent::fake(['Hello from Laravel AI.'])->preventStrayPrompts();

    $tenant = tenantWithAiSettings();

    $response = app(OpenAIService::class)->createChatCompletion($tenant, [
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Hello'],
    ]);

    expect($response['choices'][0]['message']['content'])->toBe('Hello from Laravel AI.');

    TenantChatAgent::assertPrompted('Hello');
});

it('configures and logs the openrouter provider', function () {
    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ])->preventStrayEmbeddings();

    $tenant = tenantWithAiSettings('openrouter');

    app(OpenAIService::class)->createEmbedding($tenant, 'OpenRouter embedding');

    expect(config('ai.providers.openrouter.key'))->toBe('sk-test')
        ->and(config('ai.providers.openrouter.url'))->toBe('https://openrouter.ai/api/v1')
        ->and(config('ai.providers.openrouter.models.embeddings.default'))->toBe('openai/text-embedding-3-small')
        ->and(AiUsageLog::query()->latest('id')->value('provider'))->toBe('openrouter');
});

it('tests both openrouter chat and embeddings before activation', function () {
    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ])->preventStrayEmbeddings();
    TenantChatAgent::fake(['OK'])->preventStrayPrompts();

    $tenant = tenantWithAiSettings('openrouter');

    expect(app(OpenAIService::class)->testApiKey($tenant))->toBeTrue();

    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('connection test'));
    TenantChatAgent::assertPrompted('Reply with OK.');
});

function tenantWithAiSettings(string $provider = 'openai'): Tenant
{
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-ai-'.uniqid(), 'status' => 'active']);

    $tenant->aiSetting()->create([
        'provider' => $provider,
        'api_key_encrypted' => Crypt::encryptString('sk-test'),
        'base_url' => $provider === 'openrouter' ? 'https://openrouter.ai/api/v1' : 'https://api.openai.com/v1',
        'chat_model' => $provider === 'openrouter' ? 'openai/gpt-4o-mini' : 'gpt-4o-mini',
        'embedding_model' => $provider === 'openrouter' ? 'openai/text-embedding-3-small' : 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'is_active' => true,
    ]);

    return $tenant->load('aiSetting');
}
