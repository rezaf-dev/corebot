<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('app:user-create
    {--name= : User full name}
    {--email= : User email address}
    {--password= : Initial password}
    {--role=tenant_admin : User role: tenant_admin or super_admin}
    {--tenant= : Tenant id or slug for tenant_admin users}
    {--verified : Mark the email address as verified}')]
#[Description('Create a user account')]
class CreateUserCommand extends Command
{
    private const ROLES = ['tenant_admin', 'super_admin'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = (string) ($this->option('role') ?: 'tenant_admin');
        $tenant = null;

        if (! in_array($role, self::ROLES, true)) {
            $this->error('Role must be one of: '.implode(', ', self::ROLES));

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = strtolower((string) ($this->option('email') ?: $this->ask('Email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if ($role === 'tenant_admin') {
            $tenant = $this->resolveTenant();

            if (! $tenant) {
                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'tenant_id' => $tenant?->id,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', Rule::in(self::ROLES)],
                'tenant_id' => [Rule::requiredIf($role === 'tenant_admin'), 'nullable', 'integer', Rule::exists(Tenant::class, 'id')],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $this->option('verified') ? now() : null,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $this->info("Created {$user->role} user {$user->email}.");

        if ($tenant) {
            $this->line("Tenant: {$tenant->name} ({$tenant->slug})");
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $tenantIdentifier = (string) ($this->option('tenant') ?: $this->ask('Tenant id or slug'));

        $tenant = Tenant::query()
            ->where('slug', $tenantIdentifier)
            ->when(is_numeric($tenantIdentifier), fn ($query) => $query->orWhereKey((int) $tenantIdentifier))
            ->first();

        if (! $tenant) {
            $this->error('Tenant not found.');
        }

        return $tenant;
    }
}
