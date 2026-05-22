<?php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\AI\OpenAIService;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Rag\TextChunker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessKnowledgeSourceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $knowledgeSourceId) {}

    public function handle(DocumentTextExtractor $extractor, TextChunker $chunker, OpenAIService $openAI): void
    {
        $source = KnowledgeSource::query()->with(['tenant.aiSetting', 'bot'])->findOrFail($this->knowledgeSourceId);

        try {
            if (! $source->tenant->aiSetting?->canUseAi()) {
                throw new \RuntimeException('Tenant AI settings are not active.');
            }

            $source->update(['status' => 'processing', 'error_message' => null]);

            $text = $extractor->extract($source);
            $chunks = $chunker->chunk($text);

            if ($chunks === []) {
                throw new \RuntimeException('No useful chunks were generated.');
            }

            $newChunks = [];

            foreach ($chunks as $index => $content) {
                $embedding = $openAI->createEmbedding($source->tenant, $content, [
                    'bot_id' => $source->bot_id,
                    'knowledge_source_id' => $source->id,
                ]);

                $newChunks[] = [
                    'tenant_id' => $source->tenant_id,
                    'bot_id' => $source->bot_id,
                    'knowledge_source_id' => $source->id,
                    'content' => $content,
                    'embedding_json' => json_encode($embedding),
                    'token_count' => str_word_count($content),
                    'chunk_index' => $index,
                    'metadata' => json_encode([
                        'source_title' => $source->title,
                        'source_type' => $source->type,
                        'chunk_index' => $index,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(function () use ($source, $newChunks) {
                KnowledgeChunk::query()->where('knowledge_source_id', $source->id)->delete();

                foreach ($newChunks as $chunk) {
                    $embedding = $chunk['embedding_json'];

                    if (DB::connection()->getDriverName() === 'pgsql') {
                        DB::table('knowledge_chunks')->insert(array_merge($chunk, [
                            'embedding' => DB::raw("'[".implode(',', json_decode($embedding, true))."]'"),
                        ]));
                    } else {
                        DB::table('knowledge_chunks')->insert($chunk);
                    }
                }

                $source->update([
                    'status' => 'ready',
                    'raw_text' => $source->type === 'text' || $source->type === 'faq' ? $source->raw_text : null,
                    'chunks_count' => count($newChunks),
                    'last_indexed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $source->update([
                'status' => 'failed',
                'error_message' => str($e->getMessage())->limit(500)->toString(),
            ]);
        }
    }
}
