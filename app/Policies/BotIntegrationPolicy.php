<?php

namespace App\Policies;

use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\User;
use App\Support\TenantAccess;

class BotIntegrationPolicy
{
    public function __construct(private readonly TenantAccess $access) {}

    public function viewAny(User $user, Bot $bot): bool
    {
        $this->access->ensureCanAccess($user, $bot);

        return true;
    }

    public function create(User $user, Bot $bot): bool
    {
        $this->access->ensureCanAccess($user, $bot);

        return $user->isTenantAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, BotIntegration $integration): bool
    {
        $this->access->ensureCanAccess($user, $integration);

        return $user->isTenantAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, BotIntegration $integration): bool
    {
        $this->access->ensureCanAccess($user, $integration);

        return $user->isTenantAdmin() || $user->isSuperAdmin();
    }

    public function test(User $user, BotIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }
}
