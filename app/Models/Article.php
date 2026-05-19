<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Jobs\FetchArticle;
use App\Observers\ArticleObserver;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'url', 'url_hash', 'domain', 'title', 'byline', 'excerpt', 'content_html', 'content_text', 'tags', 'status', 'error', 'word_count', 'fetched_at', 'read_at', 'archived_at'])]
#[Hidden(['content_html', 'content_text'])]
#[ObservedBy(ArticleObserver::class)]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => ArticleStatus::class,
            'fetched_at' => 'datetime',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Highlight, $this> */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /** @param Builder<Article> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at')->whereNull('archived_at');
    }

    /** @param Builder<Article> $query */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /** @param Builder<Article> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [ArticleStatus::Queued, ArticleStatus::Fetching]);
    }

    /** @param Builder<Article> $query */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where('title', 'ilike', $like)
            ->orWhere('url', 'ilike', $like)
            ->orWhere('excerpt', 'ilike', $like)
            ->orWhereRaw('tags::text ilike ?', [$like]));
    }

    /**
     * Put the link back in the fetch queue (manual retry or re-fetch).
     */
    public function requeue(): void
    {
        $this->update(['status' => ArticleStatus::Queued, 'error' => null]);

        FetchArticle::dispatch($this)->afterCommit();
    }

    public function path(): string
    {
        $path = parse_url($this->url, PHP_URL_PATH);

        return $path && $path !== '/' ? $path : '';
    }

    public function readMinutes(): ?int
    {
        return $this->word_count ? max(1, (int) ceil($this->word_count / 230)) : null;
    }
}
