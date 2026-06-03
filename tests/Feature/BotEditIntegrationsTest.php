<?php

use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('includes bot integrations on the edit page', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-bot-edit-int', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);

    BotIntegration::factory()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'name' => 'Capture lead',
    ]);

    $this->actingAs($user)
        ->get(route('bots.edit', $bot))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bots/Form')
            ->has('bot.integrations', 1)
            ->where('bot.integrations.0.name', 'Capture lead')
        );
});

it('returns to the bot edit page with integrations after creating one', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-bot-edit-int-store', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support']);

    $this->actingAs($user)
        ->from(route('bots.edit', $bot))
        ->post(route('bots.integrations.store', $bot), [
            'type' => 'create_lead',
            'name' => 'New lead capture',
            'description' => 'Save visitor contact details.',
            'enabled' => true,
        ])
        ->assertRedirect(route('bots.edit', $bot));

    $this->actingAs($user)
        ->get(route('bots.edit', $bot))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bots/Form')
            ->has('bot.integrations', 1)
            ->where('bot.integrations.0.name', 'New lead capture')
        );
});
