<?php

use App\Models\Bot;
use App\Models\Tenant;

it('rejects public chat for inactive bots', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot', 'status' => 'inactive']);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-1',
        'source_url' => 'https://example.com/help',
    ])->assertNotFound();
});

it('validates allowed domains', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-2', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Bot', 'allowed_domains' => ['allowed.test']]);

    $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $bot->public_key,
        'visitor_id' => 'visitor-1',
        'source_url' => 'https://blocked.test/help',
    ])->assertForbidden();
});
