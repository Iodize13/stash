<?php

namespace App\Livewire;

use App\Actions\SaveLink;
use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    public function ingest(SaveLink $saveLink): void
    {
        $this->authorize('create', Article::class);

        $saveLink->handle(auth()->user(), $this->url, $this->tags);

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
        $this->authorize('create', Article::class);

        $this->mine()->where('status', ArticleStatus::Failed)->each(fn (Article $article) => $article->requeue());
    }

    public function toggleArchive(int $id): void
    {
        $article = Article::findOrFail($id);
        $this->authorize('update', $article);

        $article->update(['archived_at' => $article->archived_at ? null : now()]);
    }

    public function markVisibleRead(): void
    {
        $this->authorize('create', Article::class);

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

        $statusCounts = $this->mine()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.library', [
            'articles' => $this->filteredQuery()->paginate(self::PER_PAGE),
            'pipeline' => $this->mine()->whereIn('status', [ArticleStatus::Queued, ArticleStatus::Fetching, ArticleStatus::Failed])
                ->oldest()
                ->limit(6)
                ->get(),
            'tabCounts' => [
                'all' => $this->mine()->count(),
                'unread' => $this->mine()->unread()->count(),
                'archived' => $this->mine()->archived()->count(),
            ],
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Everyone's library is their own, admin included (sandboxes never mix in).
     *
     * @return Builder<Article>
     */
    private function mine(): Builder
    {
        return Article::query()->whereBelongsTo(auth()->user());
    }

    /** @return Builder<Article> */
    private function filteredQuery()
    {
        $query = $this->mine()->withCount('highlights');

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
