<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'bot_id',
    'visitor_id',
    'visitor_name',
    'visitor_email',
    'visitor_phone',
    'source_url',
    'status',
    'ip_address',
    'user_agent',
    'referrer_url',
    'country_code',
    'country_name',
    'city',
    'timezone',
    'language',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'contact_notified_at',
])]
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

    protected function casts(): array
    {
        return [
            'contact_notified_at' => 'datetime',
        ];
    }
}
