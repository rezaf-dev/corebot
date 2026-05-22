<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
class AiUsageLog extends Model {}
