<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffAccess
{
    public function ensureCanManage(User $actor, User $staff): void
    {
        abort_unless($actor->hasWorkspaceAccess() && ($actor->isSuperAdmin() || ($actor->isTenantAdmin() && $staff->role === 'agent' && $staff->tenant_id === $actor->tenant_id)), 403);
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $staff, array $attributes, bool $delete = false): void
    {
        if (isset($attributes['tenant_id'])) {
            $attributes['tenant_id'] = (int) $attributes['tenant_id'];
        }

        DB::transaction(function () use ($staff, $attributes, $delete): void {
            $users = User::query()->orderBy('id')->lockForUpdate()->get();
            $current = $users->firstWhere('id', $staff->id);
            abort_unless($current, 404);
            if ($current->is_active && $current->isAdmin() && ($delete || ! ($attributes['is_active'] ?? $current->is_active) || ($attributes['role'] ?? $current->role) !== $current->role || ($attributes['tenant_id'] ?? $current->tenant_id) !== $current->tenant_id)) {
                $admins = $users->where('is_active', true)->where('role', $current->role);
                if ($current->isTenantAdmin()) {
                    $admins = $admins->where('tenant_id', $current->tenant_id);
                }
                if ($admins->count() <= 1) {
                    throw ValidationException::withMessages(['role' => 'Keep at least one active admin for this workspace.']);
                }
            }
            if ($delete || ! ($attributes['is_active'] ?? $current->is_active) || (array_key_exists('tenant_id', $attributes) && $attributes['tenant_id'] !== $current->tenant_id)) {
                ChatConversation::query()->where('assigned_to', $current->id)->update(['assigned_to' => null]);
            }
            if ($delete) {
                $current->delete();
            } else {
                $current->update($attributes);
            }
        }, 3);
    }
}
