<?php

namespace App\Livewire;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Support\UrlNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Library')]
class Library extends Component
{
    use WithPagination;

    private const PER_PAGE = 10;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $tab = 'all';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public string $url = '';

    public string $tags = '';

    public function ingest(): void
    {
        $this->authorize('create', Article::class);

        $this->validate(['url' => ['required', 'url:http,https', 'max:2048']]);

        try {
            $normalized = UrlNormalizer::normalize($this->url);
        } catch (InvalidArgumentException) {
            $this->addError('url', 'Enter a valid http(s) link.');

            return;
        }

        $hash = UrlNormalizer::hash($normalized);

        if (Article::where('url_hash', $hash)->exists()) {
            $this->addError('url', 'This link is already in your library.');

            return;
        }

        Article::create([
            'user_id' => auth()->id(),
            'url' => $normalized,
            'url_hash' => $hash,
            'domain' => UrlNormalizer::domain($normalized),
            'tags' => UrlNormalizer::tags($this->tags),
            'status' => ArticleStatus::Queued,
        ]);

        $this->reset('url', 'tags');
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        $article = Article::findOrFail($id);
        $this->authorize('update', $article);

        $article->requeue();
    }

    public function retryFailed(): void
    {
        $this->authorize('update', new Article);

        Article::where('status', ArticleStatus::Failed)->each(fn (Article $article) => $article->requeue());
    }

    public function toggleArchive(int $id): void
    {
        $article = Article::findOrFail($id);
        $this->authorize('update', $article);

        $article->update(['archived_at' => $article->archived_at ? null : now()]);
    }

    public function markVisibleRead(): void
    {
        $this->authorize('update', new Article);

        $this->filteredQuery()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function delete(int $id): void
    {
        $article = Article::findOrFail($id);
        $this->authorize('delete', $article);

        $article->delete();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'tab', 'status', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Article::class);

        $statusCounts = Article::toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.library', [
            'articles' => $this->filteredQuery()->paginate(self::PER_PAGE),
            'pipeline' => Article::whereIn('status', [ArticleStatus::Queued, ArticleStatus::Fetching, ArticleStatus::Failed])
                ->oldest()
                ->limit(6)
                ->get(),
            'tabCounts' => [
                'all' => Article::count(),
                'unread' => Article::unread()->count(),
                'archived' => Article::archived()->count(),
            ],
            'statusCounts' => $statusCounts,
        ]);
    }

    /** @return Builder<Article> */
    private function filteredQuery()
    {
        $query = Article::query();

        match ($this->tab) {
            'unread' => $query->unread(),
            'archived' => $query->archived(),
            default => null,
        };

        if (ArticleStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if (trim($this->search) !== '') {
            $query->search(trim($this->search));
        }

        return match ($this->sort) {
            'oldest' => $query->oldest(),
            'title' => $query->orderByRaw('title asc nulls last')->latest(),
            default => $query->latest(),
        };
    }
}
