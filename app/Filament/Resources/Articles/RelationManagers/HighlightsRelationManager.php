<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Enums\HighlightColor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Highlights are created in the reader; here they can be reviewed, edited or removed.
 */
class HighlightsRelationManager extends RelationManager
{
    protected static string $relationship = 'highlights';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('color')
                    ->options(collect(HighlightColor::cases())->mapWithKeys(fn (HighlightColor $c) => [$c->value => ucfirst($c->value)]))
                    ->required(),
                TagsInput::make('tags'),
                Textarea::make('note')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('exact')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('exact')
                    ->label('Quote')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('color')
                    ->badge()
                    ->formatStateUsing(fn (HighlightColor $state) => ucfirst($state->value))
                    ->color(fn (HighlightColor $state) => match ($state) {
                        HighlightColor::Cyan => 'info',
                        HighlightColor::Pink => 'danger',
                        HighlightColor::Green => 'success',
                        HighlightColor::Amber => 'warning',
                    }),
                TextColumn::make('note')
                    ->placeholder('—')
                    ->limit(50),
                TextColumn::make('tags')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
