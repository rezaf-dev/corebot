<?php

use App\Mail\ManualConversationHandoffMail;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Conversations\ManualHandoffNotifier;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('queues one email when a conversation needs manual handling', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'manual-handoff', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'notification_email' => 'admin@example.com']);
    $conversation = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'visitor_email' => 'visitor@example.com']);
    $conversation->messages()->create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'role' => 'user', 'content' => 'I need a person.']);

    app(ManualHandoffNotifier::class)->escalate($bot, $conversation);

    Mail::assertQueued(ManualConversationHandoffMail::class, fn (ManualConversationHandoffMail $mail): bool => $mail->hasTo('admin@example.com')
        && $mail->conversation->is($conversation)
        && $mail->lastUserMessage === 'I need a person.');
    expect($conversation->fresh()->status)->toBe('escalated')
        ->and($conversation->fresh()->manual_handoff_notified_at)->not->toBeNull();
});

it('does not send duplicate manual handoff emails', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'manual-handoff-once', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'notification_email' => 'admin@example.com']);
    $conversation = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'status' => 'escalated', 'manual_handoff_notified_at' => now()]);

    app(ManualHandoffNotifier::class)->escalate($bot, $conversation);

    Mail::assertNothingQueued();
});

it('lists conversations needing a reply before open conversations', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'reply-priority', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $open = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'status' => 'open']);
    $escalated = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'status' => 'escalated']);

    $this->actingAs($user)
        ->get(route('conversations.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->where('conversations.0.id', $escalated->id)
            ->where('conversations.1.id', $open->id)
        );
});
