<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->placeholder('Not fetched yet')->columnSpanFull(),
                        TextEntry::make('url')
                            ->label('Link')
                            ->url(fn (Article $record) => $record->url, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (ArticleStatus $state) => $state->label())
                            ->color(fn (ArticleStatus $state) => ArticleStatusColor::for($state)),
                        TextEntry::make('error')
                            ->color('danger')
                            ->visible(fn (Article $record) => filled($record->error)),
                        TextEntry::make('byline')->placeholder('—'),
                        TextEntry::make('word_count')->numeric()->placeholder('—'),
                        TextEntry::make('tags')->badge()->placeholder('—'),
                        TextEntry::make('created_at')->label('Saved')->since(),
                        TextEntry::make('fetched_at')->since()->placeholder('Never'),
                        TextEntry::make('read_at')->label('Read')->since()->placeholder('Unread'),
                    ]),
                Section::make('History')
                    ->description('Changes made by the fetch queue show as System; other edits show who made them.')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (Article $record) => $record->activitiesAsSubject()->with('causer')->latest('id')->limit(20)->get())
                            ->placeholder('No recorded changes.')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('event')->badge()->hiddenLabel(),
                                TextEntry::make('summary')
                                    ->hiddenLabel()
                                    ->state(fn (Activity $record) => self::summarize($record)),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->since()
                                    ->helperText(fn (Activity $record) => $record->causer?->name ?? 'System'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * "status: fetching → ready, error: — → HTTP 404"
     */
    private static function summarize(Activity $activity): string
    {
        $new = $activity->attribute_changes?->get('attributes', []) ?? [];
        $old = $activity->attribute_changes?->get('old', []) ?? [];

        return collect($new)
            ->map(fn ($value, $key) => $activity->event === 'created'
                ? "{$key}: ".self::show($value)
                : "{$key}: ".self::show($old[$key] ?? null).' → '.self::show($value))
            ->implode(', ') ?: '—';
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' || $value === [] => '—',
            is_array($value) => implode(', ', $value),
            default => str((string) $value)->limit(60)->toString(),
        };
    }
}
