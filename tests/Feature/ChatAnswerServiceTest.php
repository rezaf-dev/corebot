<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SemanticSearchService;
use Illuminate\Support\Collection;

it('uses the llm for general replies when no chunks are available', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-chat-answer', 'status' => 'active']);
    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $this->mock(SemanticSearchService::class)
        ->shouldReceive('search')
        ->once()
        ->andReturn(new Collection);

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->withArgs(fn ($tenant, array $messages) => $tenant->is($bot->tenant)
            && str_contains($messages[0]['content'], 'No matching knowledge base context was found')
            && $messages[1]['content'] === 'hello')
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'Hi, how can I help?']],
            ],
        ]);

    $response = app(ChatAnswerService::class)->answer($bot->load('tenant.aiSetting'), $conversation, 'hello');

    expect($response['message'])->toBe('Hi, how can I help?')
        ->and($response['fallback'])->toBeFalse()
        ->and($response['sources'])->toBeEmpty();
});
