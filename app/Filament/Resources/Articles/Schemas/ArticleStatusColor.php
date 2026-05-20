<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;

class ArticleStatusColor
{
    public static function for(ArticleStatus $status): string
    {
        return match ($status) {
            ArticleStatus::Ready => 'success',
            ArticleStatus::Failed => 'danger',
            default => 'info',
        };
    }
}
