<?php

namespace App\Services\Fetching;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use League\Uri\BaseUri;

/**
 * Fetches user-supplied URLs without letting them reach internal services (SSRF).
 *
 * Every hop (the original URL and each redirect) is checked the same way:
 * http(s) only, default ports only, and every resolved address must be a public
 * one. The connection is then pinned to the checked address with CURLOPT_RESOLVE,
 * so a second DNS lookup cannot swap in a private IP (DNS rebinding).
 */
class SafeHttpFetcher
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_REDIRECTS = 5;

    private const HTML_TYPES = ['text/html', 'application/xhtml+xml'];

    public function __construct(private HostResolver $resolver) {}

    public function fetch(string $url): FetchedPage
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->assertSafe($url);

            $response = $this->request($url, $host, $port, $ip);

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    throw new FetchException("HTTP {$response->status()} without a Location header");
                }

                $url = BaseUri::from($url)->resolve($location)->getUri()->toString();

                continue;
            }

            return $this->toPage($url, $response);
        }

        throw new FetchException('Too many redirects');
    }

    /**
     * @return array{string, int, string} host, port, and the vetted IP to connect to
     */
    public function assertSafe(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeUrlException('Only http(s) URLs can be fetched');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('URLs with credentials are not allowed');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrlException("Port {$port} is not allowed");
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolver->resolve($host);

        if ($ips === []) {
            throw FetchException::retryable("Could not resolve {$host}");
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrlException("{$host} resolves to a non-public address");
            }
        }

        return [$host, $port, $ips[0]];
    }

    public static function isPublicIp(string $ip): bool
    {
        // GLOBAL_RANGE rejects private, loopback, link-local (incl. cloud metadata),
        // CGNAT, reserved, documentation and IPv4-mapped/translated IPv6 ranges.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    private function request(string $url, string $host, int $port, string $ip): Response
    {
        $pinned = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'curl' => [
                    CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"],
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_MAXFILESIZE_LARGE => self::MAX_BYTES,
                ],
            ])
                ->withUserAgent('StashBot/1.0 (+'.config('app.url').')')
                ->accept('text/html,application/xhtml+xml;q=0.9,*/*;q=0.1')
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException $e) {
            throw FetchException::retryable('Connection failed: '.str($e->getMessage())->limit(120));
        }
    }

    private function toPage(string $url, Response $response): FetchedPage
    {
        $status = $response->status();

        if ($status === 429 || $response->serverError()) {
            throw FetchException::retryable("HTTP {$status}");
        }

        if (! $response->successful()) {
            throw new FetchException("HTTP {$status}");
        }

        $contentType = strtolower($response->header('Content-Type'));
        $mime = trim(explode(';', $contentType)[0]);

        if ($mime !== '' && ! in_array($mime, self::HTML_TYPES, true)) {
            throw new FetchException("Unsupported content type {$mime}");
        }

        $html = $response->body();

        if (strlen($html) > self::MAX_BYTES) {
            throw new FetchException('Page is larger than 5 MB');
        }

        if (preg_match('/charset=["\']?([\w-]+)/', $contentType, $m) && strtolower($m[1]) !== 'utf-8') {
            $converted = @mb_convert_encoding($html, 'UTF-8', $m[1]);
            $html = $converted === false ? $html : $converted;
        }

        return new FetchedPage($url, $html);
    }
}
