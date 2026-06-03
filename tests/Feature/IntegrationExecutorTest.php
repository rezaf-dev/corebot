<?php

use App\Enums\IntegrationType;
use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\ChatConversation;
use App\Models\IntegrationActionLog;
use App\Models\Tenant;
use App\Services\Integrations\IntegrationExecutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('creates a lead via create_lead integration', function () {
    Mail::fake();

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-exec-lead', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support',
        'notification_email' => 'leads@example.com',
        'contact_fields' => ['name', 'email'],
        'contact_required' => ['email'],
    ]);
    $integration = BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => IntegrationType::CreateLead,
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $result = app(IntegrationExecutor::class)->run($bot, $conversation, $integration, [
        'visitor_name' => 'Alex',
        'visitor_email' => 'alex@example.com',
    ]);

    $decoded = json_decode($result, true);

    expect($decoded['success'])->toBeTrue()
        ->and($conversation->fresh()->visitor_email)->toBe('alex@example.com');

    expect(IntegrationActionLog::query()->where('bot_integration_id', $integration->id)->count())->toBe(1);
});

it('posts to a webhook integration', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response(['ok' => true], 200)]);

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-exec-hook', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $integration = BotIntegration::factory()->webhook()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $result = app(IntegrationExecutor::class)->run($bot, $conversation, $integration, ['note' => 'test']);

    expect(json_decode($result, true)['success'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.com/lead'
        && $request['arguments']['note'] === 'test');
});

it('returns api response data from http get integrations', function () {
    Http::fake([
        'https://admin.onlinemedicalcard.com/api/v2/status*' => Http::response(['status' => 'approved'], 200),
    ]);

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-exec-status', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $integration = BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => IntegrationType::HttpGet,
        'config' => ['url' => 'https://admin.onlinemedicalcard.com/api/v2/status'],
        'allowed_domains' => ['admin.onlinemedicalcard.com'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $result = json_decode(app(IntegrationExecutor::class)->run($bot, $conversation, $integration, [
        'query' => ['secret' => '38827717'],
    ]), true);

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toBe(['status' => 'approved']);

    Http::assertSent(fn ($request) => $request->url() === 'https://admin.onlinemedicalcard.com/api/v2/status?secret=38827717');
});

it('supports path placeholders in http get urls', function () {
    Http::fake([
        'https://admin.onlinemedicalcard.com/api/v2/verify/*' => Http::response(['valid' => true], 200),
    ]);

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-exec-verify', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $integration = BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => IntegrationType::HttpGet,
        'config' => ['url' => 'https://admin.onlinemedicalcard.com/api/v2/verify/{recommendation_id}'],
        'allowed_domains' => ['admin.onlinemedicalcard.com'],
    ]);
    $conversation = ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'status' => 'open',
    ]);

    $result = json_decode(app(IntegrationExecutor::class)->run($bot, $conversation, $integration, [
        'recommendation_id' => 'N6000053720',
    ]), true);

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toBe(['valid' => true]);

    Http::assertSent(fn ($request) => $request->url() === 'https://admin.onlinemedicalcard.com/api/v2/verify/N6000053720');
});
