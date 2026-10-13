<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'status'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public function aiSetting(): HasOne
    {
        return $this->hasOne(TenantAiSetting::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
