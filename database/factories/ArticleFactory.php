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

        return [
            'user_id' => User::factory(),
            'url' => $url,
            'url_hash' => UrlNormalizer::hash(UrlNormalizer::normalize($url)),
            'domain' => UrlNormalizer::domain($url),
            'title' => rtrim(fake()->sentence(7), '.'),
            'excerpt' => fake()->paragraph(2),
            'tags' => fake()->randomElements(['systems', 'databases', 'networking', 'llm', 'math', 'c', 'rust'], 2),
            'status' => ArticleStatus::Ready,
            'word_count' => fake()->numberBetween(800, 8000),
            'fetched_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => ArticleStatus::Queued, 'title' => null, 'excerpt' => null, 'word_count' => null, 'fetched_at' => null]);
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
