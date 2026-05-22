<?php

use App\Models\User;

it('allows super admins to view horizon in non-local environments', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user)
        ->get('/horizon')
        ->assertSuccessful();
});

it('denies tenant admins access to horizon in non-local environments', function () {
    $user = User::factory()->create(['role' => 'tenant_admin', 'tenant_id' => null]);

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});

it('redirects guests away from horizon', function () {
    $this->get('/horizon')
        ->assertRedirect();
});
