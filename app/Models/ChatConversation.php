<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'bot_id', 'visitor_id', 'visitor_name', 'visitor_email', 'visitor_phone', 'source_url', 'status'])]
class ChatConversation extends Model
{
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function retrievalLogs(): HasMany
    {
        return $this->hasMany(RetrievalLog::class, 'conversation_id');
    }
}
