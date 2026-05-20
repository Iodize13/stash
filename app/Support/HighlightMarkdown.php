<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Collection;
use App\Models\Highlight;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Markdown with YAML frontmatter, so exports drop straight into Obsidian or
 * any notes folder.
 */
class HighlightMarkdown
{
    public static function article(Article $article): string
    {
        $highlights = $article->highlights()->oldest()->oldest('id')->get();

        return self::frontmatter([
            'title' => $article->title ?? $article->url,
            'source' => $article->url,
            'author' => $article->byline,
            'saved' => $article->created_at->toDateString(),
            'tags' => $article->tags,
        ])
            .'# '.($article->title ?? $article->url)."\n\n"
            .self::highlights($highlights);
    }

    public static function collection(Collection $collection): string
    {
        $sections = $collection->articles->map(function (Article $article) {
            $lines = ['## '.($article->title ?? $article->url), '', "Source: <{$article->url}>"];

            if ($article->pivot->note) {
                $lines = [...$lines, '', $article->pivot->note];
            }

            return implode("\n", $lines)."\n\n".self::highlights($article->highlights);
        });

        return self::frontmatter([
            'title' => $collection->title,
            'curator' => $collection->user->name,
            'updated' => $collection->updated_at->toDateString(),
            'sources' => $collection->articles->count(),
        ])
            .'# '.$collection->title."\n\n"
            .($collection->description ? $collection->description."\n\n" : '')
            .$sections->implode("\n");
    }

    public static function download(string $markdown, string $name): Response
    {
        $filename = Str::slug($name) ?: 'highlights';

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
        ]);
    }

    /**
     * @param  iterable<Highlight>  $highlights
     */
    private static function highlights(iterable $highlights): string
    {
        $blocks = collect($highlights)->map(function (Highlight $highlight) {
            $lines = ['> '.str_replace("\n", "\n> ", trim($highlight->exact))];

            if ($highlight->note) {
                $lines = [...$lines, '', $highlight->note];
            }

            if ($highlight->tags) {
                $lines = [...$lines, '', collect($highlight->tags)->map(fn ($tag) => "#{$tag}")->implode(' ')];
            }

            return implode("\n", $lines);
        });

        return $blocks->isEmpty() ? "_No highlights yet._\n" : $blocks->implode("\n\n---\n\n")."\n";
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function frontmatter(array $fields): string
    {
        $lines = collect($fields)
            ->filter(fn ($value) => $value !== null && $value !== [] && $value !== '')
            ->map(fn ($value, $key) => $key.': '.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return "---\n".$lines->implode("\n")."\n---\n\n";
    }
}
