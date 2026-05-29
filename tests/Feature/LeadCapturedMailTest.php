<?php

use App\Mail\LeadCapturedMail;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

it('queues lead captured mail when contact is saved', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-lead-mail', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support Bot',
        'notification_email' => 'leads@example.com',
        'contact_fields' => ['name', 'email'],
        'contact_required' => ['email'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'visitor_id' => 'visitor-1',
        'status' => 'escalated',
    ]);

    $this->postJson('/api/public/chat/contact', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'visitor_name' => 'Jane Doe',
        'visitor_email' => 'jane@example.com',
    ])->assertOk();

    Mail::assertQueued(LeadCapturedMail::class, function (LeadCapturedMail $mail) use ($bot, $conversation) {
        return $mail->hasTo('leads@example.com')
            && $mail->bot->is($bot)
            && $mail->conversation->is($conversation->fresh())
            && $mail->conversation->visitor_email === 'jane@example.com';
    });

    expect($conversation->fresh()->contact_notified_at)->not->toBeNull();
});

it('does not send lead mail twice for the same conversation', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-lead-mail-twice', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support Bot',
        'notification_email' => 'leads@example.com',
        'contact_fields' => ['email'],
        'contact_required' => ['email'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'visitor_email' => 'jane@example.com',
        'contact_notified_at' => now(),
        'status' => 'escalated',
    ]);

    $this->postJson('/api/public/chat/contact', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'visitor_email' => 'jane@example.com',
    ])->assertOk();

    Mail::assertNothingQueued();
});

it('skips lead mail when bot has no notification email', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-lead-mail-skip', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support Bot',
        'contact_fields' => ['email'],
        'contact_required' => ['email'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $this->postJson('/api/public/chat/contact', [
        'bot_public_key' => $bot->public_key,
        'conversation_id' => $conversation->id,
        'visitor_email' => 'jane@example.com',
    ])->assertOk();

    Mail::assertNothingQueued();
});
