<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ImpersonationAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial central de sesiones «Entrar como soporte» (impersonación).
 */
class PlataformaImpersonationAuditController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $logs = ImpersonationAuditLog::query()
            ->with([
                'superadmin:id,name,email',
                'tenant:id,slug,nombre_comercial,razon_social',
            ])
            ->orderByDesc('started_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (ImpersonationAuditLog $log): array {
                $tenant = $log->tenant;
                $label = $tenant !== null
                    ? (trim((string) ($tenant->nombre_comercial ?: '')) ?: $tenant->razon_social)
                    : '—';

                return [
                    'id' => (string) $log->getKey(),
                    'superadmin_name' => $log->superadmin?->name ?? '—',
                    'superadmin_email' => $log->superadmin?->email ?? '—',
                    'tenant_slug' => $tenant?->slug ?? '—',
                    'tenant_label' => $label,
                    'ip_address' => $log->ip_address,
                    'central_origin' => $log->central_origin,
                    'started_at' => $log->started_at?->toIso8601String(),
                    'ended_at' => $log->ended_at?->toIso8601String(),
                    'is_active' => $log->ended_at === null,
                ];
            });

        return Inertia::render('plataforma/auditoria-soporte/index', [
            'logs' => $logs,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }
}
