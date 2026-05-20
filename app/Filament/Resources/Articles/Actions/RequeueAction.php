<?php

namespace App\Filament\Resources\Articles\Actions;

use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Put articles back in the fetch queue. Custom Filament actions are allowed by
 * default, so each one is authorized explicitly against the article policy.
 */
class RequeueAction
{
    public static function make(): Action
    {
        return Action::make('requeue')
            ->label('Fetch again')
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize(fn (Article $record) => Gate::allows('update', $record))
            ->requiresConfirmation()
            ->action(fn (Article $record) => $record->requeue())
            ->successNotificationTitle('Queued for fetching');
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('requeue')
            ->label('Fetch again')
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize(fn () => Gate::allows('create', Article::class))
            ->requiresConfirmation()
            ->action(fn (Collection $records) => $records
                ->filter(fn (Article $article) => Gate::allows('update', $article))
                ->each->requeue())
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle('Queued for fetching');
    }
}
