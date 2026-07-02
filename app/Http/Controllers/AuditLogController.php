<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\AuditLogsXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Models\AuditLog;
use App\Support\Audit\AuditActor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro de actividad del tenant (creación, edición, eliminación, exportaciones).
 */
class AuditLogController extends Controller
{
    use LogsAuditExports;

    private const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'created_at',
        'accion',
        'modulo',
        'usuario_nombre',
    ];

    private const ACCION_OPTIONS = [
        AuditLog::ACCION_CREATED,
        AuditLog::ACCION_UPDATED,
        AuditLog::ACCION_DELETED,
        AuditLog::ACCION_EXPORTED,
        AuditLog::ACCION_DOWNLOADED,
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $accion = (string) $request->string('accion', 'todos');
        $modulo = (string) $request->string('modulo', 'todos');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $sort = (string) $request->string('sort', 'created_at');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $perPage = (int) $request->input('per_page', 15);

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'created_at';
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $query = AuditLog::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('usuario_nombre', 'ilike', "%{$search}%")
                    ->orWhere('usuario_email', 'ilike', "%{$search}%")
                    ->orWhere('registro_label', 'ilike', "%{$search}%")
                    ->orWhere('registro_id', 'ilike', "%{$search}%")
                    ->orWhere('ip_address', 'ilike', "%{$search}%");
            });
        }

        if ($accion !== 'todos' && in_array($accion, self::ACCION_OPTIONS, true)) {
            $query->where('accion', $accion);
        }

        if ($modulo !== 'todos') {
            $query->where('modulo', $modulo);
        }

        if (is_string($desde) && $desde !== '') {
            $query->whereDate('created_at', '>=', $desde);
        }

        if (is_string($hasta) && $hasta !== '') {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $query->orderBy($sort, $direction);

        $logs = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (AuditLog $log): array {
                return [
                    'id' => (string) $log->getKey(),
                    'usuario_nombre' => $log->usuario_nombre ?? '—',
                    'usuario_email' => $log->usuario_email,
                    'usuario_es_bot_ia' => AuditActor::isBotIa($log->usuario_nombre),
                    'accion' => $log->accion,
                    'modulo' => $log->modulo,
                    'registro_label' => $log->registro_label,
                    'registro_id' => $log->registro_id,
                    'cambios' => $log->cambios,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            });

        $moduloOptions = collect(config('audit.observed_models', []))
            ->map(static fn (array $cfg): string => (string) $cfg['modulo'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $stats = [
            'total' => AuditLog::query()->count(),
            'hoy' => AuditLog::query()->whereDate('created_at', today())->count(),
            'creaciones' => AuditLog::query()->where('accion', AuditLog::ACCION_CREATED)->count(),
            'ediciones' => AuditLog::query()->where('accion', AuditLog::ACCION_UPDATED)->count(),
            'eliminaciones' => AuditLog::query()->where('accion', AuditLog::ACCION_DELETED)->count(),
            'exportaciones' => AuditLog::query()
                ->whereIn('accion', [AuditLog::ACCION_EXPORTED, AuditLog::ACCION_DOWNLOADED])
                ->count(),
            'coincidencias' => $logs->total(),
        ];

        return Inertia::render('auditoria/logs/index', [
            'logs' => $logs,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'accionOptions' => self::ACCION_OPTIONS,
            'moduloOptions' => $moduloOptions,
            'filters' => [
                'search' => $search,
                'accion' => $accion,
                'modulo' => $modulo,
                'desde' => is_string($desde) ? $desde : '',
                'hasta' => is_string($hasta) ? $hasta : '',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
            'canExport' => $request->user()?->can('auditoria-logs.export') ?? false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('auditoria-logs.export') ?? false, 403);

        $search = trim((string) $request->string('search', ''));
        $accion = (string) $request->string('accion', 'todos');
        $modulo = (string) $request->string('modulo', 'todos');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $query = AuditLog::query()->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('usuario_nombre', 'ilike', "%{$search}%")
                    ->orWhere('usuario_email', 'ilike', "%{$search}%")
                    ->orWhere('registro_label', 'ilike', "%{$search}%")
                    ->orWhere('registro_id', 'ilike', "%{$search}%");
            });
        }

        if ($accion !== 'todos' && in_array($accion, self::ACCION_OPTIONS, true)) {
            $query->where('accion', $accion);
        }

        if ($modulo !== 'todos') {
            $query->where('modulo', $modulo);
        }

        if (is_string($desde) && $desde !== '') {
            $query->whereDate('created_at', '>=', $desde);
        }

        if (is_string($hasta) && $hasta !== '') {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $this->auditExport('auditoria_logs', 'Exportación con filtros actuales');

        $filename = 'registro-actividad-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new AuditLogsXlsxExport;

        return response()->streamDownload(
            static function () use ($exporter, $query): void {
                $exporter->streamTo($query);
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
