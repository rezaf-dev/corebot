<?php

namespace App\Models;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'assigned_to',
    'support_mode',
    'resolved_at',
    'tenant_id',
    'bot_id',
    'visitor_id',
    'public_session_token',
    'visitor_name',
    'visitor_email',
    'visitor_phone',
    'source_url',
    'status',
    'handoff_reason',
    'ip_address',
    'user_agent',
    'referrer_url',
    'country_code',
    'country_name',
    'city',
    'timezone',
    'language',
    'language_detected',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'contact_notified_at',
    'manual_handoff_notified_at',
])]
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    protected $attributes = ['support_mode' => 'ai', 'status' => 'open', 'language_detected' => false];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function scopeNeedsReply(Builder $query): void
    {
        $query->where('status', '!=', 'resolved')->where('support_mode', 'human')
            ->where(function ($query) {
                $query->whereRaw("COALESCE((SELECT MAX(id) FROM chat_messages WHERE conversation_id = chat_conversations.id AND role = 'user'), 0) > COALESCE((SELECT MAX(id) FROM chat_messages WHERE conversation_id = chat_conversations.id AND role = 'admin'), 0)")
                    ->orWhere(function ($query) {
                        $query->where('status', 'escalated')->whereDoesntHave('messages', fn ($query) => $query->where('role', 'admin'));
                    });
            });
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function integrationActionLogs(): HasMany
    {
        return $this->hasMany(IntegrationActionLog::class, 'conversation_id');
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
            'language_detected' => 'boolean',
            'resolved_at' => 'datetime',
            'contact_notified_at' => 'datetime',
            'manual_handoff_notified_at' => 'datetime',
        ];
    }
}
