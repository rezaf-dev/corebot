<?php

use App\Ai\Agents\TenantChatAgent;
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

function tenantWithAiSettings(): Tenant
{
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-ai', 'status' => 'active']);

    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-test'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);

    return $tenant->load('aiSetting');
}
