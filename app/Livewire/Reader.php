<?php

namespace App\Livewire;

use App\Enums\ArticleStatus;
use App\Enums\HighlightColor;
use App\Models\Article;
use App\Models\Highlight;
use App\Support\UrlNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Reader extends Component
{
    public Article $article;

    public function mount(Article $article): void
    {
        $this->authorize('view', $article);
    }

    /**
     * Save a selection made in the reader as a text-quote highlight.
     */
    public function addHighlight(string $exact, string $prefix, string $suffix, string $color, string $note = '', string $tags = ''): void
    {
        $this->authorize('update', $this->article);

        $data = validator(compact('exact', 'prefix', 'suffix', 'color', 'note'), [
            'exact' => ['required', 'string', 'max:'.Highlight::MAX_LENGTH],
            'prefix' => ['string', 'max:'.Highlight::CONTEXT_LENGTH * 2],
            'suffix' => ['string', 'max:'.Highlight::CONTEXT_LENGTH * 2],
            'color' => ['required', Rule::enum(HighlightColor::class)],
            'note' => ['string', 'max:2000'],
        ])->validate();

        // Only text that actually appears in the article can be highlighted. Whitespace is
        // ignored: a selection across two blocks has none at the boundary in the DOM,
        // while the stored plain text separates blocks with a space.
        if (! str_contains(self::withoutWhitespace((string) $this->article->content_text), self::withoutWhitespace($data['exact']))) {
            $this->addError('highlight', 'That selection is not part of the article text.');

            return;
        }

        $this->article->highlights()->create([
            'exact' => $data['exact'],
            'prefix' => mb_substr($prefix, -Highlight::CONTEXT_LENGTH),
            'suffix' => mb_substr($suffix, 0, Highlight::CONTEXT_LENGTH),
            'color' => $data['color'],
            'note' => trim($note) ?: null,
            'tags' => UrlNormalizer::tags($tags),
        ]);

        $this->dispatchAnchors();
    }

    public function updateNote(int $id, string $note): void
    {
        $this->authorize('update', $this->article);

        validator(['note' => $note], ['note' => ['string', 'max:2000']])->validate();

        $this->highlight($id)->update(['note' => trim($note) ?: null]);
    }

    public function deleteHighlight(int $id): void
    {
        $this->authorize('update', $this->article);

        $this->highlight($id)->delete();

        $this->dispatchAnchors();
    }

    public function toggleRead(): void
    {
        $this->authorize('update', $this->article);

        $this->article->update(['read_at' => $this->article->read_at ? null : now()]);
    }

    public function toggleArchive(): void
    {
        $this->authorize('update', $this->article);

        $this->article->update(['archived_at' => $this->article->archived_at ? null : now()]);
    }

    public function retry(): void
    {
        $this->authorize('update', $this->article);

        $this->article->requeue();
    }

    public function render(): View
    {
        $highlights = $this->highlights();

        return view('livewire.reader', [
            'highlights' => $highlights,
            'anchors' => $highlights->map->anchor()->values(),
            'next' => Article::unread()
                ->whereBelongsTo(auth()->user())
                ->where('status', ArticleStatus::Ready)
                ->whereKeyNot($this->article->getKey())
                ->oldest()
                ->first(),
        ])->title($this->article->title ?? $this->article->domain);
    }

    /** @return Collection<int, Highlight> */
    private function highlights(): Collection
    {
        return $this->article->highlights()->latest()->latest('id')->get();
    }

    private function highlight(int $id): Highlight
    {
        return $this->article->highlights()->findOrFail($id);
    }

    private static function withoutWhitespace(string $text): string
    {
        return preg_replace('/\s+/u', '', $text);
    }

    private function dispatchAnchors(): void
    {
        $this->dispatch('highlights-changed', anchors: $this->highlights()->map->anchor()->values());
    }
}
