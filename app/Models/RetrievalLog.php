<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'bot_id', 'conversation_id', 'chat_message_id', 'query', 'selected_chunk_ids', 'distances', 'context_text'])]
class RetrievalLog extends Model
{
    protected function casts(): array
    {
        return [
            'selected_chunk_ids' => 'array',
            'distances' => 'array',
        ];
    }
}
