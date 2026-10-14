<?php

use App\Models\Bot;
use App\Models\User;
use App\Support\WidgetAsset;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only the public demo page and the real widget to guests', function () {
    $bot = Bot::factory()->create(['system_prompt' => 'Private instructions', 'allowed_domains' => ['customer.example']]);

    $this->get(route('widget.preview', $bot->public_key))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Widget/Preview')
            ->where('bot.name', $bot->name)
            ->where('widget_url', app(WidgetAsset::class)->url($bot))
            ->missing('bot.id')
            ->missing('bot.tenant_id')
            ->missing('bot.system_prompt')
            ->missing('bot.allowed_domains')
            ->missing('widget_config')
            ->missing('contact_config')
            ->missing('embedded'));
});

it('allows users from any workspace to open a public widget demo', function () {
    $user = User::factory()->create();
    $bot = Bot::factory()->create();

    $this->actingAs($user)->get(route('widget.preview', $bot->public_key))->assertSuccessful();
});

it('does not preview inactive or unknown widgets', function () {
    $bot = Bot::factory()->create(['status' => 'inactive']);

    $this->get(route('widget.preview', $bot->public_key))->assertNotFound();
    $this->get(route('widget.preview', 'bot_unknown'))->assertNotFound();
    $this->get(route('widget.preview', $bot->id))->assertNotFound();
});

it('loads the widget and handles ready, failure, timeout, and chat launch states', function () {
    $result = Process::path(base_path())->run('node tests/support/widget-preview.test.mjs');

    expect($result->successful())->toBeTrue($result->errorOutput());
});
