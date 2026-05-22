<?php

namespace App\Support;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantAccess
{
    public function tenantFor(User $user): ?Tenant
    {
        return $user->isSuperAdmin() ? null : $user->tenant;
    }

    public function scope(Builder $query, User $user): Builder
    {
        return $user->isSuperAdmin() ? $query : $query->where('tenant_id', $user->tenant_id);
    }

    public function ensureTenantAdmin(User $user): Tenant
    {
        if (! $user->isTenantAdmin() || ! $user->tenant) {
            throw new AuthorizationException('A tenant admin account is required.');
        }

        return $user->tenant;
    }

    public function ensureCanAccess(User $user, Model $model): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! property_exists($model, 'tenant_id') && ! isset($model->tenant_id)) {
            throw new AuthorizationException;
        }

        abort_unless((int) $model->tenant_id === (int) $user->tenant_id, 403);
    }

    public function botForTenant(User $user, int|string $botId): Bot
    {
        return $this->scope(Bot::query(), $user)->findOrFail($botId);
    }

    public function sourceForTenant(User $user, int|string $sourceId): KnowledgeSource
    {
        return $this->scope(KnowledgeSource::query(), $user)->findOrFail($sourceId);
    }

    public function conversationForTenant(User $user, int|string $conversationId): ChatConversation
    {
        return $this->scope(ChatConversation::query(), $user)->findOrFail($conversationId);
    }
}
