<?php

namespace App\Http\Controllers;

use App\Exports\ComprasInventarioXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Requests\CompraInventarioStoreRequest;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompraInventarioController extends Controller
{
    use LogsAuditExports;

    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = ['fecha_documento', 'created_at', 'numero_documento'];

    public function index(Request $request): Response
    {
        $ctx = $this->resolveComprasListaContext($request);

        $proveedorOptions = Proveedor::query()
            ->whereNull('deleted_at')
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social']);

        $productoOptions = Producto::query()
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(400)
            ->get(['id', 'nombre', 'sku']);

        $unidadOptions = UnidadMedidaOpciones::forProductoForm();
        $canCreateProducto = $request->user()?->can('productos.create') ?? false;

        $filtersPayload = [
            'search' => $ctx['search'],
            'per_page' => $ctx['per_page'],
            'sort' => $ctx['sort_valid'] ? $ctx['sort'] : null,
            'direction' => $ctx['sort_valid'] && $ctx['direction_valid'] ? $ctx['direction'] : null,
            'sede_id' => $ctx['sede_id'],
            'proveedor_id' => $ctx['proveedor_id'] !== '' ? $ctx['proveedor_id'] : null,
            'fecha_desde' => $ctx['fecha_desde'],
            'fecha_hasta' => $ctx['fecha_hasta'],
        ];

        if ($ctx['base_query'] === null) {
            $compras = Compra::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();

            return Inertia::render('inventario/compras/index', [
                'compras' => $compras,
                'filters' => $filtersPayload,
                'compra_filtro_ui' => $ctx['compra_filtro_ui'],
                'stats' => [
                    'total' => 0,
                    'coincidencias' => 0,
                ],
                'sedeOptions' => $ctx['sedes_activas'],
                'proveedorOptions' => $proveedorOptions,
                'productoOptions' => $productoOptions,
                'unidadOptions' => $unidadOptions,
                'canCreateProducto' => $canCreateProducto,
                'sinSedes' => $ctx['sedes_activas']->isEmpty(),
            ]);
        }

        $listQuery = clone $ctx['base_query'];
        if ($ctx['search'] !== '') {
            $listQuery->where(function ($q) use ($ctx): void {
                $search = $ctx['search'];
                $q->where('numero_documento', 'ILIKE', "%{$search}%")
                    ->orWhere('serie', 'ILIKE', "%{$search}%")
                    ->orWhere('notas', 'ILIKE', "%{$search}%");
            });
        }

        $compras = $listQuery->paginate($ctx['per_page'])->withQueryString();

        $sedeMap = $ctx['sedes_activas']->keyBy('id');
        $compras->getCollection()->transform(function (Compra $c) use ($sedeMap): Compra {
            $sede = $sedeMap->get($c->sede_id);
            $c->setAttribute('sede_nombre', $sede !== null ? $sede->nombre : '—');
            $c->setAttribute('sede_codigo', $sede !== null ? $sede->codigo : null);

            return $c;
        });

        return Inertia::render('inventario/compras/index', [
            'compras' => $compras,
            'filters' => $filtersPayload,
            'compra_filtro_ui' => $ctx['compra_filtro_ui'],
            'stats' => [
                'total' => $ctx['total_en_rango'],
                'coincidencias' => $compras->total(),
            ],
            'sedeOptions' => $ctx['sedes_activas'],
            'proveedorOptions' => $proveedorOptions,
            'productoOptions' => $productoOptions,
            'unidadOptions' => $unidadOptions,
            'canCreateProducto' => $canCreateProducto,
            'sinSedes' => false,
        ]);
    }

    /**
     * Exporta compras a XLSX respetando sede, proveedor, rango de fechas del documento y búsqueda.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('compras.view'), 403);

        $ctx = $this->resolveComprasListaContext($request);

        if ($ctx['base_query'] === null) {
            $query = Compra::query()->whereRaw('1 = 0');
        } else {
            $query = clone $ctx['base_query'];
            if ($ctx['search'] !== '') {
                $search = $ctx['search'];
                $query->where(function ($q) use ($search): void {
                    $q->where('numero_documento', 'ILIKE', "%{$search}%")
                        ->orWhere('serie', 'ILIKE', "%{$search}%")
                        ->orWhere('notas', 'ILIKE', "%{$search}%");
                });
            }
        }

        $query->withOnly([
            'proveedor:id,ruc,razon_social',
            'creadoPor:id,name',
            'sede:id,nombre,codigo',
        ]);

        $filename = 'compras-inventario-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new ComprasInventarioXlsxExport;

        $this->auditExport('compras', $filename);

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

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Filtros compartidos entre el listado Inertia y la exportación XLSX.
     *
     * @return array{
     *     sedes_activas: Collection<int, Sede>,
     *     sede_id: string,
     *     proveedor_id: string,
     *     search: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     compra_filtro_ui: array{default_desde: string, default_hasta: string, fuera_del_mes_actual: bool},
     *     sort_valid: bool,
     *     sort: string,
     *     direction: string,
     *     direction_valid: bool,
     *     direction_sql: string,
     *     per_page: int,
     *     base_query: Builder<Compra>|null,
     *     total_en_rango: int,
     * }
     */
    private function resolveComprasListaContext(Request $request): array
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

        $proveedorRequested = (string) $request->string('proveedor_id', '');
        $proveedorId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $proveedorRequested) === 1
            ? $proveedorRequested
            : '';

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $fechaDesde = $this->parseDateParam($request->query('fecha_desde'));
        $fechaHasta = $this->parseDateParam($request->query('fecha_hasta'));

        if ($fechaDesde === null || $fechaHasta === null) {
            $fechaDesde = $defaultDesde;
            $fechaHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($fechaDesde > $fechaHasta) {
                [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
            }
            $fueraDelMesActual = ($fechaDesde !== $defaultDesde) || ($fechaHasta !== $defaultHasta);
        }

        $compraFiltroUi = [
            'default_desde' => $defaultDesde,
            'default_hasta' => $defaultHasta,
            'fuera_del_mes_actual' => $fueraDelMesActual,
        ];

        $baseQuery = null;
        $totalEnRango = 0;

        if (! $sedesActivas->isEmpty() && $sedeId !== '') {
            $baseQuery = Compra::query()
                ->whereNull('anulada_at')
                ->with([
                    'proveedor:id,ruc,razon_social',
                    'creadoPor:id,name',
                    'lineas' => fn ($q) => $q->orderBy('orden'),
                    'lineas.producto:id,nombre,sku',
                ])
                ->withCount('lineas')
                ->where('sede_id', $sedeId)
                ->whereDate('fecha_documento', '>=', $fechaDesde)
                ->whereDate('fecha_documento', '<=', $fechaHasta);

            if ($proveedorId !== '') {
                $baseQuery->where('proveedor_id', $proveedorId);
            }

            if ($sortValid) {
                $baseQuery->orderBy($sort, $directionSql);
                $baseQuery->orderByDesc('created_at');
            } else {
                $baseQuery->orderByDesc('fecha_documento');
                $baseQuery->orderByDesc('created_at');
            }

            $totalEnRango = (clone $baseQuery)->count();
        }

        return [
            'sedes_activas' => $sedesActivas,
            'sede_id' => $sedeId,
            'proveedor_id' => $proveedorId,
            'search' => $search,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'compra_filtro_ui' => $compraFiltroUi,
            'sort_valid' => $sortValid,
            'sort' => $sort,
            'direction' => $direction,
            'direction_valid' => $directionValid,
            'direction_sql' => $directionSql,
            'per_page' => $perPage,
            'base_query' => $baseQuery,
            'total_en_rango' => $totalEnRango,
        ];
    }

    public function store(CompraInventarioStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = Auth::id();

        $tid = tenant_id();
        if ($tid === null) {
            abort(403);
        }

        DB::transaction(function () use ($data, $userId, $request, $tid): void {
            $compra = Compra::query()->create([
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'sede_id' => $data['sede_id'],
                'fecha_documento' => $data['fecha_documento'],
                'numero_documento' => $data['numero_documento'] ?? null,
                'serie' => $data['serie'] ?? null,
                'moneda' => $data['moneda'] ?? 'PEN',
                'total' => $data['total'] ?? null,
                'notas' => $data['notas'] ?? null,
                'created_by_id' => $userId !== null ? (string) $userId : null,
            ]);

            if ($request->hasFile('factura')) {
                $file = $request->file('factura');
                $ext = Str::lower((string) ($file->getClientOriginalExtension() ?: 'bin'));
                $safe = Str::lower(Str::random(24)).'.'.$ext;
                $baseDir = 'compras/'.$tid.'/'.$compra->id;
                $path = $file->storeAs($baseDir, $safe, 'local');
                $compra->update([
                    'factura_path' => $path,
                    'factura_original_name' => $file->getClientOriginalName(),
                ]);
            }

            $refDoc = trim(implode('-', array_filter([$data['serie'] ?? null, $data['numero_documento'] ?? null])));
            if ($refDoc === '') {
                $refDoc = 'ref.'.Str::lower(Str::substr((string) $compra->id, 0, 8));
            }

            $moneda = $data['moneda'] ?? 'PEN';

            foreach ($data['lineas'] as $i => $linea) {
                $compraLinea = CompraLinea::query()->create([
                    'compra_id' => $compra->id,
                    'producto_id' => $linea['producto_id'],
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'] ?? null,
                    'numero_lote' => $linea['numero_lote'] ?? null,
                    'fecha_vencimiento' => $linea['fecha_vencimiento'] ?? null,
                    'orden' => (int) $i,
                ]);

                $costoUnit = $linea['costo_unitario'] ?? null;
                $notasMov = 'Entrada por compra '.$refDoc;
                if ($costoUnit !== null && $costoUnit !== '') {
                    $notasMov .= ' · '.$moneda.' '.number_format((float) (string) $costoUnit, 2, '.', '').'/u.';
                }

                $this->lotes->registrarEntrada(
                    $linea['producto_id'],
                    $data['sede_id'],
                    (string) ((float) (string) $linea['cantidad']),
                    isset($linea['numero_lote']) ? (string) $linea['numero_lote'] : null,
                    isset($linea['fecha_vencimiento']) ? (string) $linea['fecha_vencimiento'] : null,
                    $notasMov,
                    $userId !== null ? (string) $userId : null,
                    (string) $compra->id,
                    (string) $compraLinea->id,
                );
            }
        });

        return back()->with('success', 'Compra registrada y stock actualizado.');
    }

    public function destroy(Request $request, Compra $compra): RedirectResponse
    {
        abort_unless($request->user()?->can('compras.delete'), 403);

        if ($compra->anulada_at !== null) {
            return back()->with('error', 'Esta compra ya fue anulada.');
        }

        $tid = tenant_id();
        if ($tid === null) {
            abort(403);
        }

        try {
            DB::transaction(function () use ($compra, $request, $tid): void {
                $c = Compra::query()
                    ->whereKey($compra->id)
                    ->whereNull('anulada_at')
                    ->lockForUpdate()
                    ->first();

                if ($c === null) {
                    throw ValidationException::withMessages([
                        'compra' => 'Esta compra ya fue anulada o no existe.',
                    ]);
                }

                $refDoc = trim(implode('-', array_filter([(string) ($c->serie ?? ''), (string) ($c->numero_documento ?? '')])));
                if ($refDoc === '') {
                    $refDoc = 'ref.'.Str::lower(Str::substr((string) $c->id, 0, 8));
                }

                $userId = $request->user()?->id;
                $userIdStr = $userId !== null ? (string) $userId : null;

                $notasRev = 'Anulación compra '.$refDoc.' (reversión de stock)';
                $reversiones = 0;
                $movimientosCompra = MovimientoInventario::query()
                    ->where('compra_id', (string) $c->id)
                    ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
                    ->exists();

                if ($movimientosCompra) {
                    $this->lotes->revertirEntradasCompra((string) $c->id, $userIdStr, $notasRev);
                    $reversiones = 1;
                } else {
                    $lineas = $c->lineas()->orderBy('orden')->get();

                    if ($lineas->isNotEmpty()) {
                        foreach ($lineas as $linea) {
                            $cant = (float) (string) $linea->cantidad;
                            if ($cant <= 0) {
                                continue;
                            }
                            MovimientoInventario::aplicar(
                                (string) $linea->producto_id,
                                (string) $c->sede_id,
                                MovimientoInventario::TIPO_SALIDA,
                                $this->deltaNegativoParaSalida($cant),
                                $notasRev,
                                $userIdStr,
                                null,
                            );
                            $reversiones++;
                        }
                    } else {
                        $porProducto = MovimientoInventario::query()
                            ->where('compra_id', (string) $c->id)
                            ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
                            ->selectRaw('producto_id, sede_id, sum(delta) as total_entrada')
                            ->groupBy('producto_id', 'sede_id')
                            ->get();

                        foreach ($porProducto as $row) {
                            $qty = (float) (string) $row->total_entrada;
                            if ($qty <= 0) {
                                continue;
                            }
                            MovimientoInventario::aplicar(
                                (string) $row->producto_id,
                                (string) $row->sede_id,
                                MovimientoInventario::TIPO_SALIDA,
                                $this->deltaNegativoParaSalida($qty),
                                'Anulación compra '.$refDoc.' (reversión según kardex)',
                                $userIdStr,
                                null,
                            );
                            $reversiones++;
                        }
                    }
                }

                if ($reversiones === 0) {
                    throw ValidationException::withMessages([
                        'compra' => 'No hay líneas ni movimientos de entrada asociados; no se revirtió stock.',
                    ]);
                }

                if ($c->factura_path !== null && str_starts_with((string) $c->factura_path, 'compras/'.$tid.'/'.$c->id.'/')) {
                    Storage::disk('local')->delete((string) $c->factura_path);
                }

                $c->update([
                    'factura_path' => null,
                    'factura_original_name' => null,
                    'anulada_at' => now(),
                    'anulada_por_id' => $userIdStr,
                ]);
            });
        } catch (ValidationException $e) {
            $msg = $e->getMessage();
            if (isset($e->errors()['compra'][0])) {
                $msg = (string) $e->errors()['compra'][0];
            } elseif (isset($e->errors()['cantidad'][0])) {
                $msg = (string) $e->errors()['cantidad'][0];
            }

            return back()->withErrors($e->errors())->with('error', $msg);
        }

        return back()->with(
            'success',
            'Compra anulada: se registraron salidas de inventario por la misma cantidad que entró con la compra; el stock volvió atrás.',
        );
    }

    /**
     * Delta numérico negativo como string, coherente con {@see MovimientoInventarioController::store} (salida = cantidad negativa).
     */
    private function deltaNegativoParaSalida(float $cantidadPositiva): string
    {
        $n = round($cantidadPositiva, 3);

        return (string) (-$n);
    }

    public function downloadFactura(Request $request, Compra $compra): BinaryFileResponse
    {
        abort_unless($request->user()?->can('compras.view'), 403);

        $tid = tenant_id();
        if ($tid === null || $compra->factura_path === null) {
            abort(404);
        }

        if ($compra->anulada_at !== null) {
            abort(404);
        }

        $expectedPrefix = 'compras/'.$tid.'/'.$compra->id.'/';
        if (! str_starts_with((string) $compra->factura_path, $expectedPrefix)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($compra->factura_path)) {
            abort(404);
        }

        $name = $compra->factura_original_name ?? 'factura.pdf';
        $absolutePath = Storage::disk('local')->path((string) $compra->factura_path);

        return response()->download($absolutePath, $name);
    }
}
