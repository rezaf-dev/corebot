<?php

use App\Services\Knowledge\WebPageContentExtractor;
use App\Services\Knowledge\WebsiteCrawler;
use App\Support\UrlSafety;
use Illuminate\Support\Facades\Http;

it('crawls same origin pages up to the page limit', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/docs' => Http::response(
            '<html><head><title>Docs</title></head><body><main>Documentation home with enough useful words for indexing.</main><a href="/docs/install">Install</a><a href="https://other.test/page">Other</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/docs/install' => Http::response(
            '<html><head><title>Install</title></head><body><main>Installation instructions with enough useful words for indexing.</main><a href="/docs/extra">Extra</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $urlSafety = new UrlSafety(verifyDns: false);
    $crawler = new WebsiteCrawler(new WebPageContentExtractor($urlSafety), $urlSafety);
    $result = $crawler->crawl('https://example.com/docs', 2);

    expect($result['pages'])->toHaveCount(2)
        ->and($result['pages'][0]['url'])->toBe('https://example.com/docs')
        ->and($result['pages'][1]['url'])->toBe('https://example.com/docs/install')
        ->and($result['content'])->toContain('Documentation home')
        ->and($result['content'])->toContain('Installation instructions');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'other.test'));
});

it('deduplicates page links', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><title>Home</title></head><body><main>Home page content.</main><a href="/about">About</a><a href="https://example.com/about#team">Team</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/about' => Http::response(
            '<html><head><title>About</title></head><body><main>About page content.</main></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $urlSafety = new UrlSafety(verifyDns: false);
    $crawler = new WebsiteCrawler(new WebPageContentExtractor($urlSafety), $urlSafety);
    $result = $crawler->crawl('https://example.com/', 10);

    expect($result['pages'])->toHaveCount(2);
    Http::assertSentCount(2);
});

it('stops at the configured hard page ceiling', function () {
    config()->set('corebot.website_crawler.max_page_limit', 2);
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><title>Home</title></head><body><main>Home content.</main><a href="/one">One</a><a href="/two">Two</a></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/one' => Http::response(
            '<html><head><title>One</title></head><body><main>First child page.</main></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $urlSafety = new UrlSafety(verifyDns: false);
    $crawler = new WebsiteCrawler(new WebPageContentExtractor($urlSafety), $urlSafety);
    $result = $crawler->crawl('https://example.com/', 20);

    expect($result['pages'])->toHaveCount(2);
    Http::assertSentCount(2);
});
