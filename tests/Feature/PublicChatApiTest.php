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
        ->andReturn((function () {
            yield 'data: {"type":"text_delta","delta":"Hello"}'."\n\n";
            yield 'data: {"type":"done"}'."\n\n";
        })());

    $this->post('/api/public/chat/message/stream', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'message' => 'Hello',
    ])->assertStreamed()
        ->assertStreamedContent('data: {"type":"text_delta","delta":"Hello"}'."\n\n".'data: {"type":"done"}'."\n\n");
});
