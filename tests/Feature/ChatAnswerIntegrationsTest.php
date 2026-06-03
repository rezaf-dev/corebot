<?php

use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use App\Services\Integrations\IntegrationToolFactory;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SearchResult;
use App\Services\Rag\SemanticSearchService;
use Illuminate\Support\Collection;

it('passes enabled integration tools to the chat completion', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-chat-tools', 'status' => 'active']);
    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'name' => 'Save lead',
        'enabled' => true,
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $this->mock(SemanticSearchService::class)
        ->shouldReceive('searchWithMeta')
        ->once()
        ->andReturn(new SearchResult(new Collection, confident: true));

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->withArgs(function ($passedTenant, array $messages, array $options) use ($tenant) {
            return $passedTenant->is($tenant)
                && str_contains($messages[0]['content'], 'CRM support assistant')
                && count($options['tools'] ?? []) === 1
                && str_starts_with($options['tools'][0]->name(), 'integration_');
        })
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'Done.']],
            ],
        ]);

    $response = app(ChatAnswerService::class)->answer(
        $bot->load(['tenant.aiSetting', 'integrations']),
        $conversation,
        'Please save my email alex@example.com',
    );

    expect($response['message'])->toBe('Done.');
});

it('builds integration tools via factory', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-tool-factory', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $integration = BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'enabled' => true,
    ]);
    BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'enabled' => false,
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $tools = app(IntegrationToolFactory::class)->forConversation($bot->load('integrations'), $conversation);

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name())->toBe($integration->toolName());
});
