<?php

namespace App\Services\Knowledge;

use App\Support\UrlSafety;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebPageContentExtractor
{
    public function __construct(private UrlSafety $urlSafety) {}

    /**
     * @return array{url: string, title: string, content: string}
     */
    public function fetch(string $url): array
    {
        $this->urlSafety->assertPublicHttpUrl($url);

        $response = Http::withHeaders([
            'User-Agent' => config('corebot.knowledge_research.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8',
        ])
            ->timeout((int) config('corebot.knowledge_research.fetch_timeout'))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Could not fetch the page (HTTP '.$response->status().').');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_contains($contentType, 'text/plain')) {
            $text = trim($response->body());

            return [
                'url' => $url,
                'title' => $this->titleFromUrl($url),
                'content' => $this->truncate($text),
            ];
        }

        return $this->extractFromHtml($url, $response->body());
    }

    /**
     * @return array{url: string, title: string, content: string}
     */
    public function extractFromHtml(string $url, string $html): array
    {
        $title = $this->extractTitle($html) ?: $this->titleFromUrl($url);
        $content = $this->truncate($this->htmlToText($html));

        if ($content === '') {
            throw new RuntimeException('No readable content was found on this page.');
        }

        return [
            'url' => $url,
            'title' => $title,
            'content' => $content,
        ];
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $this->cleanText(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return $this->cleanText(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
        }

        return null;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;

        libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (! $loaded) {
            return $this->cleanText(strip_tags($html));
        }

        $xpath = new DOMXPath($document);

        foreach (['//script', '//style', '//nav', '//footer', '//header', '//aside', '//form'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $text = $body?->textContent ?? $document->textContent;

        return $this->cleanText((string) $text);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function titleFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'Web page';
    }

    private function truncate(string $text): string
    {
        $limit = (int) config('corebot.knowledge_research.max_content_length');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit))."\n\n[Content truncated]";
    }
}
