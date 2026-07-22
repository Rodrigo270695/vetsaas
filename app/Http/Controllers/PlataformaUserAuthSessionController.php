<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserAuthSessionLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial central de inicios/cierres de sesión de usuarios de clínica.
 */
class PlataformaUserAuthSessionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const SORTABLE_COLUMNS = [
        'logged_in_at',
        'logged_out_at',
        'tenant_slug',
        'user_name',
        'plan_codigo',
    ];

    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');
        $planGrupo = (string) $request->input('plan_grupo', 'free');
        $estado = (string) $request->input('estado', 'todos');
        $sort = (string) $request->input('sort', 'logged_in_at');
        $direction = (string) $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        if (! in_array($planGrupo, ['free', 'paid', 'todos'], true)) {
            $planGrupo = 'free';
        }

        if (! in_array($estado, ['todos', 'abiertas', 'cerradas'], true)) {
            $estado = 'todos';
        }

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'logged_in_at';
        }

        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $query = UserAuthSessionLog::query()
            ->clinicUsers()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social',
            ]);

        if ($planGrupo === 'free') {
            $query->freePlan();
        } elseif ($planGrupo === 'paid') {
            $query->paidPlan();
        }

        if ($estado === 'abiertas') {
            $query->open();
        } elseif ($estado === 'cerradas') {
            $query->closed();
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('user_name', 'ilike', $like)
                    ->orWhere('user_email', 'ilike', $like)
                    ->orWhere('tenant_slug', 'ilike', $like)
                    ->orWhereHas('tenant', function ($tenantQuery) use ($like): void {
                        $tenantQuery
                            ->where('slug', 'ilike', $like)
                            ->orWhere('nombre_comercial', 'ilike', $like)
                            ->orWhere('razon_social', 'ilike', $like);
                    });
            });
        }

        $query->orderBy($sort, $direction);

        $logs = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (UserAuthSessionLog $log): array {
                $tenant = $log->tenant;
                $label = $tenant !== null
                    ? (trim((string) ($tenant->nombre_comercial ?: '')) ?: (string) $tenant->razon_social)
                    : ($log->tenant_slug ?? '—');

                return [
                    'id' => (string) $log->getKey(),
                    'user_name' => $log->user_name,
                    'user_email' => $log->user_email,
                    'tenant_slug' => $log->tenant_slug ?? '—',
                    'tenant_label' => $label !== '' ? $label : '—',
                    'plan_codigo' => $log->plan_codigo,
                    'is_free' => $log->isFreePlan(),
                    'ip_address' => $log->ip_address,
                    'logged_in_at' => $log->logged_in_at?->toIso8601String(),
                    'logged_out_at' => $log->logged_out_at?->toIso8601String(),
                    'logout_reason' => $log->logout_reason,
                    'is_open' => $log->isOpen(),
                ];
            });

        $baseClinic = UserAuthSessionLog::query()->clinicUsers();

        $stats = [
            'total' => (clone $baseClinic)->count(),
            'abiertas' => (clone $baseClinic)->open()->count(),
            'hoy' => (clone $baseClinic)->whereDate('logged_in_at', today())->count(),
            'clinicas' => (clone $baseClinic)->distinct('tenant_id')->count('tenant_id'),
            'free' => (clone $baseClinic)->freePlan()->count(),
            'paid' => (clone $baseClinic)->paidPlan()->count(),
            'coincidencias' => $logs->total(),
        ];

        return Inertia::render('plataforma/sesiones-login/index', [
            'logs' => $logs,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filters' => [
                'search' => $search,
                'plan_grupo' => $planGrupo,
                'estado' => $estado,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
            'plan_free_codigo' => Plan::CODIGO_FREE,
        ]);
    }
}
