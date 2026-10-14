<?php

use App\Models\Bot;
use App\Models\User;
use App\Support\WidgetAsset;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

it('previews the real widget with saved appearance, language, and contact configuration', function (bool $embedded) {
    $user = User::factory()->create();
    $bot = Bot::factory()->create([
        'tenant_id' => $user->tenant_id,
        'welcome_message' => 'Welcome to our support team.',
        'system_prompt' => 'Private system instructions',
        'widget_config' => ['title' => 'Help desk', 'primary_color' => '#123456', 'initial_open' => true, 'default_locale' => 'en'],
        'contact_fields' => ['name', 'email', 'phone'],
        'contact_required' => ['email'],
        'collect_contact_on_start' => true,
    ]);

    $this->actingAs($user)->get(route('widget.preview', ['bot' => $bot, 'embedded' => $embedded]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Widget/Preview')
            ->where('bot.id', $bot->id)
            ->missing('bot.system_prompt')
            ->where('widget_url', app(WidgetAsset::class)->url($bot))
            ->where('widget_config.title', 'Help desk')
            ->where('widget_config.primary_color', '#123456')
            ->where('widget_config.initial_open', true)
            ->where('widget_config.welcome_message', 'Welcome to our support team.')
            ->missing('widget_config.avatar_path')
            ->where('widget_localization.locale', 'en')
            ->where('contact_config.fields', ['name', 'email', 'phone'])
            ->where('contact_config.required', ['email'])
            ->where('contact_config.collect_contact_on_start', true)
            ->where('embedded', $embedded));
})->with([false, true]);

it('requires authentication to view the widget demo', function (bool $embedded) {
    $bot = Bot::factory()->create();

    $this->get(route('widget.preview', ['bot' => $bot, 'embedded' => $embedded]))
        ->assertRedirect(route('login'));
})->with([false, true]);

it('prevents access to another workspace widget demo', function (bool $embedded) {
    $user = User::factory()->create();
    $bot = Bot::factory()->create();

    $this->actingAs($user)->get(route('widget.preview', ['bot' => $bot, 'embedded' => $embedded]))
        ->assertForbidden();
})->with([false, true]);

it('does not preview inactive widgets', function () {
    $user = User::factory()->create();
    $bot = Bot::factory()->create(['tenant_id' => $user->tenant_id, 'status' => 'inactive']);

    $this->actingAs($user)->get(route('widget.preview', $bot))->assertNotFound();
});

it('allows platform admins to preview widgets across workspaces', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $bot = Bot::factory()->create();

    $this->actingAs($user)->get(route('widget.preview', $bot))->assertSuccessful();
});

it('provides domain restrictions without altering them for the demo', function () {
    $user = User::factory()->create();
    $bot = Bot::factory()->create(['tenant_id' => $user->tenant_id, 'allowed_domains' => ['customer.example']]);

    $this->actingAs($user)->get(route('widget.preview', $bot))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bot.allowed_domains', ['customer.example'])
            ->has('preview_host'));

    expect($bot->fresh()->allowed_domains)->toBe(['customer.example']);
});

it('loads the widget and handles ready, failure, timeout, and chat launch states', function () {
    $result = Process::path(base_path())->run('node tests/support/widget-preview.test.mjs');

    expect($result->successful())->toBeTrue($result->errorOutput());
});
