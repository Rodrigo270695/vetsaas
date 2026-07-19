<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\PlatformSecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Escribe eventos de seguridad en `public.platform_security_audit_logs`.
 * Nunca debe tumbar la request original si el log falla.
 */
final class PlatformSecurityAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(
        string $action,
        string $modulo,
        string $summary,
        ?array $metadata = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectLabel = null,
        ?Request $request = null,
    ): void {
        try {
            /** @var User|null $user */
            $user = Auth::user();
            $req = $request ?? request();
            $ctx = current_tenant();

            $tenantLabel = null;
            if ($ctx !== null) {
                $comercial = trim((string) ($ctx->nombreComercial() ?? ''));
                $tenantLabel = $comercial !== ''
                    ? $comercial
                    : (trim($ctx->razonSocial()) !== '' ? $ctx->razonSocial() : $ctx->slug);
            }

            PlatformSecurityAuditLog::query()->create([
                'actor_id' => $user?->getKey(),
                'actor_name' => $user?->name,
                'actor_email' => $user?->email,
                'tenant_id' => tenant_id(),
                'tenant_slug' => $ctx?->slug,
                'tenant_label' => $tenantLabel,
                'action' => $action,
                'modulo' => $modulo,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId !== null ? (string) $subjectId : null,
                'subject_label' => $subjectLabel !== null && $subjectLabel !== ''
                    ? mb_substr($subjectLabel, 0, 255)
                    : null,
                'summary' => mb_substr($summary, 0, 500),
                'metadata' => $metadata,
                'ip_address' => $req?->ip(),
                'user_agent' => $req?->userAgent() !== null
                    ? mb_substr((string) $req->userAgent(), 0, 500)
                    : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
