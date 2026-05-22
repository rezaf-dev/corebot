<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'bot_id',
    'knowledge_source_id',
    'content',
    'embedding_json',
    'token_count',
    'chunk_index',
    'metadata',
])]
class KnowledgeChunk extends Model
{
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    protected function casts(): array
    {
        return [
            'embedding_json' => 'array',
            'metadata' => 'array',
        ];
    }
}
