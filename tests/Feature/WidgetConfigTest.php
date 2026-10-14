<?php

use App\Models\ApplicationSetting;
use App\Models\Bot;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WidgetConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('initializes widget settings and switches bots with the installed React form API', function () {
    $result = Process::path(base_path())->run('node tests/support/widget-settings.test.mjs');

    expect($result->successful())->toBeTrue("Widget settings checks failed:\n".$result->errorOutput());
});

it('returns widget config for an active bot', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-config', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bot',
        'welcome_message' => 'Welcome to our CRM help chat.',
        'widget_config' => [
            'title' => 'Help Desk',
            'primary_color' => '#ff0000',
            'avatar_url' => 'https://example.com/support.jpg',
            'suggested_prompts' => ['What are your hours?', 'Talk to support'],
        ],
    ]);

    $this->getJson('/api/public/chat/widget-config?bot_public_key='.$bot->public_key)
        ->assertSuccessful()
        ->assertJsonPath('widget.title', 'Help Desk')
        ->assertJsonPath('widget.primary_color', '#ff0000')
        ->assertJsonPath('widget.avatar_url', 'https://example.com/support.jpg')
        ->assertJsonPath('widget.position', 'bottom-right')
        ->assertJsonPath('widget.initial_open', false)
        ->assertJsonPath('widget.launcher_label', "We're here to help")
        ->assertJsonPath('widget.welcome_message', 'Welcome to our CRM help chat.')
        ->assertJsonPath('widget.suggested_prompts', ['What are your hours?', 'Talk to support']);
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

it('returns the global language default in widget config and conversation startup', function () {
    ApplicationSetting::factory()->create(['default_locale' => 'fa']);
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-language', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);

    $this->getJson('/api/public/chat/widget-config?bot_public_key='.$bot->public_key)
        ->assertSuccessful()
        ->assertJsonPath('localization.locale', 'fa')
        ->assertJsonPath('localization.direction', 'rtl')
        ->assertJsonStructure(['localization' => ['translations']]);

    $this->postJson('/api/public/chat/start', ['bot_public_key' => $bot->public_key])
        ->assertSuccessful()
        ->assertJsonPath('localization.locale', 'fa')
        ->assertJsonPath('localization.direction', 'rtl');
});

it('resolves widget locale overrides only for enabled languages', function (string $preferred, string $expected) {
    ApplicationSetting::factory()->create(['default_locale' => 'ar', 'enabled_locales' => ['en', 'ar', 'tr']]);
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-language-override', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);

    $this->getJson('/api/public/chat/widget-config?bot_public_key='.$bot->public_key.'&locale='.$preferred)
        ->assertSuccessful()->assertJsonPath('localization.locale', $expected);
    $this->postJson('/api/public/chat/start', ['bot_public_key' => $bot->public_key, 'locale' => $preferred])
        ->assertSuccessful()->assertJsonPath('localization.locale', $expected);
})->with(['enabled' => ['tr', 'tr'], 'disabled' => ['fa', 'ar'], 'unsupported' => ['xx', 'ar']]);

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

it('uploads a support avatar for tenant admins', function () {
    Storage::fake('public');

    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-avatar-upload', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);
    $payload = WidgetConfig::DEFAULTS;
    $payload['avatar'] = UploadedFile::fake()->image('support.png');

    $this->actingAs($user)
        ->put(route('widget.install.update', $bot), $payload)
        ->assertRedirect();

    $config = $bot->fresh()->resolvedWidgetConfig();

    expect($config['avatar_path'])->toStartWith("tenants/{$tenant->id}/widget-avatars/")
        ->and($config['avatar_url'])->toContain('/storage/'.$config['avatar_path']);

    Storage::disk('public')->assertExists($config['avatar_path']);
});

it('resolves api base from widget script url', function (string $widgetUrl, string $expectedApiBase) {
    expect(WidgetConfig::apiBaseFromWidgetUrl($widgetUrl))->toBe($expectedApiBase);
})->with([
    'site root' => ['https://app.test/widget.js', 'https://app.test/api/public/chat'],
    'subfolder' => ['https://app.test/corebot/widget.js', 'https://app.test/corebot/api/public/chat'],
    'nested subfolder' => ['https://app.test/apps/crm/widget.js', 'https://app.test/apps/crm/api/public/chat'],
]);

it('saves suggested prompts for tenant admins', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-prompts', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot']);

    $payload = WidgetConfig::DEFAULTS;
    $payload['suggested_prompts'] = ['Pricing plans', 'Book a demo'];

    $this->actingAs($user)
        ->put(route('widget.install.update', $bot), $payload)
        ->assertRedirect();

    expect($bot->fresh()->resolvedWidgetConfig()['suggested_prompts'])
        ->toBe(['Pricing plans', 'Book a demo']);
});

it('builds stable embed snippets without widget config', function () {
    $snippet = WidgetConfig::embedSnippet(
        'https://app.test/widgets/bot_testkey.js',
    );

    expect($snippet)
        ->toBe('<script src="https://app.test/widgets/bot_testkey.js"></script>');
});

it('does not accept widget config from script data attributes', function () {
    $widgetSource = file_get_contents(public_path('widget.js'));

    expect($widgetSource)
        ->not->toContain('parseDatasetConfig')
        ->not->toContain('script.dataset.apiBase')
        ->not->toContain('dataset.primaryColor')
        ->not->toContain('dataset.launcherIcon')
        ->toContain('script.dataset.botKey');
});

it('offers the approved launcher icon set', function () {
    expect(WidgetConfig::icons())->toBe(['chat', 'help', 'support', 'assistant']);
});

it('keeps the widget hidden until remote config is applied', function () {
    $widgetSource = file_get_contents(public_path('widget.js'));

    expect($widgetSource)
        ->toContain('data-config-ready="false"')
        ->toContain('.crm-ai-root[data-config-ready="false"] { visibility: hidden; }')
        ->toContain("crmRoot.dataset.configReady = 'true';")
        ->and(strpos($widgetSource, 'applyConfig(config);', strpos($widgetSource, 'loadRemoteConfig().then')))
        ->toBeLessThan(strpos($widgetSource, "crmRoot.dataset.configReady = 'true';"));
});

it('saves appearance and avatars through the browsers multipart method spoofing flow', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::factory()->create(['tenant_id' => $tenant->id]);
    $payload = array_replace(WidgetConfig::DEFAULTS, [
        '_method' => 'put',
        'primary_color' => '#aabbcc',
        'launcher_icon' => 'assistant',
        'position' => 'top-left',
        'enabled_locales' => '[]',
        'localized_content' => '{}',
        'avatar' => UploadedFile::fake()->image('avatar.png'),
    ]);

    $this->actingAs($user)->post(route('widget.install.update', $bot), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($bot->fresh()->resolvedWidgetConfig())
        ->primary_color->toBe('#aabbcc')
        ->launcher_icon->toBe('assistant')
        ->position->toBe('top-left');
    Storage::disk('public')->assertExists($bot->fresh()->widget_config['avatar_path']);
});
