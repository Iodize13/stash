<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Actions\SaveLink;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * Same path as the library's save-link form: normalize, de-duplicate, queue.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveLink::class)->handle(
            auth()->user(),
            $data['url'],
            implode(' ', $data['tags'] ?? []),
            field: 'data.url',
        );
    }
}
