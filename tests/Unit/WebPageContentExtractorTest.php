<?php

use App\Services\Knowledge\WebPageContentExtractor;
use App\Support\UrlSafety;

it('extracts readable text from html', function () {
    $extractor = new WebPageContentExtractor(new UrlSafety);

    $result = $extractor->extractFromHtml(
        'https://example.com/article',
        '<html><head><title>Article Title</title></head><body><nav>Menu</nav><main><p>Important knowledge here.</p></main><footer>Footer</footer></body></html>',
    );

    expect($result['title'])->toBe('Article Title')
        ->and($result['content'])->toContain('Important knowledge here.')
        ->and($result['content'])->not->toContain('Menu')
        ->and($result['content'])->not->toContain('Footer');
});

it('blocks localhost urls', function () {
    $urlSafety = new UrlSafety;

    expect(fn () => $urlSafety->assertPublicHttpUrl('http://127.0.0.1/test'))
        ->toThrow(InvalidArgumentException::class, 'This URL is not allowed.');
});
