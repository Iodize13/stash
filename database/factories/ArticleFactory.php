<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Support\UrlNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $url = 'https://'.fake()->domainName().'/'.fake()->slug(3);
        [$html, $text] = $this->content();

        return [
            'user_id' => User::factory(),
            'url' => $url,
            'url_hash' => UrlNormalizer::hash(UrlNormalizer::normalize($url)),
            'domain' => UrlNormalizer::domain($url),
            'title' => rtrim(fake()->sentence(7), '.'),
            'excerpt' => fake()->paragraph(2),
            'tags' => fake()->randomElements(['systems', 'databases', 'networking', 'llm', 'math', 'c', 'rust'], 2),
            'status' => ArticleStatus::Ready,
            'byline' => fake()->name(),
            'content_html' => $html,
            'content_text' => $text,
            'word_count' => str_word_count($text),
            'fetched_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state([
            'status' => ArticleStatus::Queued,
            'title' => null,
            'byline' => null,
            'excerpt' => null,
            'content_html' => null,
            'content_text' => null,
            'word_count' => null,
            'fetched_at' => null,
        ]);
    }

    /**
     * Sanitized-looking article markup and its plain text, like ArticleExtractor stores.
     *
     * @return array{string, string}
     */
    private function content(): array
    {
        $html = '';
        $text = [];

        foreach (range(1, 3) as $section) {
            $heading = rtrim(fake()->sentence(4), '.');
            $html .= "<h2>{$section}. {$heading}</h2>";
            $text[] = "{$section}. {$heading}";

            foreach (fake()->paragraphs(3) as $paragraph) {
                $html .= "<p>{$paragraph}</p>";
                $text[] = $paragraph;
            }
        }

        return [$html, implode(' ', $text)];
    }

    public function fetching(): static
    {
        return $this->queued()->state(['status' => ArticleStatus::Fetching]);
    }

    public function failed(string $error = 'HTTP 429: rate limited'): static
    {
        return $this->queued()->state(['status' => ArticleStatus::Failed, 'error' => $error]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
