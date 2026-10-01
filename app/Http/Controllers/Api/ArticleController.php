<?php

namespace App\Http\Controllers\Api;

use App\Actions\SaveLink;
use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Token-authenticated API for clients outside the browser (CLI, shortcuts,
 * extensions). Every query is scoped to the token owner's own articles.
 */
class ArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ArticleStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $articles = $request->user()->articles()
            ->withCount('highlights')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return ArticleResource::collection($articles);
    }

    public function store(Request $request, SaveLink $saveLink): JsonResponse
    {
        Gate::authorize('create', Article::class);

        $data = $request->validate([
            'url' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $article = $saveLink->handle(
            $request->user(),
            $data['url'],
            implode(' ', $data['tags'] ?? []),
            title: $data['title'] ?? null,
        );

        return ArticleResource::make($article->refresh())
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.articles.show', $article));
    }

    public function show(Request $request, int $article): ArticleResource
    {
        return ArticleResource::make(
            $request->user()->articles()->withCount('highlights')->findOrFail($article)
        );
    }
}
