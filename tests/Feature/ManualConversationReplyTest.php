<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

it('lets a tenant admin send a manual reply to a conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'manual-reply', 'status' => 'active']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'escalated',
        'manual_handoff_notified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('conversations.messages.store', $conversation), ['message' => 'A teammate will help you today.'])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe('open')
        ->and($conversation->fresh()->manual_handoff_notified_at)->toBeNull()
        ->and($conversation->messages()->latest('id')->first())
        ->role->toBe('admin')
        ->content->toBe('A teammate will help you today.');
});

it('returns manual replies to the conversation widget', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'manual-reply-widget', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'public_session_token' => hash('sha256', $conversationToken = Str::random(64)),
    ]);
    $reply = $conversation->messages()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'role' => 'admin',
        'content' => 'A human reply.',
    ]);

    $this->postJson('/api/public/chat/manual-messages', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'conversation_token' => $conversationToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('messages.0.id', $reply->id)
        ->assertJsonPath('messages.0.content', 'A human reply.');
});

it('prevents admins from replying to another tenant conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'manual-reply-access', 'status' => 'active']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'manual-reply-other', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $otherTenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create(['tenant_id' => $otherTenant->id, 'bot_id' => $bot->id]);

    $this->actingAs($admin)
        ->post(route('conversations.messages.store', $conversation), ['message' => 'Not allowed.'])
        ->assertForbidden();

    expect($conversation->messages)->toBeEmpty();
});

it('lets a tenant admin delete a conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'delete-conversation', 'status' => 'active']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id]);

    $this->actingAs($admin)
        ->delete(route('conversations.destroy', $conversation))
        ->assertRedirect(route('conversations.index'));

    $this->assertModelMissing($conversation);
});

it('prevents admins from deleting another tenant conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'delete-conversation-access', 'status' => 'active']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'delete-conversation-other', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $otherTenant->id, 'name' => 'Support']);
    $conversation = ChatConversation::create(['tenant_id' => $otherTenant->id, 'bot_id' => $bot->id]);

    $this->actingAs($admin)
        ->delete(route('conversations.destroy', $conversation))
        ->assertForbidden();

    $this->assertModelExists($conversation);
});
