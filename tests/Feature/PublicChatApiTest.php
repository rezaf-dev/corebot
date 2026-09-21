<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\ChatMessageFeedback;
use App\Models\Tenant;
use App\Services\Rag\ChatAnswerService;
use Illuminate\Support\Str;

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
        'public_session_token' => hash('sha256', $conversationToken = Str::random(64)),
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
        'conversation_token' => $conversationToken,
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
        'public_session_token' => hash('sha256', $conversationToken = Str::random(64)),
    ]);

    $this->postJson('/api/public/chat/message', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'conversation_token' => $conversationToken,
        'message' => 'Help me',
        'page' => ['url' => 'https://other.test/pricing'],
    ])->assertForbidden();
});

it('requires the conversation token to access a public conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'public-session-token', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'public_session_token' => hash('sha256', Str::random(64)),
    ]);

    $this->postJson('/api/public/chat/message', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'message' => 'Help me',
    ])->assertUnprocessable()->assertJsonValidationErrors('conversation_token');

    $this->postJson('/api/public/chat/manual-messages', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'conversation_token' => Str::random(64),
    ])->assertNotFound();

    $this->postJson('/api/public/chat/contact', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'conversation_token' => Str::random(64),
        'visitor_email' => 'visitor@example.com',
    ])->assertNotFound();
});

it('records one feedback vote per public chat session', function () {
    $tenant = Tenant::create(['name' => 'Feedback Demo', 'slug' => 'feedback-demo', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'public_session_token' => hash('sha256', $conversationToken = Str::random(64)),
    ]);
    $message = $conversation->messages()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'role' => 'assistant',
        'content' => 'Here is the answer.',
    ]);

    $this->postJson('/api/public/chat/feedback', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'conversation_token' => $conversationToken,
        'chat_message_id' => $message->id,
        'vote' => 'down',
        'reason' => 'Did not answer',
    ])->assertSuccessful();

    expect(ChatMessageFeedback::query()->where('chat_message_id', $message->id)->value('vote'))->toBe('down');
});
