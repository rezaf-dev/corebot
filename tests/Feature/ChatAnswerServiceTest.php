<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SearchResult;
use App\Services\Rag\SemanticSearchService;
use Illuminate\Support\Collection;

it('uses the llm for general replies when no chunks are available', function () {
    $tenant = Tenant::create(['name' => 'NTR Chemical', 'slug' => 'demo-chat-answer', 'status' => 'active']);
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
        ->shouldReceive('searchWithMeta')
        ->once()
        ->andReturn(new SearchResult(new Collection, confident: false));

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->withArgs(fn ($tenant, array $messages) => $tenant->is($bot->tenant)
            && str_contains($messages[0]['content'], 'No matching knowledge base context was found')
            && str_contains($messages[0]['content'], 'You are a customer support representative for NTR Chemical')
            && str_contains($messages[0]['content'], 'first-person language such as "we", "our", and "us"')
            && str_contains($messages[0]['content'], 'Do not begin with phrases such as "Based on the provided context"')
            && $messages[1]['content'] === 'hello')
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'Hi, how can I help?']],
            ],
        ]);

    $response = app(ChatAnswerService::class)->answer($bot->load('tenant.aiSetting'), $conversation, 'hello');

    expect($response['message'])->toBe('Hi, how can I help?')
        ->and($response['fallback'])->toBeFalse()
        ->and($response['needs_contact'])->toBeTrue()
        ->and($response['sources'])->toBeEmpty();

    expect($conversation->fresh()->status)->toBe('escalated');
});

it('does not request contact when retrieval is confident', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-chat-confident', 'status' => 'active']);
    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support',
        'contact_fields' => ['name', 'email'],
        'contact_required' => ['email'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $chunk = (object) [
        'id' => 1,
        'distance' => 0.1,
        'metadata' => ['source_title' => 'Website help', 'source_url' => 'https://example.com/help'],
        'content' => 'Download the guide: [Guide](https://example.com/guide.pdf)',
    ];

    $this->mock(SemanticSearchService::class)
        ->shouldReceive('searchWithMeta')
        ->once()
        ->andReturn(new SearchResult(collect([$chunk]), confident: true));

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->withArgs(fn ($tenant, array $messages) => str_contains($messages[0]['content'], 'Speak as a member of Demo, not as an outside observer.')
            && str_contains($messages[0]['content'], 'Source URL: https://example.com/help')
            && str_contains($messages[0]['content'], '[Guide](https://example.com/guide.pdf)')
            && str_contains($messages[0]['content'], 'include a descriptive clickable Markdown link'))
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'Here is the answer.']],
            ],
        ]);

    $response = app(ChatAnswerService::class)->answer($bot->load('tenant.aiSetting'), $conversation, 'pricing?');

    expect($response['needs_contact'])->toBeFalse()
        ->and($response['sources'][0]['url'])->toBe('https://example.com/help')
        ->and($conversation->fresh()->status)->toBe('open');
});

it('does not request contact when required fields are already present', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-chat-has-contact', 'status' => 'active']);
    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support',
        'contact_fields' => ['email'],
        'contact_required' => ['email'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'visitor_email' => 'visitor@example.com',
        'status' => 'open',
    ]);

    $this->mock(SemanticSearchService::class)
        ->shouldReceive('searchWithMeta')
        ->once()
        ->andReturn(new SearchResult(collect(), confident: false));

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'I am not sure.']],
            ],
        ]);

    $response = app(ChatAnswerService::class)->answer($bot->load('tenant.aiSetting'), $conversation, 'custom plan?');

    expect($response['needs_contact'])->toBeFalse();
});

it('limits conversation history sent to the model', function () {
    config(['rag.history_message_limit' => 2]);

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'history-limit', 'status' => 'active']);
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

    foreach ([
        ['user', 'old question'],
        ['assistant', 'old answer'],
        ['user', 'recent question'],
        ['assistant', 'recent answer'],
    ] as [$role, $content]) {
        $conversation->messages()->create([
            'tenant_id' => $tenant->id,
            'bot_id' => $bot->id,
            'role' => $role,
            'content' => $content,
        ]);
    }

    $this->mock(SemanticSearchService::class)
        ->shouldReceive('searchWithMeta')
        ->once()
        ->andReturn(new SearchResult(collect(), confident: false));

    $this->mock(OpenAIService::class)
        ->shouldReceive('createChatCompletion')
        ->once()
        ->withArgs(function ($tenant, array $messages): bool {
            $contents = collect($messages)->pluck('content');

            return $contents->contains('recent question')
                && $contents->contains('recent answer')
                && $contents->contains('current question')
                && ! $contents->contains('old question')
                && ! $contents->contains('old answer');
        })
        ->andReturn([
            'choices' => [
                ['message' => ['content' => 'Current answer']],
            ],
        ]);

    app(ChatAnswerService::class)->answer(
        $bot->load('tenant.aiSetting'),
        $conversation,
        'current question',
    );
});
