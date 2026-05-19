<?php

namespace Database\Factories;

use App\Enums\HighlightColor;
use App\Models\Article;
use App\Models\Highlight;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Highlight> */
class HighlightFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'exact' => fake()->sentence(10),
            'color' => fake()->randomElement(HighlightColor::cases()),
            'note' => fake()->optional(0.6)->sentence(12),
            'tags' => fake()->randomElements(['key-idea', 'quote', 'todo', 'question'], fake()->numberBetween(0, 2)),
        ];
    }

    /**
     * Quote a real sentence from one of the article's paragraphs so it can be
     * anchored in the reader.
     */
    public function quoting(Article $article, int $sentence = 0): static
    {
        $text = (string) $article->content_text;
        preg_match_all('#<p>(.*?)</p>#s', (string) $article->content_html, $paragraphs);
        $sentences = collect($paragraphs[1])
            ->flatMap(fn (string $p) => preg_split('/(?<=[.!?])\s+/u', strip_tags($p), -1, PREG_SPLIT_NO_EMPTY))
            ->values();
        $exact = $sentences[$sentence % max(1, $sentences->count())] ?? $text;
        $offset = (int) strpos($text, $exact);

        return $this->for($article)->state([
            'exact' => $exact,
            'prefix' => mb_substr(substr($text, 0, $offset), -Highlight::CONTEXT_LENGTH),
            'suffix' => mb_substr(substr($text, $offset + strlen($exact)), 0, Highlight::CONTEXT_LENGTH),
        ]);
    }
}
