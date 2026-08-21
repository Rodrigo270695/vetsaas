<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ReporteVentasProductosXlsxExport;
use App\Exports\ReporteVentasServiciosXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Models\User;
use App\Services\Reportes\ReporteVentasService;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReporteVentasController extends Controller
{
    use LogsAuditExports;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ReporteVentasService $reportes,
    ) {}

    public function productos(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        $this->assertProductosAccess($user);

        $payload = $this->reportes->ventasPorProducto(
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
        );

        return Inertia::render('reportes/ventas-productos/index', [
            'moneda' => $payload['moneda'],
            'filtros' => $payload['filtros'],
            'totales' => $payload['totales'],
            'items' => $payload['items'],
            'can_export' => $this->userCan($user, 'reporte-financiero.export'),
        ]);
    }

    public function exportProductos(Request $request): StreamedResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        $this->assertProductosAccess($user);
        abort_unless($this->userCan($user, 'reporte-financiero.export'), 403);

        $payload = $this->reportes->ventasPorProducto(
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
        );

        $items = $this->filterItemsBySearch($payload['items'], $this->stringOrNull($request->query('search')));
        $totales = $this->recomputeTotales($items);

        $filename = 'reporte-ventas-productos-'.now()->format('Ymd-His').'.xlsx';
        $this->auditExport('reporte_financiero', $filename);

        $export = new ReporteVentasProductosXlsxExport;

        return response()->streamDownload(
            function () use ($export, $items, $totales, $payload): void {
                $export->streamTo(
                    $items,
                    $totales,
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

    public function servicios(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        [$capabilities, $tipo, $includeGrooming] = $this->resolveServiciosContext($user, $request);

        $payload = $this->reportes->ventasPorServicio(
            $tipo,
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
            $includeGrooming,
        );

        return Inertia::render('reportes/ventas-servicios/index', [
            'moneda' => $payload['moneda'],
            'filtros' => $payload['filtros'],
            'totales' => $payload['totales'],
            'resumen' => $payload['resumen'],
            'vacuna_aplicaciones' => $payload['vacuna_aplicaciones'] ?? ['total' => 0, 'sin_cobro' => 0],
            'items' => $payload['items'],
            'capabilities' => [
                'ventas' => (bool) ($capabilities['ventas'] ?? false),
                'grooming' => (bool) ($capabilities['grooming'] ?? false),
            ],
            'can_export' => $this->userCan($user, 'reporte-financiero.export'),
        ]);
    }

    public function exportServicios(Request $request): StreamedResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();
        [$capabilities, $tipo, $includeGrooming] = $this->resolveServiciosContext($user, $request);
        abort_unless($this->userCan($user, 'reporte-financiero.export'), 403);

        // Para export multi-hoja (resumen/todos) necesitamos todos los ítems.
        $tipoExport = in_array($tipo, ['tratamiento', 'vacuna', 'grooming'], true) ? $tipo : 'todos';

        $payload = $this->reportes->ventasPorServicio(
            $tipoExport,
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
            $includeGrooming,
        );

        $items = $this->filterItemsBySearch($payload['items'], $this->stringOrNull($request->query('search')));
        $payload['filtros']['tipo'] = $tipoExport === 'todos' ? 'todos' : $tipoExport;
        $totales = $this->recomputeTotales($items);
        $resumen = $this->recomputeResumen($items, $includeGrooming);

        $filename = 'reporte-ventas-servicios-'.now()->format('Ymd-His').'.xlsx';
        $this->auditExport('reporte_financiero', $filename);

        $export = new ReporteVentasServiciosXlsxExport;

        return response()->streamDownload(
            function () use ($export, $items, $totales, $resumen, $payload, $includeGrooming): void {
                $export->streamTo(
                    $items,
                    $totales,
                    $resumen,
                    $payload['filtros'],
                    (string) $payload['moneda'],
                    $includeGrooming,
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

    private function assertProductosAccess(User $user): void
    {
        $capabilities = TenantModuleAccess::filterCapabilities(
            $this->tenantManager->current()?->tenant,
            [
                'ventas' => $this->userCan($user, 'ventas.view'),
                'productos' => $this->userCan($user, 'productos.view'),
            ],
        );

        abort_unless(
            ($capabilities['ventas'] ?? false) && ($capabilities['productos'] ?? false),
            403,
        );
    }

    /**
     * @return array{0: array<string, bool>, 1: string, 2: bool}
     */
    private function resolveServiciosContext(User $user, Request $request): array
    {
        $capabilities = TenantModuleAccess::filterCapabilities(
            $this->tenantManager->current()?->tenant,
            [
                'ventas' => $this->userCan($user, 'ventas.view'),
                'grooming' => $this->userCan($user, 'grooming.view'),
            ],
        );

        abort_unless($capabilities['ventas'] ?? false, 403);

        $tipo = (string) $request->query('tipo', 'todos');
        if (! in_array($tipo, ['todos', 'tratamiento', 'vacuna', 'grooming'], true)) {
            $tipo = 'todos';
        }

        $includeGrooming = (bool) ($capabilities['grooming'] ?? false);
        if ($tipo === 'grooming' && ! $includeGrooming) {
            $tipo = 'todos';
        }

        return [$capabilities, $tipo, $includeGrooming];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filterItemsBySearch(array $items, ?string $search): array
    {
        if ($search === null) {
            return $items;
        }

        $q = mb_strtolower(trim($search));
        if ($q === '') {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static function (array $item) use ($q): bool {
                $haystack = mb_strtolower(
                    trim((string) ($item['nombre'] ?? '').' '.(string) ($item['categoria'] ?? '')),
                );

                return str_contains($haystack, $q);
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}
     */
    private function recomputeTotales(array $items): array
    {
        $unidades = 0.0;
        $ingresos = 0.0;
        $costo = 0.0;
        $utilidadAcum = 0.0;
        $conCosto = 0;
        $sinCosto = 0;
        $ventas = 0;

        foreach ($items as $item) {
            $unidades += (float) ($item['cantidad'] ?? 0);
            $ingresos += (float) ($item['ingreso'] ?? 0);
            $ventas += (int) ($item['ventas'] ?? 0);
            if (! empty($item['tiene_costo'])) {
                $costo += (float) ($item['costo'] ?? 0);
                $utilidadAcum += (float) ($item['utilidad'] ?? 0);
                $conCosto++;
            } else {
                $sinCosto++;
            }
        }

        $ingresos = round($ingresos, 2);
        $costo = round($costo, 2);
        $utilidad = $conCosto > 0 ? round($utilidadAcum, 2) : null;
        $margen = ($utilidad !== null && $ingresos > 0)
            ? round(max(-999.9, min(999.9, ($utilidad / $ingresos) * 100)), 1)
            : null;

        return [
            'unidades' => round($unidades, 2),
            'ventas' => $ventas,
            'ingresos' => $ingresos,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen_pct' => $margen,
            'items_sin_costo' => $sinCosto,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     tratamiento: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int},
     *     vacuna: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int},
     *     grooming: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int}
     * }
     */
    private function recomputeResumen(array $items, bool $includeGrooming): array
    {
        $buckets = [
            'tratamiento' => [],
            'vacuna' => [],
            'grooming' => [],
        ];

        foreach ($items as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (! isset($buckets[$tipo])) {
                continue;
            }
            if ($tipo === 'grooming' && ! $includeGrooming) {
                continue;
            }
            $buckets[$tipo][] = $item;
        }

        $empty = [
            'unidades' => 0.0,
            'ventas' => 0,
            'ingresos' => 0.0,
            'costo' => 0.0,
            'utilidad' => null,
            'margen_pct' => null,
            'items' => 0,
            'items_sin_costo' => 0,
        ];

        $out = [];
        foreach (['tratamiento', 'vacuna', 'grooming'] as $tipo) {
            if ($tipo === 'grooming' && ! $includeGrooming) {
                $out[$tipo] = $empty;

                continue;
            }

            $sliceItems = $buckets[$tipo];
            $totales = $this->recomputeTotales($sliceItems);
            $out[$tipo] = [
                ...$totales,
                'items' => count($sliceItems),
            ];
        }

        return $out;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function userCan(User $user, string $ability): bool
    {
        try {
            return $user->can($ability);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
