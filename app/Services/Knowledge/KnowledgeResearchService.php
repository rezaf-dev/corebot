<?php

namespace App\Services\Knowledge;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class KnowledgeResearchService
{
    public function __construct(
        private WebPageContentExtractor $pageExtractor,
        private WebSearchClient $webSearch,
    ) {}

    /**
     * @return array{title: string, content: string, sources: list<array{url: string, title: string}>}
     */
    public function fromUrl(string $url): array
    {
        $page = $this->pageExtractor->fetch($url);

        return [
            'title' => $page['title'],
            'content' => $this->formatSection($page['title'], $page['url'], $page['content']),
            'sources' => [
                [
                    'url' => $page['url'],
                    'title' => $page['title'],
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, content: string, sources: list<array{url: string, title: string}>}
     */
    public function fromSearch(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A search query is required.');
        }

        $results = $this->webSearch->search($query);
        $sections = [];
        $sources = [];

        foreach ($results as $result) {
            try {
                $page = $this->pageExtractor->fetch($result['url']);
            } catch (Throwable) {
                if ($result['snippet'] !== '') {
                    $sections[] = $this->formatSection($result['title'], $result['url'], $result['snippet']);
                    $sources[] = [
                        'url' => $result['url'],
                        'title' => $result['title'],
                    ];
                }

                continue;
            }

            $sections[] = $this->formatSection($page['title'], $page['url'], $page['content']);
            $sources[] = [
                'url' => $page['url'],
                'title' => $page['title'],
            ];
        }

        if ($sections === []) {
            throw new RuntimeException('Could not extract readable content from the search results.');
        }

        return [
            'title' => 'Research: '.$query,
            'content' => implode("\n\n---\n\n", $sections),
            'sources' => $sources,
        ];
    }

    private function formatSection(string $title, string $url, string $content): string
    {
        return "## {$title}\nSource: {$url}\n\n{$content}";
    }
}
