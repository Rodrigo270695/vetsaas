<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PlatformSecurityAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial central de acciones de seguridad (roles, usuarios).
 */
class PlataformaSecurityAuditController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const SORTABLE_COLUMNS = [
        'created_at',
        'action',
        'modulo',
        'tenant_slug',
        'actor_name',
    ];

    private const MODULO_OPTIONS = ['todos', 'roles', 'usuarios'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $modulo = (string) $request->string('modulo', 'todos');
        $sort = (string) $request->string('sort', 'created_at');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $perPage = (int) $request->integer('per_page', 15);

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'created_at';
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }
        if (! in_array($modulo, self::MODULO_OPTIONS, true)) {
            $modulo = 'todos';
        }

        $query = PlatformSecurityAuditLog::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('actor_name', 'ilike', "%{$search}%")
                    ->orWhere('actor_email', 'ilike', "%{$search}%")
                    ->orWhere('tenant_slug', 'ilike', "%{$search}%")
                    ->orWhere('tenant_label', 'ilike', "%{$search}%")
                    ->orWhere('summary', 'ilike', "%{$search}%")
                    ->orWhere('subject_label', 'ilike', "%{$search}%")
                    ->orWhere('action', 'ilike', "%{$search}%")
                    ->orWhere('ip_address', 'ilike', "%{$search}%");
            });
        }

        if ($modulo !== 'todos') {
            $query->where('modulo', $modulo);
        }

        $query->orderBy($sort, $direction);

        $logs = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (PlatformSecurityAuditLog $log): array {
                return [
                    'id' => (string) $log->getKey(),
                    'created_at' => $log->created_at?->toIso8601String(),
                    'actor_name' => $log->actor_name ?? '—',
                    'actor_email' => $log->actor_email,
                    'tenant_slug' => $log->tenant_slug ?? '—',
                    'tenant_label' => $log->tenant_label ?? ($log->tenant_slug ? '—' : 'Central'),
                    'action' => $log->action,
                    'modulo' => $log->modulo,
                    'subject_label' => $log->subject_label,
                    'summary' => $log->summary,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                ];
            });

        $stats = [
            'total' => PlatformSecurityAuditLog::query()->count(),
            'hoy' => PlatformSecurityAuditLog::query()->whereDate('created_at', today())->count(),
            'roles' => PlatformSecurityAuditLog::query()->where('modulo', 'roles')->count(),
            'usuarios' => PlatformSecurityAuditLog::query()->where('modulo', 'usuarios')->count(),
            'coincidencias' => $logs->total(),
        ];

        return Inertia::render('plataforma/auditoria-seguridad/index', [
            'logs' => $logs,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filters' => [
                'search' => $search,
                'modulo' => $modulo,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ]);
    }
}
