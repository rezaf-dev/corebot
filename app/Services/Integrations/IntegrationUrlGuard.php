<?php

namespace App\Services\Integrations;

use InvalidArgumentException;

class IntegrationUrlGuard
{
    /**
     * @param  list<string>  $allowedDomains
     */
    public function assertAllowed(string $url, array $allowedDomains): void
    {
        if ($allowedDomains === []) {
            throw new InvalidArgumentException('Integration has no allowed domains configured.');
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === '' || ! in_array($host, $allowedDomains, true)) {
            throw new InvalidArgumentException('URL host is not in the integration allowlist.');
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http and https URLs are allowed.');
        }
    }

    /**
     * @return list<string>
     */
    public function domainsFromUrl(string $url): array
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

        return $host !== '' ? [$host] : [];
    }
}
