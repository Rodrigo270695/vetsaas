<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

final class AuditModelObserver
{
    public function created(Model $model): void
    {
        AuditLogger::logModelEvent($model, AuditLog::ACCION_CREATED);
    }

    public function updated(Model $model): void
    {
        AuditLogger::logModelEvent($model, AuditLog::ACCION_UPDATED);
    }

    public function deleted(Model $model): void
    {
        AuditLogger::logModelEvent($model, AuditLog::ACCION_DELETED);
    }
}
