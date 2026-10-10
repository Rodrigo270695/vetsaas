<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use RuntimeException;

final class OpenWaRateLimitedException extends RuntimeException
{
    public static function matches(string $message): bool
    {
        return str_contains($message, 'HTTP 429')
            || str_contains($message, 'ThrottlerException')
            || str_contains($message, 'Too Many Requests');
    }
}
