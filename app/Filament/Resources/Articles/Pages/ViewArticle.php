<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\Actions\RequeueAction;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reader')
                ->label('Open in reader')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->url(fn (Article $record) => route('articles.show', $record))
                ->visible(fn (Article $record) => $record->content_html !== null),
            RequeueAction::make(),
            EditAction::make(),
        ];
    }
}
