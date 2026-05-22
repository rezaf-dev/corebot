<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a tenant admin user from command options', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => 'active',
    ]);

    $this->artisan('app:user-create', [
        '--name' => 'Taylor Admin',
        '--email' => 'taylor@example.com',
        '--password' => 'password-secret',
        '--role' => 'tenant_admin',
        '--tenant' => 'acme',
        '--verified' => true,
    ])
        ->expectsOutput('Created tenant_admin user taylor@example.com.')
        ->expectsOutput('Tenant: Acme (acme)')
        ->assertSuccessful();

    $user = User::query()->where('email', 'taylor@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe('tenant_admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password-secret', $user->password))->toBeTrue();
});

it('creates a super admin user without a tenant', function () {
    $this->artisan('app:user-create', [
        '--name' => 'Super Admin',
        '--email' => 'super-admin@example.com',
        '--password' => 'password-secret',
        '--role' => 'super_admin',
    ])
        ->expectsOutput('Created super_admin user super-admin@example.com.')
        ->doesntExpectOutputToContain('Tenant:')
        ->assertSuccessful();

    $user = User::query()->where('email', 'super-admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBeNull()
        ->and($user->role)->toBe('super_admin');
});

it('rejects duplicate email addresses', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('app:user-create', [
        '--name' => 'Existing User',
        '--email' => 'existing@example.com',
        '--password' => 'password-secret',
        '--role' => 'super_admin',
    ])
        ->expectsOutput('The email has already been taken.')
        ->assertFailed();
});

it('rejects missing tenants for tenant admins', function () {
    $this->artisan('app:user-create', [
        '--name' => 'Tenant Admin',
        '--email' => 'tenant-admin@example.com',
        '--password' => 'password-secret',
        '--role' => 'tenant_admin',
        '--tenant' => 'missing-tenant',
    ])
        ->expectsOutput('Tenant not found.')
        ->assertFailed();

    expect(User::query()->where('email', 'tenant-admin@example.com')->exists())->toBeFalse();
});
