<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Services\Audit\AuditLogger;

trait LogsAuditExports
{
    protected function auditExport(string $modulo, ?string $descripcion = null): void
    {
        AuditLogger::logExport($modulo, $descripcion);
    }

    protected function auditDownload(
        string $modulo,
        ?string $registroId = null,
        ?string $registroLabel = null,
        ?string $archivo = null,
    ): void {
        AuditLogger::logDownload($modulo, $registroId, $registroLabel, $archivo);
    }
}
