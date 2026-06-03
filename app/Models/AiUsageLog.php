<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'bot_id',
    'conversation_id',
    'knowledge_source_id',
    'type',
    'provider',
    'model',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'estimated_cost',
    'error_message',
])]
class AiUsageLog extends Model
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
