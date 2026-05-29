<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\GeoIp\MaxMindGeoIpService;

it('returns contact configuration when starting a conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-start-contact', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'contact_fields' => ['name', 'email', 'phone'],
        'contact_required' => ['email'],
        'collect_contact_on_start' => true,
    ]);

    $this->mock(MaxMindGeoIpService::class)
        ->shouldReceive('lookup')
        ->once()
        ->andReturn([
            'country_code' => 'US',
            'country_name' => 'United States',
            'city' => 'New York',
        ]);

    $response = $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-abc',
        'source_url' => 'https://example.com/pricing',
        'referrer_url' => 'https://google.com/',
        'timezone' => 'America/New_York',
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring',
    ], [
        'Accept-Language' => 'en-US,en;q=0.9',
        'User-Agent' => 'Mozilla/5.0 Test',
    ])->assertOk();

    $response->assertJsonPath('contact_fields', ['name', 'email', 'phone'])
        ->assertJsonPath('contact_required', ['email'])
        ->assertJsonPath('has_contact', false)
        ->assertJsonPath('collect_contact_on_start', true);

    $conversation = ChatConversation::query()->first();

    expect($conversation->referrer_url)->toBe('https://google.com/')
        ->and($conversation->timezone)->toBe('America/New_York')
        ->and($conversation->utm_source)->toBe('google')
        ->and($conversation->utm_medium)->toBe('cpc')
        ->and($conversation->utm_campaign)->toBe('spring')
        ->and($conversation->country_code)->toBe('US')
        ->and($conversation->city)->toBe('New York')
        ->and($conversation->user_agent)->toBe('Mozilla/5.0 Test')
        ->and($conversation->language)->toBe('en-US');
});

it('reuses prior visitor contact on new conversations', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-prior-contact', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'contact_fields' => ['email'],
        'contact_required' => ['email'],
    ]);

    ChatConversation::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'visitor_id' => 'visitor-xyz',
        'visitor_email' => 'known@example.com',
        'visitor_name' => 'Known User',
    ]);

    $this->mock(MaxMindGeoIpService::class)->shouldReceive('lookup')->andReturn([
        'country_code' => null,
        'country_name' => null,
        'city' => null,
    ]);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-xyz',
        'source_url' => 'https://example.com',
    ])
        ->assertOk()
        ->assertJsonPath('has_contact', true)
        ->assertJsonPath('contact.email', 'known@example.com');

    $latest = ChatConversation::query()->latest('id')->first();

    expect($latest->visitor_email)->toBe('known@example.com')
        ->and($latest->visitor_name)->toBe('Known User');
});

it('validates required contact fields', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-contact-validate', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'contact_fields' => ['name', 'email'],
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
        'visitor_name' => 'Jane',
    ])->assertUnprocessable();
});
