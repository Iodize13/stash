<?php

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Models\Article;
use App\Services\ArticleExtractor;
use App\Services\Fetching\FetchException;
use App\Services\Fetching\HostResolver;
use App\Services\Fetching\SafeHttpFetcher;
use App\Services\Fetching\UnsafeUrlException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Point hostnames at chosen IPs so SSRF checks run without real DNS.
 *
 * @param  array<string, list<string>>  $map
 */
function fakeDns(array $map = []): void
{
    app()->instance(HostResolver::class, new class($map) implements HostResolver
    {
        /** @param array<string, list<string>> $map */
        public function __construct(private array $map) {}

        public function resolve(string $host): array
        {
            return $this->map[$host] ?? ['93.184.216.34'];
        }
    });
}

function articleHtml(string $title = 'Epoll vs select'): string
{
    $paragraph = '<p>Event loops multiplex many sockets on one thread. Non-blocking descriptors let the server '
        .'react only when the kernel reports readiness, which keeps latency low under load and avoids '
        .'one thread per connection. This paragraph is long enough to count as real article content.</p>';

    return <<<HTML
        <!doctype html><html><head><title>{$title}</title>
        <meta name="author" content="Ada Lovelace"></head>
        <body><nav><a href="/">Home</a></nav>
        <article><h1>{$title}</h1>
        {$paragraph}{$paragraph}{$paragraph}
        <p><a href="/docs/epoll">relative link</a> and <a href="javascript:alert(1)">bad link</a></p>
        <img src="https://cdn.example.com/diagram.png" onerror="alert(1)">
        <script>alert('xss')</script>
        {$paragraph}</article></body></html>
        HTML;
}

beforeEach(fn () => fakeDns());

describe('SSRF guard', function () {
    it('blocks hosts that resolve to internal addresses', function (string $ip) {
        fakeDns(['evil.test' => [$ip]]);

        app(SafeHttpFetcher::class)->fetch('https://evil.test/');
    })->with([
        'loopback' => '127.0.0.1',
        'private 10/8' => '10.0.0.5',
        'private 192.168' => '192.168.1.1',
        'cloud metadata' => '169.254.169.254',
        'cgnat' => '100.64.0.1',
        'unspecified' => '0.0.0.0',
        'ipv6 loopback' => '::1',
        'ipv6 unique local' => 'fd00::1',
        'ipv4-mapped ipv6' => '::ffff:127.0.0.1',
    ])->throws(UnsafeUrlException::class);

    it('blocks a host if any of its addresses is internal', function () {
        fakeDns(['mixed.test' => ['93.184.216.34', '10.0.0.1']]);

        app(SafeHttpFetcher::class)->fetch('https://mixed.test/');
    })->throws(UnsafeUrlException::class);

    it('blocks literal internal IPs, odd ports, credentials and other schemes', function (string $url) {
        app(SafeHttpFetcher::class)->fetch($url);
    })->with([
        'http://127.0.0.1/',
        'http://[::1]/',
        'http://169.254.169.254/latest/meta-data/',
        'https://example.com:6379/',
        'https://user:pass@example.com/',
        'file:///etc/passwd',
        'gopher://example.com/',
    ])->throws(UnsafeUrlException::class);

    it('re-checks every redirect target', function () {
        fakeDns(['internal.test' => ['10.1.2.3']]);
        Http::fake(['https://public.test/*' => Http::response('', 302, ['Location' => 'https://internal.test/admin'])]);

        app(SafeHttpFetcher::class)->fetch('https://public.test/start');
    })->throws(UnsafeUrlException::class);

    it('blocks redirects to a literal metadata IP', function () {
        Http::fake(['https://public.test/*' => Http::response('', 301, ['Location' => 'http://169.254.169.254/'])]);

        app(SafeHttpFetcher::class)->fetch('https://public.test/start');
    })->throws(UnsafeUrlException::class);

    it('returns the vetted address that the request is pinned to', function () {
        fakeDns(['example.com' => ['93.184.216.34', '2606:2800:220:1::1']]);

        expect(app(SafeHttpFetcher::class)->assertSafe('https://example.com/post'))
            ->toBe(['example.com', 443, '93.184.216.34']);
    });
});

describe('fetching', function () {
    it('follows relative redirects to public hosts', function () {
        Http::fake([
            'https://example.com/old' => Http::response('', 301, ['Location' => '/new']),
            'https://example.com/new' => Http::response(articleHtml(), 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $page = app(SafeHttpFetcher::class)->fetch('https://example.com/old');

        expect($page->url)->toBe('https://example.com/new');
    });

    it('stops after too many redirects', function () {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://example.com/loop'])]);

        app(SafeHttpFetcher::class)->fetch('https://example.com/loop');
    })->throws(FetchException::class, 'Too many redirects');

    it('classifies failures as retryable or permanent', function (int $status, bool $retryable) {
        Http::fake(['*' => Http::response('nope', $status)]);

        try {
            app(SafeHttpFetcher::class)->fetch('https://example.com/');
            $this->fail('Expected a FetchException');
        } catch (FetchException $e) {
            expect($e->retryable)->toBe($retryable)
                ->and($e->getMessage())->toBe("HTTP {$status}");
        }
    })->with([
        'rate limited' => [429, true],
        'server error' => [503, true],
        'not found' => [404, false],
        'forbidden' => [403, false],
    ]);

    it('treats connection failures as retryable', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        try {
            app(SafeHttpFetcher::class)->fetch('https://example.com/');
            $this->fail('Expected a FetchException');
        } catch (FetchException $e) {
            expect($e->retryable)->toBeTrue();
        }
    });

    it('rejects non-html responses', function () {
        Http::fake(['*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);

        app(SafeHttpFetcher::class)->fetch('https://example.com/paper.pdf');
    })->throws(FetchException::class, 'Unsupported content type application/pdf');
});

describe('FetchArticle job', function () {
    it('is queued when a link is saved', function () {
        Queue::fake();

        $article = Article::factory()->queued()->create();

        Queue::assertPushed(FetchArticle::class, fn (FetchArticle $job) => $job->article->is($article));
    });

    it('is not queued for articles that are already fetched', function () {
        Queue::fake();

        Article::factory()->create();

        Queue::assertNothingPushed();
    });

    it('extracts and stores sanitized content', function () {
        Http::fake(['*' => Http::response(articleHtml(), 200, ['Content-Type' => 'text/html'])]);
        Queue::fake();
        $article = Article::factory()->queued()->create(['url' => 'https://example.com/post']);

        (new FetchArticle($article))->handle(app(SafeHttpFetcher::class), app(ArticleExtractor::class));

        $article->refresh();
        expect($article)
            ->status->toBe(ArticleStatus::Ready)
            ->title->toBe('Epoll vs select')
            ->byline->toBe('Ada Lovelace')
            ->error->toBeNull()
            ->fetched_at->not->toBeNull()
            ->and($article->word_count)->toBeGreaterThan(100)
            ->and($article->content_text)->toContain('Event loops multiplex')
            ->and($article->content_html)
            ->not->toContain('<script')
            ->not->toContain('onerror')
            ->not->toContain('javascript:')
            ->toContain('https://example.com/docs/epoll')
            ->toContain('rel="noopener noreferrer nofollow"');
    });

    it('marks permanent failures as failed without retrying', function () {
        Http::fake(['*' => Http::response('gone', 404)]);

        $article = Article::factory()->queued()->create(['url' => 'https://example.com/missing']);

        expect($article->fresh())
            ->status->toBe(ArticleStatus::Failed)
            ->error->toBe('HTTP 404');
    });

    it('marks blocked urls as failed', function () {
        fakeDns(['internal.test' => ['10.0.0.1']]);

        $article = Article::factory()->queued()->create(['url' => 'https://internal.test/']);

        expect($article->fresh())
            ->status->toBe(ArticleStatus::Failed)
            ->error->toBe('internal.test resolves to a non-public address');
        Http::assertNothingSent();
    });

    it('puts retryable failures back in the queue until attempts run out', function () {
        Http::fake(['*' => Http::response('slow down', 429)]);
        Queue::fake();
        $article = Article::factory()->queued()->create(['url' => 'https://example.com/busy']);
        $job = new FetchArticle($article);

        expect(fn () => $job->handle(app(SafeHttpFetcher::class), app(ArticleExtractor::class)))
            ->toThrow(FetchException::class);

        expect($article->fresh())
            ->status->toBe(ArticleStatus::Queued)
            ->error->toBe('Retrying: HTTP 429');

        $job->failed(FetchException::retryable('HTTP 429'));

        expect($article->fresh())->status->toBe(ArticleStatus::Failed)->error->toBe('HTTP 429');
    });

    it('fails pages without readable content', function () {
        Http::fake(['*' => Http::response('<html><body></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $article = Article::factory()->queued()->create(['url' => 'https://example.com/empty']);

        expect($article->fresh())->status->toBe(ArticleStatus::Failed)->error->toBe('No readable content found');
    });
});
