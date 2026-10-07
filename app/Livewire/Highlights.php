<?php

namespace App\Livewire;

use App\Enums\HighlightColor;
use App\Models\Article;
use App\Models\Highlight;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Highlights')]
class Highlights extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $color = '';

    /** all | notes | untagged */
    #[Url(except: 'all')]
    public string $show = 'all';

    /** article | time */
    #[Url(except: 'article')]
    public string $group = 'article';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'color', 'show', 'group', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function updateNote(int $id, string $note): void
    {
        $highlight = Highlight::findOrFail($id);
        $this->authorize('update', $highlight);

        validator(['note' => $note], ['note' => ['string', 'max:2000']])->validate();

        $highlight->update(['note' => trim($note) ?: null]);
    }

    public function delete(int $id): void
    {
        $highlight = Highlight::findOrFail($id);
        $this->authorize('delete', $highlight);

        $highlight->delete();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Highlight::class);

        $highlights = $this->filteredQuery()
            ->with('article:id,url,domain,title,byline,created_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.highlights', [
            'highlights' => $highlights,
            // By article: grouped per page (an article can continue on the next page).
            // Chronological: one section holding the whole page.
            'sections' => $this->group === 'time'
                ? collect([0 => $highlights->getCollection()])->filter(fn ($items) => $items->isNotEmpty())
                : $highlights->getCollection()->groupBy('article_id'),
            'perArticle' => Article::whereIn('id', $highlights->pluck('article_id'))
                ->withCount(['highlights', 'highlights as notes_count' => fn (Builder $q) => $q->whereNotNull('note')])
                ->get(['id'])
                ->keyBy('id'),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Highlights on the signed-in user's own articles.
     *
     * @return Builder<Highlight>
     */
    private function mine(): Builder
    {
        return Highlight::query()->whereIn('article_id', auth()->user()->articles()->select('id'));
    }

    /** @return Builder<Highlight> */
    private function filteredQuery(): Builder
    {
        $query = $this->mine();

        if (HighlightColor::tryFrom($this->color)) {
            $query->where('color', $this->color);
        }

        match ($this->show) {
            'notes' => $query->whereNotNull('note'),
            'untagged' => $query->whereJsonLength('tags', 0),
            default => null,
        };

        if (($term = trim($this->search)) !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('exact', 'ilike', $like)
                ->orWhere('note', 'ilike', $like)
                ->orWhereRaw('tags::text ilike ?', [$like])
                ->orWhereHas('article', fn (Builder $a) => $a->where('title', 'ilike', $like)));
        }

        $direction = $this->sort === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }

    /**
     * @return array{total: int, thisWeek: int, withNotes: int, articles: int, tagged: int, colors: array<string, int>}
     */
    private function stats(): array
    {
        $colors = $this->mine()->toBase()->selectRaw('color, count(*) as total')->groupBy('color')->pluck('total', 'color');

        return [
            'total' => (int) $colors->sum(),
            'thisWeek' => $this->mine()->where('created_at', '>=', now()->subWeek())->count(),
            'withNotes' => $this->mine()->whereNotNull('note')->count(),
            'articles' => $this->mine()->distinct()->count('article_id'),
            'tagged' => $this->mine()->whereJsonLength('tags', '>', 0)->count(),
            'colors' => collect(HighlightColor::cases())
                ->mapWithKeys(fn (HighlightColor $c) => [$c->value => (int) ($colors[$c->value] ?? 0)])
                ->all(),
        ];
    }
}
