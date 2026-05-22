<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'name',
    'public_key',
    'status',
    'use_tenant_ai_settings',
    'welcome_message',
    'fallback_message',
    'system_prompt',
    'allowed_domains',
    'temperature',
    'max_context_chunks',
    'similarity_threshold',
    'collect_visitor_email',
    'collect_visitor_phone',
])]
class Bot extends Model
{
    public const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are a CRM support assistant.
Answer only using the provided knowledge base context.
You help users understand CRM workflows, records, orders, leads, payments, tickets, statuses, and general system usage.
If the answer is not present in the context, say you do not know and suggest contacting support.
Do not invent business rules, prices, policies, legal advice, medical advice, payment decisions, or account-specific information.
Keep answers clear, practical, and concise.
PROMPT;

    public const DEFAULT_FALLBACK_MESSAGE = 'I am not sure based on the available information. Please contact support, and someone can help you with this.';

    protected static function booted(): void
    {
        static::creating(function (Bot $bot) {
            $bot->public_key ??= 'bot_'.Str::random(32);
            $bot->system_prompt ??= self::DEFAULT_SYSTEM_PROMPT;
            $bot->fallback_message ??= self::DEFAULT_FALLBACK_MESSAGE;
            $bot->welcome_message ??= 'Hi, how can I help with your CRM today?';
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function casts(): array
    {
        return [
            'use_tenant_ai_settings' => 'boolean',
            'allowed_domains' => 'array',
            'temperature' => 'decimal:2',
            'similarity_threshold' => 'decimal:3',
            'collect_visitor_email' => 'boolean',
            'collect_visitor_phone' => 'boolean',
        ];
    }
}
