<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\PropietarioRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\FelSerie;
use App\Services\Venta\VentaAnulacionService;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\GroomingServicioTarifa;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Venta;
use App\Services\Fel\FelEmisionVentaService;
use App\Services\Venta\VentaCheckoutService;
use App\Support\Caja\VentaTicketPolicy;
use App\Support\Fel\FelReceptorResolver;
use App\Support\Fel\FelSerieResolver;
use App\Support\PlanCapabilities;
use App\Support\Venta\VentaDesdeCargoPrefill;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'created_at',
        'numero',
        'total',
        'estado',
    ];

    public function index(Request $request): Response
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

        $query = Venta::query()
            ->with([
                'propietario:id,nombres,apellidos,razon_social',
                'paciente:id,nombre',
                'creadoPor:id,name',
            ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like, $search): void {
                $q->where('numero', 'ILIKE', $like)
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

        if ($estado !== 'todas') {
            $query->where('estado', $estado);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionSql);
            if ($sort !== 'created_at') {
                $query->orderByDesc('created_at');
            }
        } else {
            $query->orderByDesc('created_at');
        }

        $ventas = $query->paginate($perPage)->withQueryString();

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
                'estado' => $v->estado,
                'moneda' => $v->moneda,
                'total' => (string) $v->total,
                'subtotal' => (string) $v->subtotal,
                'igv_monto' => (string) $v->igv_monto,
                'metodo_pago' => $v->metodo_pago,
                'fel_estado' => $v->fel_estado,
                'created_at' => $v->created_at?->toIso8601String(),
                'cliente' => $nombreCliente,
                'paciente' => $v->paciente?->nombre,
                'cajero' => $v->creadoPor?->name ?? '—',
                'sede' => $sedeNombres[$v->sede_id] ?? '—',
            ];
        });

        return Inertia::render('caja/ventas/index', [
            'ventas' => $ventas,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Venta::query()->count(),
                'coincidencias' => $ventas->total(),
            ],
        ]);
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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

        try {
            $desdeCargo = $prefill->buildFromHotelEstancia($hotelEstancia);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            'sede:id,serie_boleta,serie_factura,activa',
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
                'ticket_ancho_mm' => in_array((string) $clinic->ticket_ancho_mm, ['58', '80'], true)
                    ? (string) $clinic->ticket_ancho_mm
                    : '80',
                'emite_comprobantes_sunat' => (bool) $clinic->emite_comprobantes_sunat,
                'apisunat_configurado' => \App\Support\Fel\ApisunatCredentialResolver::estaConfigurado($clinic),
                'plan_permite_boletas'   => PlanCapabilities::boletasElectronicas($tenantModel),
                'plan_permite_facturas'  => PlanCapabilities::facturasElectronicas($tenantModel),
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
            ->route('caja.ventas.show', $venta)
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
    public function ticket(Request $request, Venta $venta): View
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

        $venta->load([
            'felDocument',
            'lineas' => fn ($q) => $q->orderBy('id'),
            'propietario:id,nombres,apellidos,razon_social,numero_documento',
            'paciente:id,nombre',
            'creadoPor:id,name',
        ]);

        $ancho = in_array((string) $cfg->ticket_ancho_mm, ['58', '80'], true)
            ? (string) $cfg->ticket_ancho_mm
            : '80';

        $propietario = $venta->propietario;
        $clienteNombre = $propietario === null
            ? '—'
            : ($propietario->razon_social ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos]))));

        $sedeNombre = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($venta->sede_id)
            ->value('nombre');

        $metodoPagoLabel = $venta->metodo_pago !== null
            ? __('caja.ventas.ticket.metodo_'.$venta->metodo_pago)
            : null;

        $lineas = $venta->lineas->map(fn ($ln): array => [
            'descripcion' => $ln->descripcion_snapshot,
            'cantidad' => (string) $ln->cantidad,
            'subtotal' => (string) $ln->subtotal,
        ])->values()->all();

        $clinicNombre = $cfg->nombre_comercial ?: $cfg->razon_social ?: config('app.name');
        $trim = static function (?string $v): ?string {
            if ($v === null) {
                return null;
            }
            $t = trim($v);

            return $t === '' ? null : $t;
        };

        $fechaCobro = ($venta->fecha_pago ?? $venta->created_at)
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? '—';

        return view('caja.venta-ticket', [
            'ancho_mm' => $ancho,
            'clinic_logo_url' => $cfg->logo_url,
            'clinic_nombre' => $clinicNombre,
            'clinic_ruc' => $trim($cfg->ruc),
            'clinic_direccion' => $trim($cfg->direccion_fiscal),
            'clinic_telefono' => $trim($cfg->telefono_principal),
            'moneda' => $venta->moneda,
            'igv_porcentaje' => (string) $cfg->igv_porcentaje,
            'venta' => $venta,
            'lineas' => $lineas,
            'fecha_cobro' => $fechaCobro,
            'sede_nombre' => $sedeNombre,
            'cliente_nombre' => $clienteNombre,
            'cliente_doc' => $propietario?->numero_documento,
            'paciente_nombre' => $venta->paciente?->nombre,
            'cajero_nombre' => $venta->creadoPor?->name,
            'metodo_pago_label' => $metodoPagoLabel,
            'cpe_numero' => $venta->felDocument?->numero_completo,
            'auto_print' => $request->boolean('print'),
        ]);
    }

    public function store(StoreVentaRequest $request, VentaCheckoutService $checkout, TenantManager $tenants): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $venta = $checkout->registrar($request->validated(), $user, $tenants->current()?->tenant);

        return redirect()
            ->route('caja.ventas.show', $venta)
            ->with('success', __('caja.ventas.flash.registrada', ['numero' => $venta->numero]));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{id: string, label: string, doc: ?string}>  $propietarios
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
                'plan_permite_boletas'    => PlanCapabilities::boletasElectronicas($tenantModel),
                'plan_permite_facturas'   => PlanCapabilities::facturasElectronicas($tenantModel),
            ],
            'propietarios_opciones' => $propietarios,
            'departamentos' => $departamentos,
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
