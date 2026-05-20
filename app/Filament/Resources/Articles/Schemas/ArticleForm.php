<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Creating only takes a link: everything else comes from the fetch job.
                TextInput::make('url')
                    ->label('Link')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->visibleOn('create')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->maxLength(250)
                    ->hiddenOn('create')
                    ->columnSpanFull(),
                TagsInput::make('tags')
                    ->placeholder('Add a tag')
                    ->splitKeys(['Tab', ' ', ','])
                    ->columnSpanFull(),
            ]);
    }
}
