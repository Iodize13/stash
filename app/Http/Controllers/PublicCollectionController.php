<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\HighlightMarkdown;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * The public face of a collection. Only titles, links, highlights and curator
 * notes are published: the stored article text stays private.
 */
class PublicCollectionController extends Controller
{
    public function show(string $slug): View
    {
        $collection = $this->find($slug);

        $tags = $collection->articles
            ->flatMap(fn ($article) => $article->tags ?? [])
            ->countBy()
            ->sortDesc();

        return view('collections.show', [
            'collection' => $collection,
            'tags' => $tags,
            'highlightCount' => $collection->articles->sum(fn ($article) => $article->highlights->count()),
            'noteCount' => $collection->articles->filter(fn ($article) => filled($article->pivot->note))->count()
                + $collection->articles->sum(fn ($article) => $article->highlights->whereNotNull('note')->count()),
        ]);
    }

    public function feed(string $slug): Response
    {
        $collection = $this->find($slug);

        return response()
            ->view('collections.feed', ['collection' => $collection])
            ->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }

    public function export(string $slug): Response
    {
        $collection = $this->find($slug);

        return HighlightMarkdown::download(HighlightMarkdown::collection($collection), $collection->slug);
    }

    private function find(string $slug): Collection
    {
        return Collection::public()
            ->where('slug', $slug)
            ->with([
                'user:id,name',
                'articles' => fn ($query) => $query->select('articles.id', 'url', 'domain', 'title', 'byline', 'tags', 'word_count', 'articles.created_at'),
                'articles.highlights' => fn ($query) => $query->oldest()->oldest('id'),
            ])
            ->firstOrFail();
    }
}
