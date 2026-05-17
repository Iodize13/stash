<?php

namespace App\Support;

use InvalidArgumentException;

class UrlNormalizer
{
    /**
     * Canonical form used to detect duplicate links: lowercase scheme and host,
     * no fragment, no tracking parameters, sorted query, no trailing slash.
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['host'])
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Not an http(s) URL.');
        }

        $host = strtolower($parts['host']);
        $scheme = strtolower($parts['scheme']);
        $port = isset($parts['port']) && ! self::isDefaultPort($scheme, $parts['port'])
            ? ':'.$parts['port']
            : '';
        $path = rtrim($parts['path'] ?? '', '/');

        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(
            $query,
            fn ($key) => ! str_starts_with(strtolower((string) $key), 'utm_')
                && ! in_array(strtolower((string) $key), ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($query);
        $queryString = $query ? '?'.http_build_query($query) : '';

        return "{$scheme}://{$host}{$port}{$path}{$queryString}";
    }

    public static function hash(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }

    public static function domain(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /**
     * Parse "#systems #concurrency, networking" into unique lowercase tags.
     *
     * @return list<string>
     */
    public static function tags(string $input): array
    {
        $tags = preg_split('/[\s,]+/', strtolower($input), -1, PREG_SPLIT_NO_EMPTY);
        $tags = array_map(fn ($tag) => preg_replace('/[^a-z0-9_-]/', '', ltrim($tag, '#')), $tags);

        return array_slice(array_values(array_unique(array_filter($tags))), 0, 10);
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
