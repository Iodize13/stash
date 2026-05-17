<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\ArticleExtractor;
use App\Services\Fetching\FetchException;
use App\Services\Fetching\SafeHttpFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class FetchArticle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    /** A link deleted while queued has nothing left to fetch. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Article $article) {}

    public function uniqueId(): string
    {
        return (string) $this->article->id;
    }

    public function handle(SafeHttpFetcher $fetcher, ArticleExtractor $extractor): void
    {
        $this->article->update(['status' => ArticleStatus::Fetching, 'error' => null]);

        try {
            $content = $extractor->extract($fetcher->fetch($this->article->url));
        } catch (FetchException $e) {
            if (! $e->retryable) {
                $this->fail($e);

                return;
            }

            if ($this->attempts() < $this->tries) {
                $this->article->update(['status' => ArticleStatus::Queued, 'error' => "Retrying: {$e->getMessage()}"]);
            }

            throw $e;
        }

        $this->article->update([
            ...$content,
            'status' => ArticleStatus::Ready,
            'error' => null,
            'fetched_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof FetchException
            ? $exception->getMessage()
            : 'Unexpected error while fetching';

        $this->article->update([
            'status' => ArticleStatus::Failed,
            'error' => Str::limit($message, 250),
        ]);
    }
}
