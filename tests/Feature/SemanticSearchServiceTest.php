<?php

use App\Models\Bot;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use App\Services\Rag\SemanticSearchService;

it('returns the nearest chunks when the similarity threshold is too strict', function () {
    $tenant = Tenant::query()->create([
        'name' => 'OMC',
        'slug' => 'omc',
        'status' => 'active',
    ]);

    $bot = Bot::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Support',
        'status' => 'active',
        'max_context_chunks' => 3,
        'similarity_threshold' => 0.001,
    ]);

    $source = KnowledgeSource::query()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Patient handling',
        'status' => 'ready',
        'raw_text' => 'Duplicate patients are merged by administrators after reviewing identifiers.',
        'chunks_count' => 1,
    ]);

    $embedding = array_fill(0, 1536, 0.0);
    $embedding[1] = 1.0;

    KnowledgeChunk::query()->create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'knowledge_source_id' => $source->id,
        'content' => 'Duplicate patients are merged by administrators after reviewing identifiers.',
        'embedding_json' => $embedding,
        'token_count' => 9,
        'chunk_index' => 0,
        'metadata' => ['source_title' => 'Patient handling'],
    ]);

    $queryEmbedding = array_fill(0, 1536, 0.0);
    $queryEmbedding[0] = 1.0;

    $this->mock(OpenAIService::class)
        ->shouldReceive('createEmbedding')
        ->once()
        ->andReturn($queryEmbedding);

    $chunks = app(SemanticSearchService::class)->search($bot, 'How are duplicate patients handled?');

    expect($chunks)->toHaveCount(1)
        ->and($chunks->first()->content)->toContain('Duplicate patients');
});
