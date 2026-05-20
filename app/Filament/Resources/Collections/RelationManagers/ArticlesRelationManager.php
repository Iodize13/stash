<?php

namespace App\Filament\Resources\Collections\RelationManagers;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    /**
     * Only the curator note (stored on the pivot) is edited here; the article
     * itself is managed in the library.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::noteField(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn ($query) => $query->withCount('highlights'))
            ->reorderable('position')
            ->columns([
                TextColumn::make('title')
                    ->placeholder('Not fetched yet')
                    ->limit(60)
                    ->description(fn (Article $record) => $record->domain)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state) => $state->label())
                    ->color(fn (ArticleStatus $state) => match ($state) {
                        ArticleStatus::Ready => 'success',
                        ArticleStatus::Failed => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('highlights_count')
                    ->label('Highlights'),
                TextColumn::make('note')
                    ->label('Curator note')
                    ->placeholder('—')
                    ->limit(50),
            ])
            ->headerActions([
                AttachAction::make()
                    ->authorize(fn () => $this->canManage())
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title', 'url'])
                    ->recordTitle(fn (Article $record) => $record->title ?? $record->url)
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        self::noteField(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Note')->authorize(fn () => $this->canManage()),
                DetachAction::make()->authorize(fn () => $this->canManage()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->authorize(fn () => $this->canManage()),
                ]),
            ]);
    }

    /**
     * Filament only checks isReadOnly() for attach/detach, never a policy, so
     * membership changes are authorized explicitly against the collection.
     */
    private function canManage(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    private static function noteField(): Textarea
    {
        return Textarea::make('note')
            ->label('Curator note')
            ->helperText('Shown above this article\'s highlights on the public page.')
            ->rows(3)
            ->maxLength(2000);
    }
}
