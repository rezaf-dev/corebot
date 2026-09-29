<?php

use App\Models\Bot;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

it('generates a static widget containing server configuration', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-asset', 'status' => 'active']);
    $bot = Bot::create([
        'tenant_id' => $tenant->id,
        'name' => 'Support',
        'welcome_message' => 'Welcome </script> safely',
        'contact_fields' => ['name', 'email'],
        'contact_required' => ['email'],
        'collect_contact_on_start' => true,
        'widget_config' => [
            'primary_color' => '#123456',
            'launcher_icon' => 'support',
        ],
    ]);

    $path = config('corebot.widget_assets.path').'/'.$bot->public_key.'.js';
    $contents = File::get($path);

    expect($contents)
        ->toContain('const serverBootstrap = {')
        ->toContain('"bot_key":"'.$bot->public_key.'"')
        ->toContain('"api_base":"'.url('/api/public/chat').'"')
        ->toContain('"primary_color":"#123456"')
        ->toContain('"launcher_icon":"support"')
        ->toContain('"collect_contact_on_start":true')
        ->toContain('Welcome \\u003C/script\\u003E safely')
        ->not->toContain('/* __COREBOT_BOOTSTRAP__ */ null');
});

it('removes the static widget when a bot becomes inactive', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-asset-inactive', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);

    $path = config('corebot.widget_assets.path').'/'.$bot->public_key.'.js';
    expect(File::exists($path))->toBeTrue();

    $bot->update(['status' => 'inactive']);

    expect(File::exists($path))->toBeFalse();
});

it('regenerates and deletes static widgets with the bot lifecycle', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-asset-lifecycle', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);
    $path = config('corebot.widget_assets.path').'/'.$bot->public_key.'.js';

    $bot->update(['welcome_message' => 'Updated welcome message']);

    expect(File::get($path))->toContain('Updated welcome message');

    $bot->delete();

    expect(File::exists($path))->toBeFalse();
});

it('builds static widgets for all active bots', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-assets-command', 'status' => 'active']);
    $activeBot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Active']);
    $inactiveBot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Inactive', 'status' => 'inactive']);
    $stalePath = config('corebot.widget_assets.path').'/deleted_bot.js';
    File::put($stalePath, 'stale');

    $this->artisan('widgets:build')
        ->expectsOutputToContain('Generated 1 widget asset(s).')
        ->assertSuccessful();

    expect(File::exists(config('corebot.widget_assets.path').'/'.$activeBot->public_key.'.js'))->toBeTrue()
        ->and(File::exists(config('corebot.widget_assets.path').'/'.$inactiveBot->public_key.'.js'))->toBeFalse()
        ->and(File::exists($stalePath))->toBeFalse();
});
