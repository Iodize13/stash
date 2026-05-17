<?php

namespace App\Services\Fetching;

use RuntimeException;

class FetchException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function retryable(string $message): self
    {
        return new self($message, retryable: true);
    }
}
