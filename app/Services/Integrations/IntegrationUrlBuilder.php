<?php

namespace App\Services\Integrations;

use InvalidArgumentException;

class IntegrationUrlBuilder
{
    /**
     * @return list<string>
     */
    public function pathParameterNames(string $urlTemplate): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $urlTemplate, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function build(string $urlTemplate, array $arguments): string
    {
        $url = $urlTemplate;

        foreach ($this->pathParameterNames($urlTemplate) as $name) {
            $value = $arguments[$name] ?? null;

            if (! is_string($value) && ! is_numeric($value)) {
                throw new InvalidArgumentException("Missing path parameter: {$name}");
            }

            $encoded = rawurlencode((string) $value);
            $url = str_replace('{'.$name.'}', $encoded, $url);
        }

        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $url)) {
            throw new InvalidArgumentException('URL template still contains unresolved placeholders.');
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    public function domainsForAllowlist(string $urlTemplate): array
    {
        $sample = $this->build($urlTemplate, array_fill_keys($this->pathParameterNames($urlTemplate), 'sample'));

        $host = strtolower(parse_url($sample, PHP_URL_HOST) ?: '');

        return $host !== '' ? [$host] : [];
    }
}
