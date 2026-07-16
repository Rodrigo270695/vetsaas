<?php

namespace App\Http\Controllers;

use App\Exports\VentasXlsxExport;
use App\Grooming\GroomingCatalogoMode;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\ProductoRapidoVentaRequest;
use App\Http\Requests\PropietarioRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\Venta;
use App\Services\Fel\FelEmisionVentaService;
use App\Services\Inventario\InventarioLoteService;
use App\Services\Venta\VentaAnulacionService;
use App\Services\Venta\VentaCheckoutService;
use App\Services\Venta\VentaTicketPdfService;
use App\Services\Venta\VentaWhatsAppComprobanteSender;
use App\Support\Caja\TicketAnchoMm;
use App\Support\Caja\VentaTicketPolicy;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelReceptorResolver;
use App\Support\Fel\FelSerieResolver;
use App\Support\Inventario\UnidadMedidaOpciones;
use App\Support\PlanCapabilities;
use App\Support\Tenancy\TenantModuleAccess;
use App\Support\Venta\VentaDesdeCargoPrefill;
use App\Support\WhatsApp\WhatsAppChatId;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VentaController extends Controller
{
    use LogsAuditExports;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'created_at',
        'numero',
        'total',
        'estado',
    ];

    public function index(Request $request): Response
    {
        $ctx = $this->resolveVentasListaContext($request);

        $filtersPayload = [
            'search' => $ctx['search'],
            'per_page' => $ctx['per_page'],
            'sort' => $ctx['sort_valid'] ? $ctx['sort'] : null,
            'direction' => $ctx['sort_valid'] && $ctx['direction_valid'] ? $ctx['direction'] : null,
            'estado' => $ctx['estado'],
            'fecha_desde' => $ctx['fecha_desde'],
            'fecha_hasta' => $ctx['fecha_hasta'],
        ];

        $listQuery = clone $ctx['base_query'];

        if ($ctx['search'] !== '') {
            $this->applyVentasSearchFilter($listQuery, $ctx['search']);
        }

        $ventas = $listQuery->paginate($ctx['per_page'])->withQueryString();

        $tenantId = $request->user()?->tenant_id;
        $sedeIds = $ventas->pluck('sede_id')->unique()->filter()->all();
        $sedeNombres = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $sedeIds)
            ->pluck('nombre', 'id');

        $ventas->getCollection()->transform(function (Venta $v) use ($sedeNombres): array {
            $p = $v->propietario;
            $nombreCliente = $p === null
                ? '—'
                : ($p->razon_social ?: trim(implode(' ', array_filter([$p->nombres, $p->apellidos]))));

            return [
                'id' => $v->id,
                'numero' => $v->numero,
                'numero_display' => $v->felDocument?->numero_completo ?? $v->numero,
                'estado' => $v->estado,
                'moneda' => $v->moneda,
                'total' => (string) $v->total,
                'subtotal' => (string) $v->subtotal,
                'igv_monto' => (string) $v->igv_monto,
                'metodo_pago' => $v->metodo_pago,
                'fel_estado' => $v->fel_estado,
                'created_at' => $v->created_at?->toIso8601String(),
                'cliente' => $nombreCliente,
                'cliente_telefono' => $p?->telefono,
                'paciente' => $v->paciente?->nombre,
                'cajero' => $v->creadoPor?->name ?? '—',
                'sede' => $sedeNombres[$v->sede_id] ?? '—',
                'pdf_url' => $v->felDocument?->url_pdf,
            ];
        });

        return Inertia::render('caja/ventas/index', [
            'ventas' => $ventas,
            'filters' => $filtersPayload,
            'venta_filtro_ui' => $ctx['venta_filtro_ui'],
            'ticket_ancho_mm' => TicketAnchoMm::normalize((string) ClinicSetting::current()->ticket_ancho_mm),
            'stats' => [
                ...$ctx['stats_summary'],
                'coincidencias' => $ventas->total(),
            ],
        ]);
    }

    /**
     * Exporta ventas a XLSX respetando rango de fechas, estado y búsqueda.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('ventas.view'), 403);

        $ctx = $this->resolveVentasListaContext($request);

        $query = clone $ctx['base_query'];

        if ($ctx['search'] !== '') {
            $this->applyVentasSearchFilter($query, $ctx['search']);
        }

        $query->withOnly([
            'propietario:id,nombres,apellidos,razon_social',
            'paciente:id,nombre',
            'creadoPor:id,name',
            'sede:id,nombre,codigo',
            'felDocument:id,venta_id,numero_completo,estado',
        ]);

        $filename = 'ventas-caja-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new VentasXlsxExport;

        $this->auditExport('ventas', $filename);

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
     *     search: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     venta_filtro_ui: array{default_desde: string, default_hasta: string, fuera_del_mes_actual: bool},
     *     estado: string,
     *     sort_valid: bool,
     *     sort: string,
     *     direction: string,
     *     direction_valid: bool,
     *     direction_sql: string,
     *     per_page: int,
     *     base_query: Builder<Venta>,
     *     total_en_rango: int,
     *     stats_summary: array{
     *         total: int,
     *         pagado: int,
     *         pendiente: int,
     *         parcial: int,
     *         anulado: int,
     *         cpe_emitidos: int,
     *     },
     * }
     */
    private function resolveVentasListaContext(Request $request): array
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 15;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, ['todas', Venta::ESTADO_PAGADO, Venta::ESTADO_PENDIENTE, Venta::ESTADO_PARCIAL, Venta::ESTADO_ANULADO], true)) {
            $estado = 'todas';
        }

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

        $ventaFiltroUi = [
            'default_desde' => $defaultDesde,
            'default_hasta' => $defaultHasta,
            'fuera_del_mes_actual' => $fueraDelMesActual,
        ];

        $baseQuery = Venta::query()
            ->with([
                'propietario:id,nombres,apellidos,razon_social,telefono',
                'paciente:id,nombre',
                'creadoPor:id,name',
                'felDocument:id,venta_id,numero_completo,estado,url_pdf,enlace_consulta',
            ])
            ->whereRaw('DATE(COALESCE(fecha_pago, created_at)) >= ?', [$fechaDesde])
            ->whereRaw('DATE(COALESCE(fecha_pago, created_at)) <= ?', [$fechaHasta]);

        if ($estado !== 'todas') {
            $baseQuery->where('estado', $estado);
        }

        if ($sortValid) {
            $baseQuery->orderBy($sort, $directionSql);
            if ($sort !== 'created_at') {
                $baseQuery->orderByDesc('created_at');
            }
        } else {
            $baseQuery->orderByDesc('created_at');
        }

        $totalEnRango = (clone $baseQuery)->count();

        $statsSummary = $this->ventasStatsSummary(
            $fechaDesde,
            $fechaHasta,
            $search,
        );

        return [
            'search' => $search,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'venta_filtro_ui' => $ventaFiltroUi,
            'estado' => $estado,
            'sort_valid' => $sortValid,
            'sort' => $sort,
            'direction' => $direction,
            'direction_valid' => $directionValid,
            'direction_sql' => $directionSql,
            'per_page' => $perPage,
            'base_query' => $baseQuery,
            'total_en_rango' => $totalEnRango,
            'stats_summary' => $statsSummary,
        ];
    }

    /**
     * Conteos agregados del periodo (fecha + búsqueda), sin filtro de estado.
     *
     * @return array{
     *     total: int,
     *     pagado: int,
     *     pendiente: int,
     *     parcial: int,
     *     anulado: int,
     *     cpe_emitidos: int,
     * }
     */
    private function ventasStatsSummary(string $fechaDesde, string $fechaHasta, string $search): array
    {
        $query = Venta::query()
            ->whereRaw('DATE(COALESCE(fecha_pago, created_at)) >= ?', [$fechaDesde])
            ->whereRaw('DATE(COALESCE(fecha_pago, created_at)) <= ?', [$fechaHasta]);

        if ($search !== '') {
            $this->applyVentasSearchFilter($query, $search);
        }

        $row = (clone $query)->selectRaw(
            'COUNT(*) as total,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as pagado,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as pendiente,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as parcial,
            SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as anulado,
            SUM(CASE WHEN fel_estado = ? THEN 1 ELSE 0 END) as cpe_emitidos',
            [
                Venta::ESTADO_PAGADO,
                Venta::ESTADO_PENDIENTE,
                Venta::ESTADO_PARCIAL,
                Venta::ESTADO_ANULADO,
                Venta::FEL_EMITIDO,
            ],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pagado' => (int) ($row->pagado ?? 0),
            'pendiente' => (int) ($row->pendiente ?? 0),
            'parcial' => (int) ($row->parcial ?? 0),
            'anulado' => (int) ($row->anulado ?? 0),
            'cpe_emitidos' => (int) ($row->cpe_emitidos ?? 0),
        ];
    }

    /**
     * @param  Builder<Venta>  $query
     */
    private function applyVentasSearchFilter(Builder $query, string $search): void
    {
        $like = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function ($q) use ($like, $search): void {
            $q->where('numero', 'ILIKE', $like)
                ->orWhereHas('felDocument', fn ($fq) => $fq->where('numero_completo', 'ILIKE', $like))
                ->orWhereHas('propietario', function ($q2) use ($like): void {
                    $q2->where('nombres', 'ILIKE', $like)
                        ->orWhere('apellidos', 'ILIKE', $like)
                        ->orWhere('razon_social', 'ILIKE', $like);
                });
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $search) === 1) {
                $q->orWhere('id', $search);
            }
        });
    }

    public function create(Request $request, TenantManager $tenants): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeNombre = null;
        if ($miSesion !== null) {
            $sedeNombre = Sede::query()->whereKey($miSesion->sede_id)->value('nombre');
        }

        $clinic = ClinicSetting::current();
        $tenantModel = $tenants->current()?->tenant;

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ]);

        return Inertia::render('caja/ventas/create', $this->buildCreatePayload($miSesion, $sedeNombre, $clinic, $tenantModel, $propietarios));
    }

    public function createDesdeConsulta(Request $request, Consulta $consulta, VentaDesdeCargoPrefill $prefill, TenantManager $tenants): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($user->can('ventas.create') && $user->can('consulta-cargos.cobrar'), 403);

        $tenantId = $user->tenant_id;

        try {
            $desdeCargo = $prefill->build($consulta);
        } catch (ValidationException $e) {
            return redirect()
                ->route('clinica.historias-clinicas.consultas.cargos.show', ['consulta' => $consulta])
                ->withErrors($e->errors());
        }

        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeNombre = $miSesion !== null
            ? Sede::query()->whereKey($miSesion->sede_id)->value('nombre')
            : null;

        $clinic = ClinicSetting::current();
        $tenantModel = $tenants->current()?->tenant;

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ]);

        return Inertia::render('caja/ventas/create', [
            ...$this->buildCreatePayload($miSesion, $sedeNombre, $clinic, $tenantModel, $propietarios),
            'desde_cargo' => $desdeCargo,
        ]);
    }

    public function createDesdeInternamiento(
        Request $request,
        Internamiento $internamiento,
        VentaDesdeCargoPrefill $prefill,
        TenantManager $tenants,
    ): Response|RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($user->can('ventas.create') && $user->can('consulta-cargos.cobrar'), 403);

        try {
            $desdeCargo = $prefill->buildFromInternamiento($internamiento);
        } catch (ValidationException $e) {
            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', ['internamiento' => $internamiento])
                ->withErrors($e->errors());
        }

        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeNombre = $miSesion !== null
            ? Sede::query()->whereKey($miSesion->sede_id)->value('nombre')
            : null;

        $clinic = ClinicSetting::current();
        $tenantModel = $tenants->current()?->tenant;

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ]);

        return Inertia::render('caja/ventas/create', [
            ...$this->buildCreatePayload($miSesion, $sedeNombre, $clinic, $tenantModel, $propietarios),
            'desde_cargo' => $desdeCargo,
        ]);
    }

    public function createDesdeGrooming(
        Request $request,
        GroomingTurno $groomingTurno,
        VentaDesdeCargoPrefill $prefill,
        TenantManager $tenants,
    ): Response|RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($user->can('ventas.create') && $user->can('grooming.view'), 403);

        try {
            $desdeCargo = $prefill->buildFromGrooming($groomingTurno);
        } catch (ValidationException $e) {
            return redirect()
                ->route('servicios.grooming')
                ->withErrors($e->errors());
        }

        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeNombre = $miSesion !== null
            ? Sede::query()->whereKey($miSesion->sede_id)->value('nombre')
            : null;

        $clinic = ClinicSetting::current();
        $tenantModel = $tenants->current()?->tenant;

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ]);

        return Inertia::render('caja/ventas/create', [
            ...$this->buildCreatePayload($miSesion, $sedeNombre, $clinic, $tenantModel, $propietarios),
            'desde_cargo' => $desdeCargo,
        ]);
    }

    public function createDesdeHotel(
        Request $request,
        HotelEstancia $hotelEstancia,
        VentaDesdeCargoPrefill $prefill,
        TenantManager $tenants,
    ): Response|RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($user->can('ventas.create') && $user->can('hotel.view'), 403);

        $tenantModel = $tenants->current()?->tenant;
        abort_unless(TenantModuleAccess::isEnabled($tenantModel, 'hotel'), 404);

        try {
            $desdeCargo = $prefill->buildFromHotelEstancia($hotelEstancia);
        } catch (ValidationException $e) {
            return redirect()
                ->route('servicios.hotel')
                ->withErrors($e->errors());
        }

        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeNombre = $miSesion !== null
            ? Sede::query()->whereKey($miSesion->sede_id)->value('nombre')
            : null;

        $clinic = ClinicSetting::current();
        $tenantModel = $tenants->current()?->tenant;

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ]);

        return Inertia::render('caja/ventas/create', [
            ...$this->buildCreatePayload($miSesion, $sedeNombre, $clinic, $tenantModel, $propietarios),
            'desde_cargo' => $desdeCargo,
        ]);
    }

    public function show(Request $request, Venta $venta): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $venta->load([
            'lineas' => fn ($q) => $q->orderBy('id'),
            'lineas.producto:id,sku,unidad',
            'propietario:id,nombres,apellidos,razon_social,numero_documento',
            'paciente:id,nombre',
            'creadoPor:id,name',
            'consulta:id,atendido_at',
            'consulta.historiaClinica.paciente:id,nombre',
            'felDocument',
            'sede:id,activa',
        ]);

        $clinic = ClinicSetting::current();
        $tenantModel = app(TenantManager::class)->current()?->tenant;
        $propietario = $venta->propietario;
        $felEmision = app(FelEmisionVentaService::class);
        $puedeEmitirFel = $felEmision->puedeEmitir($tenantModel, $clinic, $venta);
        $tipoFel = $venta->tipoComprobanteSunat();
        $serieFelCodigo = app(FelSerieResolver::class)->codigoSerieParaVenta($venta, $tipoFel);
        $puedeImprimirTicket = VentaTicketPolicy::puedeImprimir($venta, $clinic, $tenantModel);
        $sedeNombre = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($venta->sede_id)
            ->value('nombre');
        $cliente = $propietario === null
            ? '—'
            : ($propietario->razon_social ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos]))));

        $consultaVinculo = null;
        if ($venta->consulta_id !== null && $venta->consulta !== null) {
            $consultaVinculo = [
                'id' => $venta->consulta_id,
                'atendido_at' => $venta->consulta->atendido_at?->toIso8601String(),
                'paciente' => $venta->consulta->historiaClinica->paciente->nombre ?? $venta->paciente?->nombre,
            ];
        }

        return Inertia::render('caja/ventas/show', [
            'venta' => [
                'id' => $venta->id,
                'numero' => $venta->numero,
                'consulta_id' => $venta->consulta_id,
                'estado' => $venta->estado,
                'moneda' => $venta->moneda,
                'subtotal' => (string) $venta->subtotal,
                'igv_monto' => (string) $venta->igv_monto,
                'descuento_monto' => (string) $venta->descuento_monto,
                'total' => (string) $venta->total,
                'metodo_pago' => $venta->metodo_pago,
                'monto_recibido' => $venta->monto_recibido !== null ? (string) $venta->monto_recibido : null,
                'vuelto' => $venta->vuelto !== null ? (string) $venta->vuelto : null,
                'fecha_pago' => $venta->fecha_pago?->toIso8601String(),
                'created_at' => $venta->created_at?->toIso8601String(),
                'notas' => $venta->notas,
                'fel_estado' => $venta->fel_estado,
                'tipo_comprobante_sunat' => $venta->tipo_comprobante_sunat,
                'fel_document' => $venta->felDocument === null ? null : [
                    'numero_completo' => $venta->felDocument->numero_completo,
                    'estado' => $venta->felDocument->estado,
                    'url_pdf' => $venta->felDocument->url_pdf,
                    'url_xml' => $venta->felDocument->url_xml,
                    'enlace_consulta' => $venta->felDocument->enlace_consulta,
                    'error_mensaje' => $venta->felDocument->error_mensaje,
                    'emitido_at' => $venta->felDocument->emitido_at?->toIso8601String(),
                ],
                'cliente' => $cliente,
                'cliente_doc' => $propietario?->numero_documento,
                'paciente' => $venta->paciente?->nombre,
                'cajero' => $venta->creadoPor?->name ?? '—',
                'sede' => $sedeNombre ?? '—',
                'lineas' => $venta->lineas->map(fn ($ln): array => [
                    'id' => $ln->id,
                    'descripcion' => $ln->descripcion_snapshot,
                    'cantidad' => (string) $ln->cantidad,
                    'precio_unitario' => (string) $ln->precio_unitario,
                    'subtotal' => (string) $ln->subtotal,
                    'sku' => $ln->producto?->sku,
                    'unidad' => $ln->producto?->unidad,
                ])->values()->all(),
            ],
            'clinica' => [
                'igv_porcentaje' => (string) $clinic->igv_porcentaje,
                'ticket_ancho_mm' => TicketAnchoMm::normalize((string) $clinic->ticket_ancho_mm),
                'emite_comprobantes_sunat' => (bool) $clinic->emite_comprobantes_sunat,
                'apisunat_configurado' => ApisunatCredentialResolver::estaConfigurado($clinic),
                'plan_permite_boletas' => PlanCapabilities::boletasElectronicas($tenantModel),
                'plan_permite_facturas' => PlanCapabilities::facturasElectronicas($tenantModel),
            ],
            'fel' => [
                'puede_emitir' => $puedeEmitirFel,
                'emitir_url' => route('caja.ventas.emitir-fel', ['venta' => $venta]),
                'tipo_comprobante' => FelReceptorResolver::etiquetaTipo($tipoFel),
                'serie' => $serieFelCodigo,
            ],
            'ticket' => [
                'puede_imprimir' => $puedeImprimirTicket,
            ],
            'anulacion' => [
                'puede_anular' => $request->user()?->can('ventas.delete')
                    && $venta->estado === Venta::ESTADO_PAGADO,
                'anular_url' => route('caja.ventas.anular', ['venta' => $venta]),
                'anulado_at' => $venta->anulado_at?->toIso8601String(),
                'motivo' => $venta->motivo_anulacion,
            ],
            'consulta_vinculo' => $consultaVinculo,
            'ui' => [
                'auto_imprimir' => $request->boolean('imprimir'),
            ],
        ]);
    }

    public function emitirFel(Request $request, Venta $venta, FelEmisionVentaService $emision): RedirectResponse
    {
        abort_unless($request->user()?->can('ventas.create'), 403);

        try {
            $emision->emitir($venta);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('caja.ventas.show', $venta)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('caja.ventas.show', ['venta' => $venta, 'imprimir' => 1])
            ->with('success', __('caja.ventas.fel.emitida_ok'));
    }

    public function anular(
        AnularVentaRequest $request,
        Venta $venta,
        VentaAnulacionService $anulacion,
    ): RedirectResponse {
        try {
            $anulacion->anular($venta, $request->validated(), $request->user());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('caja.ventas.show', $venta)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('caja.ventas.show', $venta)
            ->with('success', __('caja.ventas.anulacion.ok'));
    }

    /**
     * Vista HTML para impresora térmica (58 mm o 80 mm según Configuración → General).
     * No sustituye comprobante SUNAT salvo que la venta tenga CPE emitido.
     */
    public function ticket(Request $request, Venta $venta, VentaTicketPdfService $ticketPdf): View
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $cfg = ClinicSetting::current();
        $tenantModel = app(TenantManager::class)->current()?->tenant;

        abort_unless(
            VentaTicketPolicy::puedeImprimir($venta, $cfg, $tenantModel),
            403,
            __('caja.ventas.ticket.no_disponible'),
        );

        $ancho = TicketAnchoMm::fromRequest($request, (string) $cfg->ticket_ancho_mm);
        $data = $ticketPdf->viewData($venta, $cfg, (string) $tenantId, $ancho);
        $data['auto_print'] = $request->boolean('print');

        return view('caja.venta-ticket', $data);
    }

    /**
     * Envía el ticket/comprobante por WhatsApp (PDF adjunto) al cliente/propietario.
     * Acepta `telefono` opcional cuando el propietario no tiene número guardado.
     */
    public function enviarWhatsApp(
        Request $request,
        Venta $venta,
        VentaWhatsAppComprobanteSender $sender,
    ): RedirectResponse {
        abort_unless($request->user()?->can('ventas.view'), 403);

        if ($venta->estado === Venta::ESTADO_ANULADO) {
            return back()->with('warning', __('caja.ventas.flash.whatsapp_anulada'));
        }

        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20'],
            'ancho' => ['nullable', 'string', 'in:'.implode(',', TicketAnchoMm::ALLOWED)],
        ]);

        $clinic = ClinicSetting::current();
        $ancho = TicketAnchoMm::normalize($data['ancho'] ?? null, (string) $clinic->ticket_ancho_mm);

        $venta->loadMissing([
            'propietario:id,nombres,apellidos,razon_social,telefono',
        ]);

        $propietario = $venta->propietario;
        $phone = trim((string) ($data['telefono'] ?? '')) !== ''
            ? (string) $data['telefono']
            : $propietario?->telefono;

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', __('caja.ventas.flash.whatsapp_no_phone'));
        }

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return back()->with('warning', __('caja.ventas.flash.whatsapp_fallo'));
        }

        $ownerName = $propietario !== null
            ? (trim($propietario->displayName()) !== '' ? $propietario->displayName() : 'cliente')
            : 'cliente';

        try {
            $sender->send(
                $venta,
                $tenant,
                $chatId,
                $ownerName,
                $clinic,
                $ancho,
            );

            return back()->with('success', __('caja.ventas.flash.whatsapp_enviado'));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar ticket WhatsApp de venta', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);

            $msg = __('caja.ventas.flash.whatsapp_fallo');
            $detail = trim($e->getMessage());
            if ($detail !== '') {
                $msg .= ' '.$detail;
            }

            return back()->with('warning', $msg);
        }
    }

    public function store(StoreVentaRequest $request, VentaCheckoutService $checkout, TenantManager $tenants): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $venta = $checkout->registrar($request->validated(), $user, $tenants->current()?->tenant);

        return redirect()
            ->route('caja.ventas.show', ['venta' => $venta, 'imprimir' => 1])
            ->with('success', __('caja.ventas.flash.registrada', ['numero' => $venta->numero]));
    }

    /**
     * @param  Collection<int, array{id: string, label: string, doc: ?string}>  $propietarios
     * @return array<string, mixed>
     */
    public function storePropietarioRapido(PropietarioRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('ventas.create'), 403);

        $userId = Auth::id();
        $data = $this->hydratePropietarioUbigeo($request->validated());

        $propietario = Propietario::query()->create([
            ...$data,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        $label = $propietario->razon_social
            ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos])));

        return response()->json([
            'propietario' => [
                'id' => $propietario->id,
                'label' => $label !== '' ? $label : '—',
                'doc' => $propietario->numero_documento,
            ],
        ], 201);
    }

    /**
     * Alta rápida de un producto vendible con stock inicial, sin salir de la venta.
     *
     * El stock se registra en la sede de la sesión de caja abierta del cajero,
     * de modo que el producto queda inmediatamente disponible en el buscador.
     */
    public function storeProductoRapido(ProductoRapidoVentaRequest $request, InventarioLoteService $inventario): JsonResponse
    {
        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        if ($sesion === null) {
            return response()->json([
                'message' => __('caja.ventas.create.rapido_sin_sesion'),
            ], 422);
        }

        $userId = Auth::id();
        $data = $request->validated();
        $stockCantidad = (string) $data['stock_inicial'];

        $producto = DB::transaction(function () use ($data, $userId, $sesion, $stockCantidad, $inventario): Producto {
            $producto = Producto::query()->create([
                'categoria_id' => null,
                'nombre' => $data['nombre'],
                'slug' => $this->generarSlugProductoUnico((string) $data['nombre']),
                'descripcion' => null,
                'sku' => $data['sku'] ?? null,
                'codigo_barras' => null,
                'unidad' => $data['unidad'] ?? 'UN',
                'precio_venta' => $data['precio_venta'],
                'precio_compra' => null,
                'stock_minimo' => null,
                'medicamento' => (bool) ($data['medicamento'] ?? false),
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $inventario->registrarEntrada(
                (string) $producto->id,
                (string) $sesion->sede_id,
                $stockCantidad,
                $data['numero_lote'] ?? null,
                $data['fecha_vencimiento'] ?? null,
                'Stock inicial al registrar producto desde venta',
                $userId !== null ? (string) $userId : null,
            );

            return $producto;
        });

        return response()->json([
            'producto' => [
                'id' => (string) $producto->id,
                'nombre' => $producto->nombre,
                'sku' => $producto->sku,
                'precio_venta' => $producto->precio_venta !== null ? (string) $producto->precio_venta : null,
                'unidad' => $producto->unidad,
                'stock_sede' => (string) round((float) $stockCantidad, 3),
            ],
        ], 201);
    }

    /**
     * Alta rápida de un servicio en el catálogo editable (grooming personalizado),
     * para reutilizarlo en próximas ventas. Solo disponible cuando el tenant usa
     * catálogo personalizado y el usuario puede administrarlo.
     */
    public function storeServicioRapido(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('ventas.create') ?? false, 403);
        abort_unless($this->puedeCrearServicioCatalogo($request), 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $nombre = trim((string) $data['nombre']);
        $categoria = isset($data['categoria']) ? trim((string) $data['categoria']) : '';
        $maxOrden = (int) GroomingServicio::query()->max('orden');

        $servicio = GroomingServicio::query()->create([
            'nombre' => $nombre,
            'categoria' => $categoria === '' ? null : $categoria,
            'precio_lista' => $data['precio_lista'],
            'moneda' => 'PEN',
            'duracion_minutos' => (int) ($data['duracion_minutos'] ?? 60),
            'activo' => true,
            'orden' => $maxOrden + 1,
        ]);

        return response()->json([
            'servicio' => [
                'nombre' => $servicio->nombre,
                'precio_lista' => (string) $servicio->precio_lista,
            ],
        ], 201);
    }

    private function puedeCrearServicioCatalogo(Request $request): bool
    {
        if (! GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            return false;
        }

        $user = $request->user();

        return ($user?->can('grooming.create') ?? false)
            || ($user?->can('tarifas.create') ?? false);
    }

    private function generarSlugProductoUnico(string $nombre): ?string
    {
        $base = Str::slug($nombre);
        if ($base === '') {
            return null;
        }

        $base = mb_substr($base, 0, 150);
        $slug = $base;
        $i = 0;

        while (Producto::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = mb_substr($base.'-'.$i, 0, 160);
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydratePropietarioUbigeo(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }

    private function buildCreatePayload(
        ?CajaSesion $miSesion,
        ?string $sedeNombre,
        ClinicSetting $clinic,
        mixed $tenantModel,
        $propietarios,
    ): array {
        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $user = request()->user();
        $puedeCrearProducto = $user?->can('productos.create') ?? false;
        $puedeCrearServicio = GroomingCatalogoMode::usaCatalogoPersonalizado()
            && (($user?->can('grooming.create') ?? false) || ($user?->can('tarifas.create') ?? false));

        return [
            'puede_vender' => $miSesion !== null,
            'mi_sesion' => $miSesion === null ? null : [
                'id' => $miSesion->id,
                'sede_id' => $miSesion->sede_id,
                'sede_nombre' => $sedeNombre ?? '—',
                'moneda' => $miSesion->moneda,
            ],
            'clinica' => [
                'moneda' => $clinic->moneda,
                'igv_porcentaje' => (string) $clinic->igv_porcentaje,
                'precio_incluye_igv' => (bool) $clinic->precio_incluye_igv,
                'emite_comprobantes_sunat' => (bool) $clinic->emite_comprobantes_sunat,
                'plan_permite_boletas' => PlanCapabilities::boletasElectronicas($tenantModel),
                'plan_permite_facturas' => PlanCapabilities::facturasElectronicas($tenantModel),
            ],
            'propietarios_opciones' => $propietarios,
            'departamentos' => $departamentos,
            'puede_crear_producto' => $puedeCrearProducto,
            'puede_crear_servicio' => $puedeCrearServicio,
            'unidad_opciones' => UnidadMedidaOpciones::forProductoForm(),
        ];
    }

    public function pacientesPorPropietario(Request $request): JsonResponse
    {
        $request->validate([
            'propietario_id' => ['required', 'uuid', 'exists:propietarios,id'],
        ]);

        $rows = Paciente::query()
            ->where('propietario_id', $request->string('propietario_id'))
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(200)
            ->get(['id', 'nombre']);

        return response()->json(['data' => $rows]);
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $sedeId = $sesion?->sede_id;

        $like = '%'.addcslashes($q, '%_\\').'%';

        $query = Producto::query()
            ->where('productos.activo', true)
            ->whereNull('productos.deleted_at')
            ->where(function ($inner) use ($like): void {
                $inner->where('productos.nombre', 'ILIKE', $like)
                    ->orWhere('productos.sku', 'ILIKE', $like)
                    ->orWhere('productos.codigo_barras', 'ILIKE', $like);
            });

        if ($sedeId !== null) {
            $productos = (clone $query)
                ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                    $join->on('es.producto_id', '=', 'productos.id')
                        ->where('es.sede_id', '=', $sedeId);
                })
                ->orderBy('productos.nombre')
                ->limit(40)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.precio_venta',
                    'productos.unidad',
                    DB::raw('COALESCE(es.cantidad, 0) as stock_sede'),
                ]);
        } else {
            $productos = $query
                ->orderBy('productos.nombre')
                ->limit(40)
                ->get(['productos.id', 'productos.nombre', 'productos.sku', 'productos.precio_venta', 'productos.unidad']);

            foreach ($productos as $p) {
                $p->setAttribute('stock_sede', '0');
            }
        }

        return response()->json([
            'data' => $productos->map(fn (Producto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                'unidad' => $p->unidad,
                'stock_sede' => (string) ($p->stock_sede ?? '0'),
            ]),
        ]);
    }

    /**
     * Tarifas de servicios (grooming) para sugerir precio en POS libre.
     */
    public function buscarServiciosTarifa(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q', ''));

        if (GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            $query = GroomingServicio::query()->where('activo', true);

            if ($q !== '') {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($sub) use ($like): void {
                    $sub->where('nombre', 'ILIKE', $like)
                        ->orWhere('categoria', 'ILIKE', $like);
                });
            }

            $rows = $query
                ->orderBy('orden')
                ->orderBy('nombre')
                ->limit(30)
                ->get(['nombre', 'precio_lista']);

            return response()->json([
                'data' => $rows->map(static fn (GroomingServicio $row): array => [
                    'nombre' => $row->nombre,
                    'precio_lista' => (string) $row->precio_lista,
                ]),
            ]);
        }

        $query = GroomingServicioTarifa::query()->where('activo', true);

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('servicio', 'ILIKE', $like);
        }

        $rows = $query
            ->orderBy('servicio')
            ->limit(30)
            ->get(['servicio', 'precio_lista']);

        return response()->json([
            'data' => $rows->map(static fn (GroomingServicioTarifa $row): array => [
                'nombre' => $row->servicio,
                'precio_lista' => (string) $row->precio_lista,
            ]),
        ]);
    }
}
