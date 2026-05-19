<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Highlight;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ExportHighlightsController extends Controller
{
    /**
     * Download an article's highlights as Markdown with YAML frontmatter
     * (drops straight into Obsidian or any notes folder).
     */
    public function __invoke(Article $article): Response
    {
        Gate::authorize('view', $article);

        $highlights = $article->highlights()->oldest()->oldest('id')->get();

        $frontmatter = collect([
            'title' => $article->title ?? $article->url,
            'source' => $article->url,
            'author' => $article->byline,
            'saved' => $article->created_at->toDateString(),
            'tags' => $article->tags,
        ])->filter()->map(fn ($value, $key) => $key.': '.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $body = $highlights->map(function (Highlight $highlight) {
            $lines = ['> '.str_replace("\n", "\n> ", trim($highlight->exact))];

            if ($highlight->note) {
                $lines[] = '';
                $lines[] = $highlight->note;
            }

            if ($highlight->tags) {
                $lines[] = '';
                $lines[] = collect($highlight->tags)->map(fn ($tag) => "#{$tag}")->implode(' ');
            }

            return implode("\n", $lines);
        });

        $markdown = "---\n".$frontmatter->implode("\n")."\n---\n\n"
            .'# '.($article->title ?? $article->url)."\n\n"
            .($body->isEmpty() ? "_No highlights yet._\n" : $body->implode("\n\n---\n\n")."\n");

        $filename = Str::slug($article->title ?? $article->domain) ?: 'highlights';

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
        ]);
    }
}
