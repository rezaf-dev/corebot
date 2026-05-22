<?php

use App\Services\Rag\TextChunker;

it('chunks long text with overlap-sized output', function () {
    $words = collect(range(1, 1800))->map(fn ($i) => "word{$i}")->implode(' ');

    $chunks = app(TextChunker::class)->chunk($words);

    expect($chunks)->toHaveCount(3)
        ->and(str_word_count($chunks[0]))->toBe(800)
        ->and(str_word_count($chunks[1]))->toBe(800)
        ->and($chunks[1])->toContain('word701');
});
