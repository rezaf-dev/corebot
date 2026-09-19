<?php

namespace App\Services\Knowledge;

use App\Support\UrlSafety;
use Throwable;

class WebsiteCrawler
{
    public function __construct(
        private readonly WebPageContentExtractor $extractor,
        private readonly UrlSafety $urlSafety,
    ) {}

    /**
     * @return array{content: string, pages: list<array{url: string, title: string}>, skipped: int}
     */
    public function crawl(string $startUrl, int $pageLimit): array
    {
        $start = $this->normalizeUrl($startUrl);
        $origin = $this->origin($start);
        $limit = min(max(1, $pageLimit), (int) config('corebot.website_crawler.max_page_limit'));
        $contentLimit = (int) config('corebot.website_crawler.max_total_content_length');
        $queue = [$start];
        $queued = [$start => true];
        $pages = [];
        $sections = [];
        $contentLength = 0;
        $skipped = 0;

        while ($queue !== [] && count($pages) < $limit && $contentLength < $contentLimit) {
            $url = array_shift($queue);

            try {
                $page = $this->extractor->fetch($url);
            } catch (Throwable) {
                $skipped++;

                continue;
            }

            $remaining = $contentLimit - $contentLength;
            $content = mb_substr($page['content'], 0, $remaining);
            $sections[] = "## {$page['title']}\nSource: {$page['url']}\n\n{$content}";
            $contentLength += mb_strlen($content);
            $pages[] = ['url' => $page['url'], 'title' => $page['title']];

            foreach ($page['links'] as $link) {
                if (count($queue) + count($pages) >= $limit || isset($queued[$link])) {
                    continue;
                }

                try {
                    $normalized = $this->normalizeUrl($link);
                } catch (Throwable) {
                    continue;
                }

                if ($this->origin($normalized) !== $origin || isset($queued[$normalized])) {
                    continue;
                }

                $queued[$normalized] = true;
                $queue[] = $normalized;
            }
        }

        if ($pages === []) {
            throw new \RuntimeException('No readable pages could be crawled from this website.');
        }

        return [
            'content' => implode("\n\n---\n\n", $sections),
            'pages' => $pages,
            'skipped' => $skipped,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $this->urlSafety->assertPublicHttpUrl($url);
        $parts = parse_url(trim($url));
        $path = $parts['path'] ?? '/';

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($path === '' ? '/' : $path)
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80));
    }
}
