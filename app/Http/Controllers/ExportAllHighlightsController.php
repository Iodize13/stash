<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Support\HighlightMarkdown;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExportAllHighlightsController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Highlight::class);

        return HighlightMarkdown::download(HighlightMarkdown::all(request()->user()), 'highlights-'.now()->toDateString());
    }
}
