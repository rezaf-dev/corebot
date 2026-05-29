<?php

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

readonly class SearchResult
{
    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    public function __construct(
        public Collection $chunks,
        public bool $confident,
    ) {}
}
