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
        $embedding = $this->openAI->createEmbedding($bot->tenant, $query, ['bot_id' => $bot->id]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $vector = '['.implode(',', $embedding).']';

            return KnowledgeChunk::query()
                ->select('*')
                ->selectRaw('embedding <=> ? AS distance', [$vector])
                ->where('tenant_id', $bot->tenant_id)
                ->where('bot_id', $bot->id)
                ->orderByRaw('embedding <=> ?', [$vector])
                ->limit($bot->max_context_chunks)
                ->get()
                ->filter(fn ($chunk) => (float) $chunk->distance <= (float) $bot->similarity_threshold)
                ->values();
        }

        return KnowledgeChunk::query()
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($embedding) {
                $chunk->distance = $this->cosineDistance($embedding, $chunk->embedding_json ?? []);

                return $chunk;
            })
            ->filter(fn ($chunk) => $chunk->distance <= (float) $bot->similarity_threshold)
            ->sortBy('distance')
            ->take($bot->max_context_chunks)
            ->values();
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
