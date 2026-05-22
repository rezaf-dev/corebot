<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'tenant_id',
    'provider',
    'api_key_encrypted',
    'base_url',
    'chat_model',
    'embedding_model',
    'embedding_dimensions',
    'monthly_token_limit',
    'monthly_message_limit',
    'is_active',
    'last_tested_at',
    'last_test_status',
    'last_test_error',
])]
class TenantAiSetting extends Model
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function maskedApiKey(): ?string
    {
        if (! $this->api_key) {
            return null;
        }

        return str_starts_with($this->api_key, 'sk-') ? 'sk-...'.substr($this->api_key, -4) : '...'.substr($this->api_key, -4);
    }

    public function canUseAi(): bool
    {
        return $this->is_active && filled($this->api_key);
    }

    public function getApiKeyAttribute(): ?string
    {
        return $this->api_key_encrypted ? Crypt::decryptString($this->api_key_encrypted) : null;
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
