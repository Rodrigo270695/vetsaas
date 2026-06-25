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

    private const SORTABLE_COLUMNS = [
        'started_at',
        'ended_at',
        'tenant_slug',
    ];

    public function index(Request $request): Response
    {
        $search    = $request->input('search', '');
        $estado    = $request->input('estado', 'todos');
        $sort      = $request->input('sort', 'started_at');
        $direction = $request->input('direction', 'desc');
        $perPage   = (int) $request->input('per_page', 15);

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'started_at';
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $query = ImpersonationAuditLog::query()
            ->with([
                'superadmin:id,name,email',
                'tenant:id,slug,nombre_comercial,razon_social',
            ]);

        if ($search !== '') {
            $query->whereHas('tenant', function ($q) use ($search): void {
                $q->where('slug', 'ilike', "%{$search}%")
                  ->orWhere('nombre_comercial', 'ilike', "%{$search}%")
                  ->orWhere('razon_social', 'ilike', "%{$search}%");
            });
        }

        if ($estado === 'activas') {
            $query->whereNull('ended_at');
        } elseif ($estado === 'finalizadas') {
            $query->whereNotNull('ended_at');
        }

        $query->orderBy($sort, $direction);

        $logs = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (ImpersonationAuditLog $log): array {
                $tenant = $log->tenant;
                $label  = $tenant !== null
                    ? (trim((string) ($tenant->nombre_comercial ?: '')) ?: $tenant->razon_social)
                    : '—';

                return [
                    'id'               => (string) $log->getKey(),
                    'superadmin_name'  => $log->superadmin?->name ?? '—',
                    'superadmin_email' => $log->superadmin?->email ?? '—',
                    'tenant_slug'      => $tenant?->slug ?? '—',
                    'tenant_label'     => $label,
                    'ip_address'       => $log->ip_address,
                    'central_origin'   => $log->central_origin,
                    'started_at'       => $log->started_at?->toIso8601String(),
                    'ended_at'         => $log->ended_at?->toIso8601String(),
                    'is_active'        => $log->ended_at === null,
                ];
            });

        $stats = [
            'total'        => ImpersonationAuditLog::query()->count(),
            'activas'      => ImpersonationAuditLog::query()->whereNull('ended_at')->count(),
            'hoy'          => ImpersonationAuditLog::query()->whereDate('started_at', today())->count(),
            'clinicas'     => ImpersonationAuditLog::query()->distinct('tenant_id')->count('tenant_id'),
            'coincidencias' => $logs->total(),
        ];

        return Inertia::render('plataforma/auditoria-soporte/index', [
            'logs'           => $logs,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filters'        => [
                'search'    => $search,
                'estado'    => $estado,
                'sort'      => $sort,
                'direction' => $direction,
                'per_page'  => $perPage,
            ],
            'stats' => $stats,
        ]);
    }
}
