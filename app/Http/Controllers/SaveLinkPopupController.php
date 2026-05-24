<?php

namespace App\Http\Controllers;

use App\Actions\SaveLink;
use App\Models\Article;
use App\Support\UrlNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * The popup opened by the bookmarklet. Opening it never saves anything: a GET
 * that wrote data would let any site add links to your library just by opening
 * this URL. The page asks for one confirmation (Enter) and saves with a POST.
 */
class SaveLinkPopupController extends Controller
{
    public function show(Request $request): View
    {
        $url = (string) $request->query('url', '');
        $saved = $request->session()->get('saved');

        return view('save', [
            'url' => $url,
            'title' => (string) $request->query('title', ''),
            'canSave' => Gate::allows('create', Article::class),
            'saved' => $saved ? Article::find($saved) : null,
            'existing' => $saved ? null : $this->existing($request, $url),
        ]);
    }

    public function store(Request $request, SaveLink $saveLink): RedirectResponse
    {
        Gate::authorize('create', Article::class);

        $data = $request->validate([
            'url' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $article = $saveLink->handle($request->user(), $data['url'], $data['tags'] ?? '', title: $data['title'] ?? null);

        return redirect()->route('save')->with('saved', $article->id);
    }

    private function existing(Request $request, string $url): ?Article
    {
        try {
            $hash = UrlNormalizer::hash(UrlNormalizer::normalize($url));
        } catch (InvalidArgumentException) {
            return null;
        }

        return $request->user()->articles()->where('url_hash', $hash)->first();
    }
}
