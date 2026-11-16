<?php

namespace App\Exceptions;

use RuntimeException;

final class GeminiRequestException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $body,
    ) {
        parent::__construct("Gemini request failed: {$status} {$body}");
    }

    public function isTransient(): bool
    {
        return in_array($this->status, [408, 425, 429, 500, 502, 503, 504], true);
    }
}
