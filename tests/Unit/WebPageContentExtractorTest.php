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

it('preserves labeled navigation and download links', function () {
    config()->set('corebot.website_crawler.max_links_per_page', 10);
    $extractor = new WebPageContentExtractor(new UrlSafety);

    $result = $extractor->extractFromHtml(
        'https://example.com/docs/start',
        '<html><head><title>Start</title></head><body><main>Read the guide.</main><a href="../pricing">Pricing</a><a href="/files/guide.pdf">Get the PDF</a></body></html>',
    );

    expect($result['links'])->toBe([
        ['url' => 'https://example.com/pricing', 'label' => 'Pricing', 'is_download' => false],
        ['url' => 'https://example.com/files/guide.pdf', 'label' => 'Get the PDF', 'is_download' => true],
    ]);
});
