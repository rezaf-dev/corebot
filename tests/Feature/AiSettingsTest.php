<?php

use App\Models\Tenant;
use App\Models\User;

it('stores api keys encrypted and exposes only masked keys', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    $this->actingAs($user)->put(route('ai-settings.update'), [
        'api_key' => 'sk-testabcd',
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
    ])->assertRedirect();

    $settings = $tenant->aiSetting()->first();

    expect($settings->getRawOriginal('api_key_encrypted'))->not->toBe('sk-testabcd')
        ->and($settings->api_key)->toBe('sk-testabcd')
        ->and($settings->maskedApiKey())->toBe('sk-...abcd');
});
