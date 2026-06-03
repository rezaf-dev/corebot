<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'bot_id',
    'bot_integration_id',
    'conversation_id',
    'tool_name',
    'request_payload',
    'response_status',
    'response_body',
    'error',
])]
class IntegrationActionLog extends Model
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(BotIntegration::class, 'bot_integration_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
        ];
    }
}
