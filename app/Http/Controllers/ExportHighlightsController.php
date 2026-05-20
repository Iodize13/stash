<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\HighlightMarkdown;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExportHighlightsController extends Controller
{
    public function __invoke(Article $article): Response
    {
        Gate::authorize('view', $article);

        return HighlightMarkdown::download(HighlightMarkdown::article($article), $article->title ?? $article->domain);
    }
}
