<?php

namespace App\Support;

use InvalidArgumentException;

class UrlSafety
{
    public function __construct(private bool $verifyDns = true) {}

    /**
     * @return array{scheme: string, host: string, port: ?int}
     */
    public function assertPublicHttpUrl(string $url): array
    {
        $normalized = trim($url);

        if ($normalized === '') {
            throw new InvalidArgumentException('A URL is required.');
        }

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Enter a valid URL.');
        }

        $parts = parse_url($normalized);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http and https URLs are allowed.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            throw new InvalidArgumentException('Enter a valid URL.');
        }

        if ($this->isBlockedHost($host)) {
            throw new InvalidArgumentException('This URL is not allowed.');
        }

        $this->assertResolvablePublicHost($host);

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
        ];
    }

    private function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPrivateIp($host);
        }

        return false;
    }

    private function assertResolvablePublicHost(string $host): void
    {
        if (! $this->verifyDns) {
            return;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            throw new InvalidArgumentException('Could not resolve the URL host.');
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && $this->isPrivateIp($ip)) {
                throw new InvalidArgumentException('This URL is not allowed.');
            }
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
