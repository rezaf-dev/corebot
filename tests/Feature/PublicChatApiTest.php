<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\Rag\ChatAnswerService;

it('rejects public chat for inactive bots', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot', 'status' => 'inactive']);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-1',
        'source_url' => 'https://example.com/help',
    ])->assertNotFound();
});

it('validates allowed domains', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-2', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot', 'allowed_domains' => ['allowed.test']]);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-1',
        'source_url' => 'https://blocked.test/help',
    ])->assertForbidden();
});

it('streams public chat messages', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-3', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'visitor_id' => 'visitor-1',
        'status' => 'open',
    ]);

    $this->mock(ChatAnswerService::class)
        ->shouldReceive('stream')
        ->once()
        ->withArgs(fn (Bot $receivedBot, ChatConversation $receivedConversation, string $message, array $page) => $receivedBot->is($bot)
            && $receivedConversation->is($conversation)
            && $message === 'Hello'
            && $page === [
                'url' => 'https://example.com/pricing',
                'title' => 'Pricing',
            ])
        ->andReturn((function () {
            yield 'data: {"type":"text_delta","delta":"Hello"}'."\n\n";
            yield 'data: {"type":"done"}'."\n\n";
        })());

    $response = $this->post('/api/public/chat/message/stream', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'message' => 'Hello',
        'page' => [
            'url' => 'https://example.com/pricing',
            'title' => 'Pricing',
        ],
    ]);

    $response->assertStreamed()
        ->assertHeader('X-Accel-Buffering', 'no')
        ->assertStreamedContent('data: {"type":"text_delta","delta":"Hello"}'."\n\n".'data: {"type":"done"}'."\n\n");

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-cache')
        ->toContain('no-transform');

    expect($conversation->fresh()->source_url)->toBe('https://example.com/pricing');
});

it('rejects a page context outside the bot allowed domains', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-4', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot', 'allowed_domains' => ['example.com']]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $this->postJson('/api/public/chat/message', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'message' => 'Help me',
        'page' => ['url' => 'https://other.test/pricing'],
    ])->assertForbidden();
});
