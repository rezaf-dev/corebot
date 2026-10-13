<?php

use App\Ai\Tools\IntegrationTool;
use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use App\Services\Integrations\IntegrationExecutor;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SearchResult;
use App\Services\Rag\SemanticSearchService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'agent']);
    $this->bot = Bot::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->conversation = ChatConversation::factory()->create(['bot_id' => $this->bot->id, 'support_mode' => 'human', 'status' => 'escalated']);
});

it('denies agents direct access to configuration', function (string $route) {
    $this->actingAs($this->agent)->get(route($route))->assertForbidden();
})->with(['settings.index', 'admin.index', 'bots.index', 'knowledge-sources.index', 'widget.install', 'ai-settings.edit', 'dashboard', 'staff.index', 'tenants.index', 'language-settings.edit']);

it('rejects configuration mutations by agents', function () {
    $this->actingAs($this->agent)->post(route('bots.store'), ['name' => 'Illegal'])->assertForbidden();
    $this->put(route('bots.update', $this->bot), ['name' => 'Illegal'])->assertForbidden();
    $this->delete(route('bots.destroy', $this->bot))->assertForbidden();
    $this->post(route('staff.store'), ['role' => 'super_admin'])->assertForbidden();
});

it('fails closed for staff without a workspace and for unknown roles', function (array $attributes) {
    $user = User::factory()->create($attributes);
    $this->actingAs($user)->get(route('conversations.index'))->assertForbidden();
})->with([[['tenant_id' => null]], [['role' => 'invalid']]]);

it('preserves authorized deep links but redirects agents away from settings after login', function () {
    $this->withSession(['url.intended' => route('bots.index')])->post('/login', ['email' => $this->agent->email, 'password' => 'password'])->assertRedirect(route('conversations.index', absolute: false));
    $this->post('/logout');
    $this->withSession(['url.intended' => route('conversations.show', $this->conversation)])->post('/login', ['email' => $this->agent->email, 'password' => 'password'])->assertRedirect(route('conversations.show', $this->conversation));
});

it('requires ownership for agent replies and prevents stealing claimed work', function () {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'agent']);
    $this->actingAs($this->agent)->post(route('conversations.messages.store', $this->conversation), ['message' => 'Reply'])->assertForbidden();
    $this->patch(route('conversations.update', $this->conversation), ['action' => 'claim'])->assertRedirect();
    expect($this->conversation->fresh()->assigned_to)->toBe($this->agent->id);
    $this->actingAs($other)->patch(route('conversations.update', $this->conversation), ['action' => 'claim'])->assertSessionHas('error');
    expect($this->conversation->fresh()->assigned_to)->toBe($this->agent->id);
    $this->actingAs($this->agent)->post(route('conversations.messages.store', $this->conversation), ['message' => 'Hello'])->assertRedirect();
    expect($this->conversation->fresh()->support_mode)->toBe('human')->and($this->conversation->messages()->first()->metadata['admin_name'])->toBe($this->agent->name);
    $this->delete(route('conversations.destroy', $this->conversation))->assertForbidden();
});

it('restricts transfers to active same tenant teammates', function () {
    $this->conversation->update(['assigned_to' => $this->agent->id]);
    foreach ([User::factory()->create(['role' => 'agent']), User::factory()->create(['role' => 'agent', 'tenant_id' => $this->tenant->id, 'is_active' => false])] as $target) {
        $this->actingAs($this->agent)->patch(route('conversations.update', $this->conversation), ['action' => 'assign', 'assigned_to' => $target->id])->assertSessionHasErrors('assigned_to');
    }
    $target = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'agent']);
    $this->patch(route('conversations.update', $this->conversation), ['action' => 'assign', 'assigned_to' => $target->id])->assertRedirect();
    $this->patch(route('conversations.update', $this->conversation), ['action' => 'resolve'])->assertForbidden();
});

it('enforces tenant isolation for every conversation action', function () {
    $foreign = ChatConversation::factory()->create();
    $this->actingAs($this->agent)->get(route('conversations.show', $foreign))->assertForbidden();
    foreach (['claim', 'resolve', 'reopen', 'return_to_ai', 'assign'] as $action) {
        $this->patch(route('conversations.update', $foreign), ['action' => $action, 'assigned_to' => $this->agent->id])->assertForbidden();
    }
});

it('resolves reopens and explicitly returns conversations to AI', function () {
    $this->conversation->update(['assigned_to' => $this->agent->id]);
    $this->actingAs($this->agent)->patch(route('conversations.update', $this->conversation), ['action' => 'resolve'])->assertRedirect();
    expect($this->conversation->fresh())->status->toBe('resolved')->support_mode->toBe('human')->assigned_to->toBe($this->agent->id);
    $this->patch(route('conversations.update', $this->conversation), ['action' => 'reopen'])->assertRedirect();
    $this->patch(route('conversations.update', $this->conversation), ['action' => 'return_to_ai'])->assertRedirect();
    expect($this->conversation->fresh())->status->toBe('open')->support_mode->toBe('ai')->assigned_to->toBeNull()->handoff_reason->toBeNull();
});

it('paginates searches and counts queues on the server', function () {
    ChatConversation::factory()->count(32)->create(['bot_id' => $this->bot->id, 'visitor_name' => 'Customer', 'support_mode' => 'human']);
    ChatConversation::factory()->create();
    $this->actingAs($this->agent)->get(route('conversations.index', ['tab' => 'all']))->assertInertia(fn (Assert $page) => $page->has('conversations.data', 30)->where('conversations.total', 33)->where('counts.all', 33));
    $this->get(route('conversations.index', ['tab' => 'all', 'search' => 'Customer']))->assertInertia(fn (Assert $page) => $page->where('conversations.total', 32));
    $this->conversation->messages()->create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'role' => 'user', 'content' => 'Unique search words']);
    $this->get(route('conversations.index', ['tab' => 'all', 'search' => 'Unique search']))->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1)->where('conversations.data.0.id', $this->conversation->id));
    $this->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->where('counts.needs_reply', 1));
    $this->actingAs($this->admin)->post(route('conversations.messages.store', $this->conversation), ['message' => 'Answered']);
    $this->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->where('counts.needs_reply', 0));
});

it('limits messages and isolates visitor history and diagnostics', function () {
    $this->conversation->update(['visitor_id' => 'same-visitor']);
    $previous = ChatConversation::factory()->create(['bot_id' => $this->bot->id, 'visitor_id' => 'same-visitor']);
    ChatConversation::factory()->create(['visitor_id' => 'same-visitor']);
    for ($i = 0; $i < 55; $i++) {
        $this->conversation->messages()->create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'role' => 'user', 'content' => (string) $i]);
    }
    $this->actingAs($this->agent)->get(route('conversations.show', $this->conversation))->assertInertia(fn (Assert $page) => $page->has('conversation.messages', 50)->where('conversation.messages.0.content', '5')->where('conversation.has_older_messages', true)->has('conversation.history', 1)->where('conversation.history.0.id', $previous->id)->missing('conversation.public_session_token')->missing('conversation.retrieval_logs')->missing('conversation.integration_action_logs'));
    $this->get(route('conversations.show', ['conversation' => $this->conversation->id, 'message_limit' => 100]))->assertInertia(fn (Assert $page) => $page->has('conversation.messages', 55)->where('conversation.has_older_messages', false));
});

it('creates tenant agents with password setup emails without privilege escalation', function () {
    Notification::fake();
    $this->actingAs($this->admin)->post(route('staff.store'), ['name' => 'Teammate', 'email' => 'teammate@example.com', 'role' => 'super_admin', 'tenant_id' => null, 'is_active' => true])->assertSessionHasNoErrors();
    $staff = User::where('email', 'teammate@example.com')->firstOrFail();
    expect($staff)->role->toBe('agent')->tenant_id->toBe($this->tenant->id);
    Notification::assertSentTo($staff, ResetPassword::class);
    $this->put(route('staff.update', $this->admin), ['name' => 'Admin', 'email' => $this->admin->email, 'role' => 'agent', 'is_active' => false])->assertForbidden();
});

it('deactivates staff blocks stale sessions and releases their conversations', function () {
    $this->conversation->update(['assigned_to' => $this->agent->id]);
    $this->actingAs($this->admin)->put(route('staff.update', $this->agent), ['name' => $this->agent->name, 'email' => $this->agent->email, 'role' => 'agent', 'tenant_id' => $this->tenant->id, 'is_active' => false])->assertSessionHasNoErrors();
    expect($this->conversation->fresh()->assigned_to)->toBeNull();
    $this->actingAs($this->agent->fresh())->get(route('conversations.index'))->assertRedirect(route('login'));
    $this->post('/login', ['email' => $this->agent->email, 'password' => 'password'])->assertSessionHasErrors('email');
});

it('protects last admins from deactivation demotion tenant moves and profile deletion', function () {
    $platform = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $this->actingAs($platform);
    foreach ([$platform, $this->admin] as $staff) {
        foreach ([['is_active' => false], ['role' => 'agent', 'tenant_id' => $this->tenant->id]] as $change) {
            $this->put(route('staff.update', $staff), [...$staff->only('name', 'email', 'role', 'tenant_id', 'is_active'), ...$change])->assertSessionHasErrors('role');
        }
    }
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('role');
    expect($platform->fresh())->not->toBeNull();
});

it('accepts human support messages without calling AI or retrieval and reopens resolved work', function (bool $stream) {
    $this->mock(OpenAIService::class)->shouldNotReceive('createChatCompletion', 'streamChatCompletion');
    $this->mock(SemanticSearchService::class)->shouldNotReceive('search');
    $this->conversation->update(['status' => 'resolved', 'resolved_at' => now(), 'public_session_token' => hash('sha256', $token = Str::random(64))]);
    $payload = ['bot_public_key' => $this->bot->public_key, 'conversation_id' => $this->conversation->id, 'conversation_token' => $token, 'message' => 'Please help again'];
    $response = $this->postJson('/api/public/chat/message'.($stream ? '/stream' : ''), $payload)->assertSuccessful();
    if ($stream) {
        expect($response->streamedContent())->toContain('"human_support":true')->toContain('"type":"done"')->not->toContain('text_delta');
    } else {
        $response->assertJsonPath('human_support', true)->assertJsonPath('message', null);
    }
    expect($this->conversation->fresh())->status->toBe('open')->resolved_at->toBeNull();
    expect($this->conversation->messages()->count())->toBe(1);
    $this->postJson('/api/public/chat/manual-messages', $payload)->assertJsonPath('support_mode', 'human')->assertJsonPath('status', 'open');
})->with([false, true]);

it('suppresses a completed AI answer when a teammate takes over during generation', function () {
    $this->conversation->update(['support_mode' => 'ai', 'status' => 'open']);
    $this->tenant->aiSetting()->create(['provider' => 'openai', 'api_key' => 'sk-test', 'base_url' => 'https://api.openai.com/v1', 'chat_model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small', 'is_active' => true]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andReturn(new SearchResult(collect(), confident: false));
    $conversation = $this->conversation;
    $this->mock(OpenAIService::class)->shouldReceive('createChatCompletion')->once()->andReturnUsing(function () use ($conversation) {
        $conversation->update(['support_mode' => 'human']);

        return ['choices' => [['message' => ['content' => 'An obsolete AI answer']]]];
    });
    $result = app(ChatAnswerService::class)->answer($this->bot->load('tenant.aiSetting'), $this->conversation, 'Help');
    expect($result['human_support'])->toBeTrue()->and($this->conversation->messages()->where('role', 'assistant')->count())->toBe(0);
});

it('stops streaming AI deltas after takeover', function () {
    $this->conversation->update(['support_mode' => 'ai', 'status' => 'open']);
    $this->tenant->aiSetting()->create(['provider' => 'openai', 'api_key' => 'sk-test', 'base_url' => 'https://api.openai.com/v1', 'chat_model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small', 'is_active' => true]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andReturn(new SearchResult(collect(), confident: false));
    $conversation = $this->conversation;
    $stream = new StreamableAgentResponse('preview', function () use ($conversation) {
        yield new TextDelta('1', '1', 'Already delivered', time());
        $conversation->update(['support_mode' => 'human']);
        yield new TextDelta('2', '1', 'Suppressed text', time());
    });
    $this->mock(OpenAIService::class)->shouldReceive('streamChatCompletion')->once()->andReturn($stream);
    $events = implode('', iterator_to_array(app(ChatAnswerService::class)->stream($this->bot->load('tenant.aiSetting'), $this->conversation, 'Help')));
    expect($events)->toContain('Already delivered')->toContain('"human_support":true')->not->toContain('Suppressed text');
    expect($this->conversation->messages()->where('role', 'assistant')->first())->content->toBe('Already delivered');
});

it('prevents late AI integration tool calls after human takeover', function () {
    $integration = BotIntegration::factory()->create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id]);
    $executor = Mockery::mock(IntegrationExecutor::class);
    $executor->shouldNotReceive('run');
    $tool = new IntegrationTool($integration, $this->bot, $this->conversation, $executor);
    expect($tool->handle(new Request))->toContain('No action was executed');
});

it('forbids replies until resolved work is reopened', function () {
    $this->conversation->update(['assigned_to' => $this->agent->id, 'status' => 'resolved']);
    $this->actingAs($this->agent)->post(route('conversations.messages.store', $this->conversation), ['message' => 'Late reply'])->assertSessionHasErrors('message');
    expect($this->conversation->messages()->count())->toBe(0);
});

it('lets platform admins configure tenant AI and hides loaded credentials from shared auth props', function () {
    $platform = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $this->actingAs($platform)->get(route('ai-settings.edit', ['tenant_id' => $this->tenant->id]))->assertSuccessful();
    $this->put(route('ai-settings.update'), ['tenant_id' => $this->tenant->id, 'provider' => 'openai', 'api_key' => 'sk-secret', 'base_url' => 'https://api.openai.com/v1', 'chat_model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->get(route('ai-settings.edit'))->assertInertia(fn (Assert $page) => $page->missing('auth.user.tenant.ai_setting')->missing('auth.user.password'));
});

it('supports every inbox queue and bot filters', function () {
    $this->conversation->update(['assigned_to' => $this->agent->id, 'visitor_email' => 'visitor@example.test']);
    $unassigned = ChatConversation::factory()->create(['bot_id' => $this->bot->id, 'support_mode' => 'human']);
    $resolved = ChatConversation::factory()->create(['bot_id' => $this->bot->id, 'status' => 'resolved']);
    $this->actingAs($this->agent);
    foreach (['mine' => $this->conversation->id, 'unassigned' => $unassigned->id, 'resolved' => $resolved->id, 'leads' => $this->conversation->id] as $tab => $id) {
        $this->get(route('conversations.index', ['tab' => $tab, 'bot_id' => $this->bot->id]))->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1)->where('conversations.data.0.id', $id));
    }
    $this->get(route('conversations.index', ['tab' => 'all', 'bot_id' => 99999]))->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
});

it('protects the last remaining admin after another admin is deactivated', function () {
    $platform = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($platform)->put(route('staff.update', $second), [...$second->only('name', 'email', 'role', 'tenant_id'), 'is_active' => false])->assertSessionHasNoErrors();
    $this->put(route('staff.update', $this->admin), [...$this->admin->only('name', 'email', 'role', 'tenant_id'), 'is_active' => false])->assertSessionHasErrors('role');
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('loads bounded older message pages and denies cross tenant history requests', function () {
    for ($i = 0; $i < 105; $i++) {
        $this->conversation->messages()->create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'role' => 'user', 'content' => (string) $i]);
    }
    $before = $this->conversation->messages()->latest('id')->first()->id + 1;
    $this->actingAs($this->agent)->getJson(route('conversations.messages.index', ['conversation' => $this->conversation->id, 'before_id' => $before]))->assertJsonCount(50, 'messages')->assertJsonPath('messages.0.content', '55')->assertJsonPath('has_more', true);
    $foreign = ChatConversation::factory()->create();
    $this->getJson(route('conversations.messages.index', ['conversation' => $foreign->id, 'before_id' => $before]))->assertForbidden();
});

it('preserves authorized agent deep links when the app uses a subfolder', function () {
    config(['app.url' => 'https://app.test/corebot']);
    URL::forceRootUrl(config('app.url'));
    $intended = route('conversations.show', $this->conversation);
    $this->withSession(['url.intended' => $intended])->post('https://app.test/login', ['email' => $this->agent->email, 'password' => 'password'])->assertRedirect($intended);
});

it('allows tenantless accounts to log out while denying workspace access', function () {
    $user = User::factory()->create(['tenant_id' => null]);
    $this->actingAs($user)->get(route('conversations.index'))->assertForbidden();
    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('enters human support when staff are requested even if contact information is complete', function () {
    $this->conversation->update(['support_mode' => 'ai', 'status' => 'open', 'visitor_email' => 'customer@example.test']);
    $this->bot->update(['contact_fields' => ['email'], 'contact_required' => ['email']]);
    $this->tenant->aiSetting()->create(['provider' => 'openai', 'api_key' => 'sk-test', 'base_url' => 'https://api.openai.com/v1', 'chat_model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small', 'is_active' => true]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andReturn(new SearchResult(collect(), confident: false));
    $this->mock(OpenAIService::class)->shouldReceive('createChatCompletion')->once()->andReturn(['choices' => [['message' => ['content' => 'A teammate will help.']]]]);
    $result = app(ChatAnswerService::class)->answer($this->bot->load('tenant.aiSetting'), $this->conversation, 'I want to talk to a person');
    expect($result['needs_contact'])->toBeFalse()->and($this->conversation->fresh())->support_mode->toBe('human')->status->toBe('escalated')->handoff_reason->toBe('human_requested');
});
