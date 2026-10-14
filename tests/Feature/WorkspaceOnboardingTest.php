<?php

use App\Models\Bot;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WidgetConfig;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

it('guides a new workspace through AI bot knowledge and widget setup', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    $this->actingAs($user)->get(route('conversations.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.completed', 0)
            ->where('onboarding.total', 4)
            ->where('onboarding.steps.0.key', 'ai')
            ->where('onboarding.steps.0.url', route('ai-settings.edit'))
            ->where('onboarding.steps.1.url', route('bots.create'))
            ->where('onboarding.steps.2.available', false)
            ->where('onboarding.steps.3.available', false));
});

it('tracks readiness and hides the guide after setup is complete', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->aiSetting()->create(['is_active' => false]);
    $bot->knowledgeSources()->create(['tenant_id' => $tenant->id, 'title' => 'FAQ', 'type' => 'text', 'status' => 'processing']);

    $this->actingAs($user)->get(route('settings.index'))->assertInertia(fn (Assert $page) => $page
        ->where('onboarding.completed', 1)
        ->where('onboarding.steps.0.complete', false)
        ->where('onboarding.steps.1.complete', true)
        ->where('onboarding.steps.2.complete', false)
        ->where('onboarding.steps.3.complete', false));

    $settings = $tenant->aiSetting()->first();
    $settings->api_key = 'sk-test';
    $settings->is_active = true;
    $settings->save();
    $bot->knowledgeSources()->update(['status' => 'ready']);
    $bot->update(['widget_config' => WidgetConfig::DEFAULTS]);

    $this->get(route('settings.index'))->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
});

it('does not use another workspace setup or inactive bots to complete onboarding', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    Bot::factory()->create(['tenant_id' => $tenant->id, 'status' => 'inactive', 'widget_config' => WidgetConfig::DEFAULTS]);
    $other = Tenant::factory()->create();
    $bot = Bot::factory()->create(['tenant_id' => $other->id, 'widget_config' => WidgetConfig::DEFAULTS]);
    $bot->knowledgeSources()->create(['tenant_id' => $other->id, 'title' => 'Other FAQ', 'type' => 'text', 'status' => 'ready']);
    $settings = $other->aiSetting()->create(['is_active' => true]);
    $settings->api_key = 'sk-other';
    $settings->save();

    $this->actingAs($user)->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->where('onboarding.completed', 0));
});

it('does not offer configuration steps to agents or platform administrators', function (string $role) {
    $user = User::factory()->create(['tenant_id' => $role === 'agent' ? Tenant::factory()->create()->id : null, 'role' => $role]);

    $this->actingAs($user)->get(route('conversations.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
})->with(['agent', 'super_admin']);

it('renders simplified settings and exercises the setup guide controls', function () {
    $result = Process::path(base_path())->run('node tests/support/settings-ui.test.mjs');

    expect($result->successful())->toBeTrue("Settings UI checks failed:\n".$result->errorOutput());
});

it('creates a bot with default messages without requiring text customization', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    $this->actingAs($user)->post(route('bots.store'), [
        'name' => 'Support', 'status' => 'active',
        'welcome_message' => '', 'fallback_message' => '', 'system_prompt' => '',
        'temperature' => '0.20', 'max_context_chunks' => 6, 'similarity_threshold' => '0.550',
    ])->assertRedirect(route('bots.index'))->assertSessionHasNoErrors();

    expect($tenant->bots()->first())
        ->welcome_message->toBe(Bot::DEFAULT_WELCOME_MESSAGE)
        ->fallback_message->toBe(Bot::DEFAULT_FALLBACK_MESSAGE)
        ->system_prompt->toBe(Bot::DEFAULT_SYSTEM_PROMPT);
});
