<?php

namespace App\Models;

use App\Enums\IntegrationType;
use Database\Factories\BotIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'bot_id',
    'type',
    'name',
    'description',
    'config',
    'credentials',
    'allowed_domains',
    'enabled',
])]
class BotIntegration extends Model
{
    /** @use HasFactory<BotIntegrationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['tool_name'];

    protected static function booted(): void
    {
        static::creating(function (BotIntegration $integration): void {
            if (! filled($integration->description)) {
                $integration->description = $integration->name;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(IntegrationActionLog::class);
    }

    public function toolName(): string
    {
        return 'integration_'.$this->id;
    }

    public function getToolNameAttribute(): string
    {
        return $this->toolName();
    }

    public function integrationType(): IntegrationType
    {
        return $this->type instanceof IntegrationType
            ? $this->type
            : IntegrationType::from((string) $this->type);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfig(): array
    {
        return is_array($this->config) ? $this->config : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedCredentials(): array
    {
        return is_array($this->credentials) ? $this->credentials : [];
    }

    /**
     * @return list<string>
     */
    public function resolvedAllowedDomains(): array
    {
        return collect($this->allowed_domains ?? [])
            ->map(fn ($domain) => strtolower(trim((string) $domain)))
            ->filter()
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'type' => IntegrationType::class,
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'allowed_domains' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
