<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'bot_id',
    'type',
    'title',
    'status',
    'original_file_path',
    'original_file_name',
    'mime_type',
    'file_size',
    'raw_text',
    'error_message',
    'chunks_count',
    'last_indexed_at',
])]
class KnowledgeSource extends Model
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    protected function casts(): array
    {
        return [
            'last_indexed_at' => 'datetime',
        ];
    }
}
