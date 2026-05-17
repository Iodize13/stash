<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Fetching;
    }
}
