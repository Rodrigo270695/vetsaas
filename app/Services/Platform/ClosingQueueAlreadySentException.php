<?php

declare(strict_types=1);

namespace App\Services\Platform;

use RuntimeException;

final class ClosingQueueAlreadySentException extends RuntimeException
{
    public function __construct(public readonly string $sentAtIso)
    {
        parent::__construct('Ya le enviaste este guion. Confirmá para reenviar.');
    }
}
