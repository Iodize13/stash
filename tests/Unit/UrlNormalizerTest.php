<?php

use App\Support\UrlNormalizer;

it('normalizes equivalent links to the same canonical form', function (string $input) {
    expect(UrlNormalizer::normalize($input))->toBe('https://example.com/post?a=1&b=2');
})->with([
    'plain' => 'https://example.com/post?b=2&a=1',
    'tracking params' => 'https://example.com/post?utm_source=x&b=2&a=1&fbclid=abc',
    'trailing slash' => 'https://example.com/post/?a=1&b=2',
    'fragment' => 'https://example.com/post?a=1&b=2#section',
    'uppercase host' => 'HTTPS://Example.COM/post?a=1&b=2',
    'default port' => 'https://example.com:443/post?a=1&b=2',
]);

it('keeps a non-default port', function () {
    expect(UrlNormalizer::normalize('http://example.com:8080/x'))->toBe('http://example.com:8080/x');
});

it('rejects non-http urls', function (string $input) {
    UrlNormalizer::normalize($input);
})->with(['javascript:alert(1)', 'ftp://example.com/file', 'not a url', 'file:///etc/passwd'])
    ->throws(InvalidArgumentException::class);

it('extracts the domain without www', function () {
    expect(UrlNormalizer::domain('https://www.Example.com/a'))->toBe('example.com');
});

it('parses tags from free text', function () {
    expect(UrlNormalizer::tags('#Systems #concurrency, networking #systems !!'))
        ->toBe(['systems', 'concurrency', 'networking']);
});
