<?php

namespace App\Services\Knowledge;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebSearchClient
{
    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException('A search query is required.');
        }

        $tavilyKey = config('corebot.knowledge_research.tavily_api_key');

        if (filled($tavilyKey)) {
            return $this->searchWithTavily($query, $tavilyKey);
        }

        return $this->searchWithDuckDuckGo($query);
    }

    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    private function searchWithTavily(string $query, string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post('https://api.tavily.com/search', [
                'query' => $query,
                'search_depth' => 'basic',
                'include_answer' => false,
                'max_results' => (int) config('corebot.knowledge_research.search_max_results'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Web search failed. Check your Tavily API key.');
        }

        $results = collect($response->json('results', []))
            ->filter(fn (array $result) => filled($result['url'] ?? null))
            ->map(fn (array $result) => [
                'url' => (string) $result['url'],
                'title' => (string) ($result['title'] ?? 'Untitled result'),
                'snippet' => (string) ($result['content'] ?? ''),
            ])
            ->values()
            ->all();

        if ($results === []) {
            throw new RuntimeException('No web search results were found for that query.');
        }

        return $results;
    }

    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    private function searchWithDuckDuckGo(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => config('corebot.knowledge_research.user_agent'),
        ])
            ->timeout(20)
            ->asForm()
            ->post('https://html.duckduckgo.com/html/', [
                'q' => $query,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Web search is temporarily unavailable.');
        }

        libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML($response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (! $loaded) {
            throw new RuntimeException('Web search is temporarily unavailable.');
        }

        $xpath = new DOMXPath($document);
        $links = $xpath->query("//a[contains(@class, 'result__a')]") ?: [];
        $snippets = $xpath->query("//a[contains(@class, 'result__snippet')]") ?: [];
        $results = [];
        $limit = (int) config('corebot.knowledge_research.search_max_results');

        for ($index = 0; $index < min($links->length, $limit); $index++) {
            $link = $links->item($index);
            $href = $link?->getAttribute('href');

            if (! is_string($href) || $href === '') {
                continue;
            }

            $url = $this->normalizeDuckDuckGoUrl($href);

            if ($url === null) {
                continue;
            }

            $snippetNode = $snippets->item($index);
            $results[] = [
                'url' => $url,
                'title' => trim((string) $link?->textContent) ?: 'Untitled result',
                'snippet' => trim((string) $snippetNode?->textContent),
            ];
        }

        if ($results === []) {
            throw new RuntimeException('No web search results were found for that query.');
        }

        return $results;
    }

    private function normalizeDuckDuckGoUrl(string $href): ?string
    {
        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '/l/?')) {
            parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $params);
            $uddg = $params['uddg'] ?? null;

            return is_string($uddg) && filter_var($uddg, FILTER_VALIDATE_URL) ? $uddg : null;
        }

        return null;
    }
}
