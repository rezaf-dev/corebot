<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

it('renders ai settings for tenant admins', function () {
    [$tenant, $user] = tenantAdmin();

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AiSettings/Edit')
            ->has('settings', fn (Assert $settings) => $settings
                ->where('provider', 'openai')
                ->where('base_url', 'https://api.openai.com/v1')
                ->etc()
            )
        );
});

it('forbids non tenant admins from ai settings', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'agent']);

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertForbidden();
});

it('stores api keys encrypted and exposes only masked keys', function () {
    [$tenant, $user] = tenantAdmin();

    $this->actingAs($user)->put(route('ai-settings.update'), [
        'provider' => 'openai',
        'api_key' => 'sk-testabcd',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
    ])->assertRedirect()
        ->assertSessionHas('success', 'AI settings saved.');

    $settings = $tenant->aiSetting()->first();

    expect($settings->getRawOriginal('api_key_encrypted'))->not->toBe('sk-testabcd')
        ->and($settings->api_key)->toBe('sk-testabcd')
        ->and($settings->maskedApiKey())->toBe('sk-...abcd')
        ->and($settings->is_active)->toBeFalse()
        ->and($settings->last_test_status)->toBeNull();
});

it('keeps the existing api key and requires retesting when models change', function () {
    [$tenant, $user] = tenantAdmin();

    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-existing-key'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'is_active' => true,
    ]);

    $this->actingAs($user)->put(route('ai-settings.update'), [
        'provider' => 'openai',
        'api_key' => '',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4.1-mini',
        'embedding_model' => 'text-embedding-3-large',
    ])->assertRedirect();

    $settings = $tenant->aiSetting()->first();

    expect($settings->api_key)->toBe('sk-existing-key')
        ->and($settings->chat_model)->toBe('gpt-4.1-mini')
        ->and($settings->embedding_model)->toBe('text-embedding-3-large')
        ->and($settings->is_active)->toBeFalse()
        ->and($settings->last_test_status)->toBeNull();
});

it('validates ai settings fields', function () {
    [, $user] = tenantAdmin();

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'provider' => 'unsupported',
            'base_url' => 'not-a-url',
            'chat_model' => '',
            'embedding_model' => '',
        ])
        ->assertSessionHasErrors(['provider', 'base_url', 'chat_model', 'embedding_model']);
});

it('stores openrouter settings and requires a replacement key when switching providers', function () {
    [$tenant, $user] = tenantAdmin();

    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-existing-key'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'is_active' => true,
    ]);

    $payload = [
        'provider' => 'openrouter',
        'api_key' => '',
        'base_url' => 'https://openrouter.ai/api/v1',
        'chat_model' => 'openai/gpt-4o-mini',
        'embedding_model' => 'openai/text-embedding-3-small',
    ];

    $this->actingAs($user)
        ->put(route('ai-settings.update'), $payload)
        ->assertSessionHasErrors('api_key');

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [...$payload, 'api_key' => 'sk-or-v1-test'])
        ->assertSessionHasNoErrors();

    $settings = $tenant->aiSetting()->first();

    expect($settings->provider)->toBe('openrouter')
        ->and($settings->api_key)->toBe('sk-or-v1-test')
        ->and($settings->base_url)->toBe('https://openrouter.ai/api/v1')
        ->and($settings->chat_model)->toBe('openai/gpt-4o-mini')
        ->and($settings->embedding_model)->toBe('openai/text-embedding-3-small')
        ->and($settings->is_active)->toBeFalse();
});

it('rejects a custom openrouter base url', function () {
    [, $user] = tenantAdmin();

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'provider' => 'openrouter',
            'api_key' => 'sk-or-v1-test',
            'base_url' => 'https://example.com/api/v1',
            'chat_model' => 'openai/gpt-4o-mini',
            'embedding_model' => 'openai/text-embedding-3-small',
        ])
        ->assertSessionHasErrors('base_url');
});

it('marks settings active when the connection test passes', function () {
    [$tenant, $user] = tenantAdmin();

    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-test'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'is_active' => false,
    ]);

    mock(OpenAIService::class)
        ->shouldReceive('testApiKey')
        ->once()
        ->andReturn(true);

    $this->actingAs($user)
        ->post(route('ai-settings.test'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Connection test passed. AI features are now active.');

    $settings = $tenant->aiSetting()->first();

    expect($settings->is_active)->toBeTrue()
        ->and($settings->last_test_status)->toBe('success')
        ->and($settings->last_test_error)->toBeNull()
        ->and($settings->last_tested_at)->not->toBeNull();
});

it('records failures when the connection test fails', function () {
    [$tenant, $user] = tenantAdmin();

    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-test'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'is_active' => true,
        'last_test_status' => 'success',
    ]);

    mock(OpenAIService::class)
        ->shouldReceive('testApiKey')
        ->once()
        ->andThrow(new RuntimeException('Invalid API key provided.'));

    $this->actingAs($user)
        ->post(route('ai-settings.test'))
        ->assertRedirect()
        ->assertSessionHas('error', 'Connection test failed. Check the details below and try again.');

    $settings = $tenant->aiSetting()->first();

    expect($settings->is_active)->toBeFalse()
        ->and($settings->last_test_status)->toBe('failed')
        ->and($settings->last_test_error)->toBe('Invalid API key provided.')
        ->and($settings->last_tested_at)->not->toBeNull();
});

/**
 * @return array{0: Tenant, 1: User}
 */
function tenantAdmin(): array
{
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-'.uniqid(), 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    return [$tenant, $user];
}
