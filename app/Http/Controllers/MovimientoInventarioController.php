<?php

namespace App\Http\Controllers;

use App\Exports\MovimientosInventarioXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Requests\MovimientoInventarioStoreRequest;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
use App\Support\Inventario\MovimientoNotasVista;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientoInventarioController extends Controller
{
    use LogsAuditExports;

    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'created_at',
        'tipo',
        'delta',
        'nombre',
    ];

    private const TIPO_FILTRO_OPTIONS = ['todos', MovimientoInventario::TIPO_ENTRADA, MovimientoInventario::TIPO_SALIDA, MovimientoInventario::TIPO_MERMA, MovimientoInventario::TIPO_AJUSTE];

    public function index(Request $request): Response
    {
        $ctx = $this->resolveMovimientosKardexList($request);

        if ($ctx['query'] === null) {
            $movimientos = MovimientoInventario::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();

            return Inertia::render('inventario/movimientos/index', [
                'movimientos' => $movimientos,
                'filters' => [
                    'search' => $ctx['search'],
                    'per_page' => $ctx['per_page'],
                    'sort' => $ctx['sort_valid'] ? $ctx['sort'] : null,
                    'direction' => $ctx['sort_valid'] && $ctx['direction_valid'] ? $ctx['direction'] : null,
                    'sede_id' => $ctx['sede_id'],
                    'tipo' => $ctx['tipo_filtro'],
                    'creado_desde' => $ctx['creado_desde'],
                    'creado_hasta' => $ctx['creado_hasta'],
                ],
                'movimiento_filtro_ui' => $ctx['rango_filtro_ui'],
                'stats' => [
                    'total' => 0,
                    'coincidencias' => 0,
                ],
                'sedeOptions' => $ctx['sedes_activas'],
                'productoOptions' => [],
                'sinSedes' => $ctx['sedes_activas']->isEmpty(),
            ]);
        }

        $movimientos = $ctx['query']->paginate($ctx['per_page'])->withQueryString();

        $totalEnRangoQuery = MovimientoInventario::query()
            ->where('sede_id', $ctx['sede_id'])
            ->whereBetween('created_at', [$ctx['inicio_rango'], $ctx['fin_rango']]);

        if ($ctx['tipo_filtro'] !== 'todos') {
            $totalEnRangoQuery->where('tipo', $ctx['tipo_filtro']);
        }

        $totalEnRango = $totalEnRangoQuery->count();

        $sedeMap = $ctx['sedes_activas']->keyBy('id');
        $movimientos->getCollection()->transform(function (MovimientoInventario $m) use ($sedeMap): MovimientoInventario {
            $sede = $sedeMap->get($m->sede_id);
            $m->setAttribute('sede_nombre', $sede !== null ? $sede->nombre : '—');
            $m->setAttribute('sede_codigo', $sede !== null ? $sede->codigo : null);
            $m->setAttribute('notas_vista', MovimientoNotasVista::fromModel($m));
            $m->makeHidden('compra');

            return $m;
        });

        $productoOptions = Producto::query()
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(400)
            ->get(['id', 'nombre', 'sku']);

        return Inertia::render('inventario/movimientos/index', [
            'movimientos' => $movimientos,
            'filters' => [
                'search' => $ctx['search'],
                'per_page' => $ctx['per_page'],
                'sort' => $ctx['sort_valid'] ? $ctx['sort'] : null,
                'direction' => $ctx['sort_valid'] && $ctx['direction_valid'] ? $ctx['direction'] : null,
                'sede_id' => $ctx['sede_id'],
                'tipo' => $ctx['tipo_filtro'],
                'creado_desde' => $ctx['creado_desde'],
                'creado_hasta' => $ctx['creado_hasta'],
            ],
            'movimiento_filtro_ui' => $ctx['rango_filtro_ui'],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $movimientos->total(),
            ],
            'sedeOptions' => $ctx['sedes_activas'],
            'productoOptions' => $productoOptions,
            'sinSedes' => false,
        ]);
    }

    /**
     * Exporta el kardex a XLSX respetando búsqueda, sede, tipo y rango de fechas.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $ctx = $this->resolveMovimientosKardexList($request);

        $query = $ctx['query'];
        if ($query === null) {
            $query = MovimientoInventario::query()->whereRaw('1 = 0');
        } else {
            $query = clone $query;
        }

        $query->with(['creadoPor:id,name', 'producto:id,nombre,sku', 'sede:id,nombre,codigo', 'compra:id,serie,numero_documento,anulada_at']);

        $filename = 'movimientos-inventario-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new MovimientosInventarioXlsxExport();

        $this->auditExport('movimientos_stock', $filename);

        return response()->streamDownload(
            function () use ($exporter, $query): void {
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

    public function store(MovimientoInventarioStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()?->id;
        $uid = $userId !== null ? (string) $userId : null;

        if (($data['tipo'] ?? '') === MovimientoInventarioStoreRequest::TIPO_TRASLADO) {
            $this->lotes->registrarTraslado(
                $data['producto_id'],
                $data['sede_id'],
                (string) $data['sede_destino_id'],
                (string) ((float) (string) $data['cantidad']),
                $data['notas'] ?? null,
                $uid,
            );

            return back()->with('success', 'Traslado registrado correctamente.');
        }

        $this->lotes->registrarMovimientoManual(
            $data['tipo'],
            $data['producto_id'],
            $data['sede_id'],
            (string) ((float) (string) $data['cantidad']),
            $data['notas'] ?? null,
            $uid,
            isset($data['numero_lote']) ? (string) $data['numero_lote'] : null,
            isset($data['fecha_vencimiento']) ? (string) $data['fecha_vencimiento'] : null,
        );

        return back()->with('success', 'Movimiento registrado correctamente.');
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Resuelve filtros del listado / export del kardex.
     *
     * @return array{
     *     sedes_activas: Collection<int, Sede>,
     *     sede_id: string,
     *     tipo_filtro: string,
     *     search: string,
     *     creado_desde: string,
     *     creado_hasta: string,
     *     rango_filtro_ui: array{default_desde: string, default_hasta: string, fuera_del_mes_actual: bool},
     *     inicio_rango: Carbon,
     *     fin_rango: Carbon,
     *     sort_valid: bool,
     *     sort: string,
     *     direction: string,
     *     direction_valid: bool,
     *     direction_sql: string,
     *     per_page: int,
     *     query: Builder<MovimientoInventario>|null,
     * }
     */
    private function resolveMovimientosKardexList(Request $request): array
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $sedeIds = $sedesActivas->pluck('id')->all();

        $sedeRequested = (string) $request->string('sede_id', '');
        $sedeId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedeIds, true)
            ? $sedeRequested
            : (string) ($sedesActivas->first()?->id ?? '');

        $tipoFiltro = (string) $request->string('tipo', 'todos');
        if (! in_array($tipoFiltro, self::TIPO_FILTRO_OPTIONS, true)) {
            $tipoFiltro = 'todos';
        }

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $creadoDesde = $this->parseDateParam($request->query('creado_desde'));
        $creadoHasta = $this->parseDateParam($request->query('creado_hasta'));

        if ($creadoDesde === null || $creadoHasta === null) {
            $creadoDesde = $defaultDesde;
            $creadoHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($creadoDesde > $creadoHasta) {
                [$creadoDesde, $creadoHasta] = [$creadoHasta, $creadoDesde];
            }
            $fueraDelMesActual = ($creadoDesde !== $defaultDesde) || ($creadoHasta !== $defaultHasta);
        }

        $inicioRango = Carbon::parse($creadoDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($creadoHasta, $tz)->endOfDay();

        $rangoFiltroUi = [
            'default_desde' => $defaultDesde,
            'default_hasta' => $defaultHasta,
            'fuera_del_mes_actual' => $fueraDelMesActual,
        ];

        $query = null;
        if (! $sedesActivas->isEmpty() && $sedeId !== '') {
            $query = MovimientoInventario::query()
                ->join('productos', function ($join): void {
                    $join->on('productos.id', '=', 'movimientos_inventario.producto_id')
                        ->whereNull('productos.deleted_at');
                })
                ->select('movimientos_inventario.*')
                ->with(['creadoPor:id,name', 'producto:id,nombre,sku', 'compra:id,serie,numero_documento,anulada_at']);

            if ($tipoFiltro !== 'todos') {
                $query->where('movimientos_inventario.tipo', $tipoFiltro);
            }

            $query->where('movimientos_inventario.sede_id', $sedeId);
            $query->whereBetween('movimientos_inventario.created_at', [$inicioRango, $finRango]);

            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where('productos.nombre', 'ILIKE', "%{$search}%")
                        ->orWhere('productos.sku', 'ILIKE', "%{$search}%")
                        ->orWhere('movimientos_inventario.notas', 'ILIKE', "%{$search}%");
                });
            }

            if ($sortValid && $sort === 'nombre') {
                $query->orderBy('productos.nombre', $directionSql);
                $query->orderByDesc('movimientos_inventario.created_at');
            } elseif ($sortValid) {
                $query->orderBy('movimientos_inventario.'.$sort, $directionSql);
                $query->orderByDesc('movimientos_inventario.created_at');
            } else {
                $query->orderByDesc('movimientos_inventario.created_at');
            }
        }

        return [
            'sedes_activas' => $sedesActivas,
            'sede_id' => $sedeId,
            'tipo_filtro' => $tipoFiltro,
            'search' => $search,
            'creado_desde' => $creadoDesde,
            'creado_hasta' => $creadoHasta,
            'rango_filtro_ui' => $rangoFiltroUi,
            'inicio_rango' => $inicioRango,
            'fin_rango' => $finRango,
            'sort_valid' => $sortValid,
            'sort' => $sort,
            'direction' => $direction,
            'direction_valid' => $directionValid,
            'direction_sql' => $directionSql,
            'per_page' => $perPage,
            'query' => $query,
        ];
    }
}
