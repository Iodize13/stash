<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Article */
class ArticleResource extends JsonResource
{
    /**
     * Never includes the stored article body: the API is for saving and listing links.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'domain' => $this->domain,
            'title' => $this->title,
            'byline' => $this->byline,
            'excerpt' => $this->excerpt,
            'tags' => $this->tags,
            'status' => $this->status->value,
            'error' => $this->error,
            'word_count' => $this->word_count,
            'highlights_count' => $this->whenCounted('highlights'),
            'saved_at' => $this->created_at?->toIso8601String(),
            'fetched_at' => $this->fetched_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'links' => [
                'reader' => route('articles.show', $this->resource),
            ],
        ];
    }
}
