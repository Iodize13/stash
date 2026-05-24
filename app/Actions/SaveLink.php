<?php

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Support\UrlNormalizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves a link to a user's library as a queued article. Shared by the library
 * page and the admin panel so both normalize and de-duplicate the same way.
 */
class SaveLink
{
    /**
     * @param  string  $field  Validation error key, so each caller can map errors to its own form field.
     * @param  string|null  $title  Provisional title (e.g. the page title from the bookmarklet), replaced once fetched.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $url, string $tags = '', string $field = 'url', ?string $title = null): Article
    {
        // Validate under a plain key: a field like "data.url" would be read as a nested path.
        $validator = validator(['url' => $url], ['url' => ['required', 'url:http,https', 'max:2048']]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([$field => $validator->errors()->get('url')]);
        }

        try {
            $normalized = UrlNormalizer::normalize($url);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => 'Enter a valid http(s) link.']);
        }

        $hash = UrlNormalizer::hash($normalized);

        if ($user->articles()->where('url_hash', $hash)->exists()) {
            throw ValidationException::withMessages([$field => 'This link is already in your library.']);
        }

        return $user->articles()->create([
            'url' => $normalized,
            'url_hash' => $hash,
            'domain' => UrlNormalizer::domain($normalized),
            'title' => filled($title) ? Str::limit(trim($title), 250) : null,
            'tags' => UrlNormalizer::tags($tags),
            'status' => ArticleStatus::Queued,
        ]);
    }
}
