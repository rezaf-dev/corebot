<?php

namespace App\Jobs;

use App\Enums\KnowledgeSourceStatus;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\AI\OpenAIService;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Knowledge\WebsiteCrawler;
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

    public function handle(DocumentTextExtractor $extractor, TextChunker $chunker, OpenAIService $openAI, WebsiteCrawler $crawler): void
    {
        $source = KnowledgeSource::query()->with(['tenant.aiSetting', 'bot'])->findOrFail($this->knowledgeSourceId);

        if ($this->wasCancelled($source)) {
            return;
        }

        try {
            if (! $source->tenant->aiSetting?->canUseAi()) {
                throw new \RuntimeException('Tenant AI settings are not active.');
            }

            $source->update(['status' => KnowledgeSourceStatus::Processing->value, 'error_message' => null]);

            if ($this->wasCancelled($source)) {
                return;
            }

            if ($source->type === 'website') {
                $crawl = $crawler->crawl((string) $source->source_url, (int) $source->crawl_page_limit);
                $source->update([
                    'raw_text' => $crawl['content'],
                    'crawled_pages_count' => count($crawl['pages']),
                ]);

                $chunks = collect($crawl['pages'])
                    ->flatMap(function (array $page) use ($chunker): array {
                        return array_map(fn (string $content): array => [
                            'content' => $content,
                            'source_title' => $page['title'],
                            'source_url' => $page['url'],
                        ], $chunker->chunk($page['content']));
                    })
                    ->values()
                    ->all();
            } else {
                $text = $extractor->extract($source);
                $chunks = array_map(fn (string $content): array => [
                    'content' => $content,
                    'source_title' => $source->title,
                    'source_url' => $source->source_url,
                ], $chunker->chunk($text));
            }

            if ($chunks === []) {
                throw new \RuntimeException('No useful chunks were generated.');
            }

            $newChunks = [];

            foreach ($chunks as $index => $chunk) {
                if ($this->wasCancelled($source)) {
                    return;
                }

                $embedding = $openAI->createEmbedding($source->tenant, $chunk['content'], [
                    'bot_id' => $source->bot_id,
                    'knowledge_source_id' => $source->id,
                ]);

                $newChunks[] = [
                    'tenant_id' => $source->tenant_id,
                    'bot_id' => $source->bot_id,
                    'knowledge_source_id' => $source->id,
                    'content' => $chunk['content'],
                    'embedding_json' => json_encode($embedding),
                    'token_count' => str_word_count($chunk['content']),
                    'chunk_index' => $index,
                    'metadata' => json_encode([
                        'source_title' => $chunk['source_title'],
                        'source_type' => $source->type,
                        'source_url' => $chunk['source_url'],
                        'chunk_index' => $index,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($this->wasCancelled($source)) {
                return;
            }

            DB::transaction(function () use ($source, $newChunks) {
                if ($this->wasCancelled($source)) {
                    return;
                }

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
                    'status' => KnowledgeSourceStatus::Ready->value,
                    'raw_text' => in_array($source->type, ['text', 'faq', 'website'], true) ? $source->raw_text : null,
                    'chunks_count' => count($newChunks),
                    'last_indexed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $source->refresh();

            if ($this->wasCancelled($source)) {
                return;
            }

            $source->update([
                'status' => KnowledgeSourceStatus::Failed->value,
                'error_message' => str($e->getMessage())->limit(500)->toString(),
            ]);
        }
    }

    private function wasCancelled(KnowledgeSource $source): bool
    {
        $source->refresh();

        return KnowledgeSourceStatus::fromStored($source->status) === KnowledgeSourceStatus::Cancelled;
    }
}
