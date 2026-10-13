<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'email_verified_at', 'password', 'tenant_id', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['is_active' => true];

    public function hasWorkspaceAccess(): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || (in_array($this->role, ['tenant_admin', 'agent'], true) && $this->tenant_id !== null && $this->tenant !== null));
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isTenantAdmin();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === 'tenant_admin' && $this->tenant_id !== null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
