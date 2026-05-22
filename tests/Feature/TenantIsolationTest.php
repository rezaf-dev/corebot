<?php

use App\Models\Bot;
use App\Models\Tenant;
use App\Models\User;

it('prevents a tenant admin from editing another tenants bot', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'tenant_admin']);
    $bot = Bot::create(['tenant_id' => $tenantB->id, 'name' => 'Other Bot']);

    $this->actingAs($user)
        ->put(route('bots.update', $bot), [
            'name' => 'Changed',
            'status' => 'active',
            'temperature' => 0.2,
            'max_context_chunks' => 6,
            'similarity_threshold' => 0.55,
            'collect_visitor_email' => true,
            'collect_visitor_phone' => false,
        ])
        ->assertForbidden();
});
