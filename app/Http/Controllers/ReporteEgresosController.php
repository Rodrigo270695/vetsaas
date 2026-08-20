<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ReporteEgresosXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Models\User;
use App\Services\Reportes\ReporteEgresosService;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteEgresosController extends Controller
{
    use LogsAuditExports;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ReporteEgresosService $reportes,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        $this->assertAccess($user);

        $payload = $this->reportes->egresos(
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
            $this->stringOrNull($request->query('sede_id')),
            $this->stringOrNull($request->query('motivo')),
        );

        return Inertia::render('reportes/egresos/index', [
            'moneda' => $payload['moneda'],
            'filtros' => $payload['filtros'],
            'totales' => $payload['totales'],
            'por_motivo' => $payload['por_motivo'],
            'items' => $payload['items'],
            'sedes' => $payload['sedes'],
            'motivos' => $payload['motivos'],
            'can_export' => $user->can('reporte-financiero.export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        $this->assertAccess($user);
        abort_unless($user->can('reporte-financiero.export'), 403);

        $payload = $this->reportes->egresos(
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
            $this->stringOrNull($request->query('sede_id')),
            $this->stringOrNull($request->query('motivo')),
        );

        $items = $payload['items'];
        $search = $this->stringOrNull($request->query('search'));
        if ($search !== null && $search !== '') {
            $q = mb_strtolower($search);
            $items = array_values(array_filter(
                $items,
                static function (array $item) use ($q): bool {
                    $haystack = mb_strtolower(trim(
                        ($item['sede_nombre'] ?? '').' '
                        .($item['motivo_label'] ?? '').' '
                        .($item['notas'] ?? '').' '
                        .($item['registrado_por'] ?? ''),
                    ));

                    return str_contains($haystack, $q);
                },
            ));
        }

        $filename = 'reporte-egresos-caja-'.now()->format('Ymd-His').'.xlsx';
        $this->auditExport('reporte_financiero', $filename);

        $export = new ReporteEgresosXlsxExport;

        return response()->streamDownload(
            function () use ($export, $items, $payload): void {
                $export->streamTo(
                    $items,
                    $payload['totales'],
                    $payload['por_motivo'],
                    $payload['filtros'],
                    (string) $payload['moneda'],
                );
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }

    private function assertAccess(User $user): void
    {
        abort_unless($user->can('reporte-financiero.view'), 403);

        // Egresos viven en caja; si el plan no tiene ventas/caja, no mostrar.
        $capabilities = TenantModuleAccess::filterCapabilities(
            $this->tenantManager->current()?->tenant,
            [
                'ventas' => $user->can('ventas.view') || $user->can('caja-sesiones.view'),
            ],
        );

        abort_unless((bool) ($capabilities['ventas'] ?? false), 403);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
