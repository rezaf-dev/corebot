<?php

namespace App\Services\Rag;

use App\Models\Bot;
use App\Models\KnowledgeChunk;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SemanticSearchService
{
    public function __construct(private readonly OpenAIService $openAI) {}

    public function search(Bot $bot, string $query): Collection
    {
        return $this->searchWithMeta($bot, $query)->chunks;
    }

    public function searchWithMeta(Bot $bot, string $query): SearchResult
    {
        $embedding = $this->openAI->createEmbedding($bot->tenant, $query, ['bot_id' => $bot->id]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $vector = '['.implode(',', $embedding).']';

            $candidates = KnowledgeChunk::query()
                ->select('*')
                ->selectRaw('embedding <=> ? AS distance', [$vector])
                ->where('tenant_id', $bot->tenant_id)
                ->where('bot_id', $bot->id)
                ->orderByRaw('embedding <=> ?', [$vector])
                ->limit($bot->max_context_chunks)
                ->get()
                ->values();

            return $this->withinThresholdOrNearest($candidates, $bot);
        }

        $candidates = KnowledgeChunk::query()
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($embedding) {
                $chunk->distance = $this->cosineDistance($embedding, $this->embeddingArray($chunk->embedding_json));

                return $chunk;
            })
            ->sortBy('distance')
            ->take($bot->max_context_chunks)
            ->values();

        return $this->withinThresholdOrNearest($candidates, $bot);
    }

    private function withinThresholdOrNearest(Collection $candidates, Bot $bot): SearchResult
    {
        $matches = $candidates
            ->filter(fn ($chunk) => (float) $chunk->distance <= (float) $bot->similarity_threshold)
            ->values();

        $confident = $matches->isNotEmpty();
        $chunks = $confident ? $matches : $candidates;

        return new SearchResult($chunks, $confident);
    }

    private function embeddingArray(mixed $embedding): array
    {
        if (is_array($embedding)) {
            return $embedding;
        }

        if (is_string($embedding)) {
            return json_decode($embedding, true) ?: [];
        }

        return [];
    }

    private function cosineDistance(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = (float) ($b[$i] ?? 0);
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 1.0;
        }

        return 1 - ($dot / (sqrt($normA) * sqrt($normB)));
    }
}
