<?php

namespace App\Services\Fetching;

final readonly class FetchedPage
{
    public function __construct(
        public string $url,
        public string $html,
    ) {}
}
