<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Actions\RequeueAction;
use App\Filament\Resources\Articles\Schemas\ArticleStatusColor;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('highlights'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->placeholder('Not fetched yet')
                    ->description(fn (Article $record) => $record->domain)
                    ->limit(70)
                    ->tooltip(fn (Article $record) => $record->url)
                    ->searchable(['title', 'url', 'domain']),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state) => $state->label())
                    ->color(fn (ArticleStatus $state) => ArticleStatusColor::for($state))
                    ->description(fn (Article $record) => $record->status === ArticleStatus::Failed ? str($record->error)->limit(40) : null),
                TextColumn::make('tags')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('highlights_count')
                    ->label('Highlights')
                    ->sortable(),
                TextColumn::make('word_count')
                    ->label('Words')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Saved')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ArticleStatus::cases())->mapWithKeys(fn (ArticleStatus $s) => [$s->value => $s->label()])),
                TernaryFilter::make('read_at')
                    ->label('Read')
                    ->nullable(),
                TernaryFilter::make('archived_at')
                    ->label('Archived')
                    ->nullable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('reader')
                        ->label('Open in reader')
                        ->icon(Heroicon::OutlinedBookOpen)
                        ->url(fn (Article $record) => route('articles.show', $record))
                        ->visible(fn (Article $record) => $record->status === ArticleStatus::Ready),
                    RequeueAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RequeueAction::bulk(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
