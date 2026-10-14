<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->bot = Bot::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->chats = ChatConversation::factory()->count(2)->create(['bot_id' => $this->bot->id, 'support_mode' => 'human', 'assigned_to' => $this->admin->id]);
});

it('includes each conversation country in the paginated inbox', function () {
    $this->chats[0]->update(['country_name' => 'Türkiye', 'country_code' => 'TR']);
    $this->chats[1]->update(['country_name' => null, 'country_code' => null]);
    $this->actingAs($this->admin)->get(route('conversations.index', ['tab' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 2)
            ->where('conversations.data', fn ($items) => collect($items)->contains(fn ($item) => $item['id'] === $this->chats[0]->id && $item['country_name'] === 'Türkiye' && $item['country_code'] === 'TR')));
});

it('resolves only selected chats and preserves ownership and support mode', function () {
    $untouched = ChatConversation::factory()->create(['bot_id' => $this->bot->id]);
    $this->actingAs($this->admin)->post(route('conversations.bulk-action'), [
        'action' => 'resolve', 'conversation_ids' => $this->chats->modelKeys(), 'filters' => ['tab' => 'all', 'bot_id' => $this->bot->id],
    ])->assertRedirect(route('conversations.index', ['tab' => 'all', 'bot_id' => $this->bot->id]))->assertSessionHasNoErrors();

    foreach ($this->chats as $chat) {
        expect($chat->fresh())->status->toBe('resolved')->resolved_at->not->toBeNull()
            ->assigned_to->toBe($this->admin->id)->support_mode->toBe('human');
    }
    expect($untouched->fresh()->status)->toBe('open');
});

it('deletes selected chats and their messages while keeping other chats', function () {
    $untouched = ChatConversation::factory()->create(['bot_id' => $this->bot->id]);
    foreach ($this->chats as $chat) {
        $chat->messages()->create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'role' => 'user', 'content' => 'Hello']);
    }
    $this->actingAs($this->admin)->post(route('conversations.bulk-action'), ['action' => 'delete', 'conversation_ids' => $this->chats->modelKeys()])
        ->assertRedirect(route('conversations.index'))->assertSessionHas('success', 'Selected conversations deleted.');

    foreach ($this->chats as $chat) {
        $this->assertModelMissing($chat);
        $this->assertDatabaseMissing('chat_messages', ['conversation_id' => $chat->id]);
    }
    $this->assertModelExists($untouched);
});

it('rejects agent bulk actions even on their own assigned conversations', function (string $action) {
    $agent = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'agent']);
    $this->chats[0]->update(['assigned_to' => $agent->id]);
    $this->actingAs($agent)->postJson(route('conversations.bulk-action'), ['action' => $action, 'conversation_ids' => $this->chats->modelKeys()])->assertForbidden();
    expect($this->chats[0]->fresh()->status)->toBe('open');
})->with(['resolve', 'delete']);

it('rejects an entire mixed tenant selection without changing authorized chats', function (string $action) {
    $foreign = ChatConversation::factory()->create();
    $this->actingAs($this->admin)->postJson(route('conversations.bulk-action'), ['action' => $action, 'conversation_ids' => [$this->chats[0]->id, $foreign->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('conversation_ids.1');
    expect($this->chats[0]->fresh()->status)->toBe('open');
    $this->assertModelExists($foreign);
})->with(['resolve', 'delete']);

it('lets platform admins perform bulk actions across workspaces', function () {
    $platform = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $foreign = ChatConversation::factory()->create();
    $this->actingAs($platform)->post(route('conversations.bulk-action'), ['action' => 'resolve', 'conversation_ids' => [$this->chats[0]->id, $foreign->id]])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($this->chats[0]->fresh()->status)->toBe('resolved')->and($foreign->fresh()->status)->toBe('resolved');
});

it('rejects empty duplicate missing oversized and malformed bulk selections', function (array $changes, string $error) {
    $payload = ['action' => 'resolve', 'conversation_ids' => $this->chats->modelKeys()];
    if (($changes['conversation_ids'] ?? null) === 'duplicate') {
        $changes['conversation_ids'] = [$this->chats[0]->id, $this->chats[0]->id];
    }
    $this->actingAs($this->admin)->postJson(route('conversations.bulk-action'), array_replace($payload, $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($this->chats[0]->fresh()->status)->toBe('open');
})->with([
    'empty' => [['conversation_ids' => []], 'conversation_ids'],
    'duplicate' => [['conversation_ids' => 'duplicate'], 'conversation_ids.0'],
    'missing' => [['conversation_ids' => [999999]], 'conversation_ids.0'],
    'too many' => [['conversation_ids' => range(1, 101)], 'conversation_ids'],
    'invalid action' => [['action' => 'assign'], 'action'],
    'not a list' => [['conversation_ids' => ['first' => 1]], 'conversation_ids'],
    'invalid id' => [['conversation_ids' => ['invalid']], 'conversation_ids.0'],
]);

it('does not overwrite a previous resolution timestamp', function () {
    $resolvedAt = now()->subDay();
    $this->chats[0]->update(['status' => 'resolved', 'resolved_at' => $resolvedAt]);
    $this->actingAs($this->admin)->post(route('conversations.bulk-action'), ['action' => 'resolve', 'conversation_ids' => $this->chats->modelKeys()])->assertSessionHasNoErrors();
    expect($this->chats[0]->fresh()->resolved_at->timestamp)->toBe($resolvedAt->timestamp);
});

it('renders role aware navigation and exercises country selection and bulk controls', function () {
    $result = Process::path(base_path())->run('node tests/support/settings-ui.test.mjs');
    expect($result->successful())->toBeTrue("UI checks failed:\n".$result->errorOutput());
});
