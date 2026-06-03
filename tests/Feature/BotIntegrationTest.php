<?php

use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\Tenant;
use App\Models\User;

it('allows tenant admin to create bot integrations', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-bot-int', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);

    $this->actingAs($user)
        ->from(route('bots.edit', $bot))
        ->post(route('bots.integrations.store', $bot), [
            'type' => 'create_lead',
            'name' => 'Capture lead',
            'description' => 'Save visitor contact details.',
            'enabled' => true,
        ])
        ->assertRedirect(route('bots.edit', $bot));

    $integration = BotIntegration::query()->where('bot_id', $bot->id)->first();

    expect($integration)->not->toBeNull()
        ->and($integration->name)->toBe('Capture lead')
        ->and($integration->type->value)->toBe('create_lead');
});

it('encrypts integration credentials', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-bot-int-enc', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);

    $this->actingAs($user)
        ->post(route('bots.integrations.store', $bot), [
            'type' => 'http_get',
            'name' => 'CRM lookup',
            'description' => 'Query CRM API.',
            'enabled' => true,
            'config' => ['url' => 'https://api.example.com/status'],
            'allowed_domains' => 'api.example.com',
            'credentials' => ['bearer_token' => 'secret-token'],
        ])
        ->assertRedirect();

    $integration = BotIntegration::query()->firstOrFail();

    expect($integration->resolvedCredentials()['bearer_token'])->toBe('secret-token')
        ->and($integration->getAttributes()['credentials'])->not->toContain('secret-token');
});

it('forbids integration management for other tenants', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'tenant-a-int', 'status' => 'active']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'tenant-b-int', 'status' => 'active']);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'tenant_admin']);
    $botA = Bot::create(['tenant_id' => $tenantA->id, 'name' => 'A bot']);

    $this->actingAs($userB)
        ->post(route('bots.integrations.store', $botA), [
            'type' => 'create_lead',
            'name' => 'Lead',
            'enabled' => true,
        ])
        ->assertForbidden();
});
