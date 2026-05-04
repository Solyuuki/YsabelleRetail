<?php

namespace App\Services\Auth;

use RuntimeException;
use Throwable;

class SocialAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $context = [],
        private readonly string $reportLevel = 'warning',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function context(): array
    {
        return $this->context;
    }

    public function reportLevel(): string
    {
        return $this->reportLevel;
    }
}
