<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\ImpersonationAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

final class ImpersonationAuditLogger
{
    public function logStarted(
        User $superadmin,
        Tenant $tenant,
        Request $request,
        ?string $centralOrigin,
    ): ImpersonationAuditLog {
        return ImpersonationAuditLog::query()->create([
            'superadmin_id' => $superadmin->getKey(),
            'tenant_id' => $tenant->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'central_origin' => $centralOrigin !== null && $centralOrigin !== ''
                ? $centralOrigin
                : null,
            'started_at' => now(),
        ]);
    }

    public function logEnded(?string $auditLogId): void
    {
        if ($auditLogId === null || $auditLogId === '') {
            return;
        }

        ImpersonationAuditLog::query()
            ->whereKey($auditLogId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }
}
