<?php

use App\Models\Bot;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WidgetConfig;

it('returns widget config for an active bot', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-config', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'welcome_message' => 'Welcome to our CRM help chat.',
        'widget_config' => [
            'title' => 'Help Desk',
            'primary_color' => '#ff0000',
        ],
    ]);

    $this->getJson('/api/public/chat/widget-config?bot_public_key='.$bot->public_key)
        ->assertSuccessful()
        ->assertJsonPath('widget.title', 'Help Desk')
        ->assertJsonPath('widget.primary_color', '#ff0000')
        ->assertJsonPath('widget.position', 'bottom-right')
        ->assertJsonPath('widget.initial_open', false)
        ->assertJsonPath('widget.welcome_message', 'Welcome to our CRM help chat.');
});

it('includes widget config when starting a conversation', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-start', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'widget_config' => ['accent_color' => '#00ff00'],
    ]);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-1',
        'source_url' => 'https://example.com',
    ])
        ->assertSuccessful()
        ->assertJsonPath('widget.accent_color', '#00ff00');
});

it('saves widget settings for tenant admins', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-save', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);

    $payload = WidgetConfig::DEFAULTS;
    $payload['title'] = 'CRM Help';
    $payload['position'] = 'bottom-left';
    $payload['initial_open'] = true;

    $this->actingAs($user)
        ->put(route('widget.install.update', $bot), $payload)
        ->assertRedirect()
        ->assertSessionHas('success', 'Widget appearance saved.');

    expect($bot->fresh()->resolvedWidgetConfig())
        ->title->toBe('CRM Help')
        ->position->toBe('bottom-left')
        ->initial_open->toBeTrue();
});

it('resolves api base from widget script url', function (string $widgetUrl, string $expectedApiBase) {
    expect(WidgetConfig::apiBaseFromWidgetUrl($widgetUrl))->toBe($expectedApiBase);
})->with([
    'site root' => ['https://app.test/widget.js', 'https://app.test/api/public/chat'],
    'subfolder' => ['https://app.test/corebot/widget.js', 'https://app.test/corebot/api/public/chat'],
    'nested subfolder' => ['https://app.test/apps/crm/widget.js', 'https://app.test/apps/crm/api/public/chat'],
]);

it('builds embed snippets with data attributes', function () {
    $snippet = WidgetConfig::embedSnippet(
        'https://app.test/widget.js',
        'bot_testkey',
        ['title' => 'Help', 'primary_color' => '#112233'],
    );

    expect($snippet)
        ->toContain('src="https://app.test/widget.js"')
        ->toContain('data-bot-key="bot_testkey"')
        ->toContain('data-title="Help"')
        ->toContain('data-primary-color="#112233"')
        ->toContain('data-initial-open="false"');
});

it('includes initial open in embed snippets when enabled', function () {
    $config = array_merge(WidgetConfig::DEFAULTS, ['initial_open' => true]);

    $snippet = WidgetConfig::embedSnippet('https://app.test/widget.js', 'bot_testkey', $config);

    expect($snippet)->toContain('data-initial-open="true"');
});
