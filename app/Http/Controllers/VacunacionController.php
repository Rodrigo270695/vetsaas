<?php

namespace App\Http\Controllers;

use App\Exports\VacunasAplicadasImportTemplateXlsx;
use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\StoreVacunaAplicadaRequest;
use App\Http\Requests\UpdateVacunaAplicadaRequest;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\MovimientoInventario;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\ServicioClinico;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Services\Clinica\VacunaAplicadaImportService;
use App\Services\Clinica\VacunaProximaCitaSync;
use App\Support\Pdf\HistorialClinicoPdfBuilder;
use App\Support\Vacunas\VacunaAplicadaStockSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VacunacionController extends Controller
{
    use ResolvesClinicPdfBranding;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'aplicada_at',
        'paciente',
        'nombre_vacuna',
        'created_at',
    ];

    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $sinRangoExplicit = $request->boolean('sin_rango');
        // Al buscar, no acotar por mes: si no, registros de otros años “desaparecen”.
        $omitirRango = $sinRangoExplicit || $search !== '';

        $aplicadaDesde = $this->parseDateParam($request->query('aplicada_desde'));
        $aplicadaHasta = $this->parseDateParam($request->query('aplicada_hasta'));

        if ($omitirRango) {
            $aplicadaDesde = null;
            $aplicadaHasta = null;
            $fueraDelMesActual = true;
            $inicioRango = null;
            $finRango = null;
        } elseif ($aplicadaDesde === null || $aplicadaHasta === null) {
            $aplicadaDesde = $defaultDesde;
            $aplicadaHasta = $defaultHasta;
            $fueraDelMesActual = false;
            $inicioRango = Carbon::parse($aplicadaDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($aplicadaHasta, $tz)->endOfDay();
        } else {
            if ($aplicadaDesde > $aplicadaHasta) {
                [$aplicadaDesde, $aplicadaHasta] = [$aplicadaHasta, $aplicadaDesde];
            }
            $fueraDelMesActual = ($aplicadaDesde !== $defaultDesde) || ($aplicadaHasta !== $defaultHasta);
            $inicioRango = Carbon::parse($aplicadaDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($aplicadaHasta, $tz)->endOfDay();
        }

        $vacunaAbrirEditar = null;
        $editarVacunaRaw = $request->query('editar_vacuna_aplicada');
        if (is_string($editarVacunaRaw) && Str::isUuid($editarVacunaRaw) && ($request->user()?->can('vacunaciones.update') ?? false)) {
            $canAuditVac = $request->user()?->can('audit-trail.view') ?? false;
            $vacEditQuery = VacunaAplicada::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'producto:id,nombre,sku',
                    'servicioClinico:id,nombre,precio_lista',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                    'consulta:id,atendido_at,cerrada_at',
                    'cargo:id,vacuna_aplicada_id,estado,venta_id,total',
                ])
                ->whereKey($editarVacunaRaw);

            if (Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id')) {
                $vacEditQuery->with(['citaProxima:id,inicio_at,duracion_minutos,motivo,estado']);
            }

            if ($canAuditVac) {
                $vacEditQuery->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $vacModel = $vacEditQuery->first();

            if ($vacModel !== null) {
                $vacunaAbrirEditar = $vacModel;
                $atVac = $vacModel->aplicada_at->copy()->timezone($tz);
                $aplicadaDesde = $atVac->copy()->startOfMonth()->toDateString();
                $aplicadaHasta = $atVac->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($aplicadaDesde !== $defaultDesde) || ($aplicadaHasta !== $defaultHasta);
                $inicioRango = Carbon::parse($aplicadaDesde, $tz)->startOfDay();
                $finRango = Carbon::parse($aplicadaHasta, $tz)->endOfDay();
                $omitirRango = false;
            }
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $withVacuna = [
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'producto:id,nombre,sku',
            'servicioClinico:id,nombre,precio_lista',
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'consulta:id,atendido_at,cerrada_at',
        ];
        if (Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id')) {
            $withVacuna[] = 'citaProxima:id,inicio_at,duracion_minutos,motivo,estado';
        }
        if (Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')) {
            $withVacuna[] = 'cargo:id,vacuna_aplicada_id,estado,venta_id,total';
        }

        $query = VacunaAplicada::query()->with($withVacuna);

        if (Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')) {
            \App\Support\ConsultaCargo\ConsultaCargoCobroEstado::withCobradosCount($query);
        }

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($inicioRango !== null && $finRango !== null) {
            $query->whereBetween('vacunas_aplicadas.aplicada_at', [$inicioRango, $finRango]);
        }
        $cobroFiltro = strtolower(trim((string) $request->string('cobro', 'todos')));
        if (! in_array($cobroFiltro, \App\Support\ConsultaCargo\ConsultaCargoCobroEstado::FILTERS, true)) {
            $cobroFiltro = \App\Support\ConsultaCargo\ConsultaCargoCobroEstado::FILTER_TODOS;
        }
        if (Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')) {
            \App\Support\ConsultaCargo\ConsultaCargoCobroEstado::applyListFilter(
                $query,
                $cobroFiltro,
            );
        }

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as vac_pac', 'vac_pac.id', '=', 'vacunas_aplicadas.paciente_id')
                ->orderBy('vac_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('vacunas_aplicadas.aplicada_at')
                ->select('vacunas_aplicadas.*');
        } elseif ($sortValid) {
            $query->orderBy('vacunas_aplicadas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'aplicada_at') {
                $query->orderByDesc('vacunas_aplicadas.aplicada_at');
            }
        } else {
            $query->orderByDesc('vacunas_aplicadas.aplicada_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('vacunas_aplicadas.nombre_vacuna', 'ILIKE', "%{$search}%")
                    ->orWhere('vacunas_aplicadas.lote', 'ILIKE', "%{$search}%")
                    ->orWhere('vacunas_aplicadas.esquema_antigenos', 'ILIKE', "%{$search}%")
                    ->orWhereHas('paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    })
                    ->orWhereHas('producto', function ($q4) use ($search) {
                        $q4->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhere('sku', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $vacunas = $query->paginate($perPage)->withQueryString()->through(function (VacunaAplicada $v): array {
            $cargo = $v->cargo;

            return [
                'id' => $v->id,
                'paciente_id' => $v->paciente_id,
                'consulta_id' => $v->consulta_id,
                'producto_id' => $v->producto_id,
                'servicio_clinico_id' => $v->servicio_clinico_id,
                'nombre_vacuna' => $v->nombre_vacuna,
                'categoria_registro' => $v->categoria_registro,
                'esquema_antigenos' => $v->esquema_antigenos,
                'fecha_proxima_sugerida' => $v->fecha_proxima_sugerida?->toDateString(),
                'cita_proxima_id' => Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id')
                    ? $v->cita_proxima_id
                    : null,
                'cita_proxima' => Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id') && $v->citaProxima !== null
                    ? [
                        'id' => $v->citaProxima->id,
                        'inicio_at' => $v->citaProxima->inicio_at?->toIso8601String(),
                        'duracion_minutos' => $v->citaProxima->duracion_minutos,
                        'motivo' => $v->citaProxima->motivo,
                        'estado' => $v->citaProxima->estado,
                    ]
                    : null,
                'aplicada_at' => $v->aplicada_at?->toIso8601String(),
                'numero_dosis' => $v->numero_dosis,
                'lote' => $v->lote,
                'notas' => $v->notas,
                'veterinario_id' => $v->veterinario_id,
                'sede_id' => $v->sede_id,
                'created_at' => $v->created_at?->toIso8601String(),
                'updated_at' => $v->updated_at?->toIso8601String(),
                'paciente' => $v->paciente,
                'producto' => $v->producto,
                'servicio_clinico' => $v->servicioClinico,
                'veterinario' => $v->veterinario,
                'sede' => $v->sede,
                'consulta' => $v->consulta,
                'creado_por' => $v->relationLoaded('creadoPor') ? $v->creadoPor : null,
                'actualizado_por' => $v->relationLoaded('actualizadoPor') ? $v->actualizadoPor : null,
                'cargo' => $cargo === null ? null : [
                    'id' => $cargo->id,
                    'estado' => $cargo->estado,
                    'venta_id' => $cargo->venta_id,
                    'total' => (string) $cargo->total,
                ],
                'estado_cobro' => Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')
                    ? $v->estadoCobro()
                    : 'sin_precuenta',
                'url_cobrar' => Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')
                    ? $v->urlCobrarEnCaja()
                    : null,
                'url_cargos' => Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')
                    ? route('clinica.vacunaciones.cargos.show', ['vacuna_aplicada' => $v], absolute: false)
                    : null,
            ];
        });

        $totalEnRango = VacunaAplicada::query()
            ->when(
                $inicioRango !== null && $finRango !== null,
                static fn ($q) => $q->whereBetween('aplicada_at', [$inicioRango, $finRango]),
            )
            ->count();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

        $tenantId = tenant_id();
        $usuariosOpciones = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $sedesOpciones = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo']);

        $serviciosVacunaOpciones = [];
        if (Schema::hasTable('servicios_clinicos')) {
            $serviciosQuery = ServicioClinico::query()
                ->where('activo', true)
                ->with('categoria:id,nombre')
                ->orderBy('orden')
                ->orderBy('nombre');

            if (Schema::hasTable('servicio_clinico_productos')) {
                $serviciosQuery->withCount('productosPaquete as productos_count');
            }

            $serviciosVacunaOpciones = $serviciosQuery
                ->get(['id', 'nombre', 'precio_lista', 'categoria_id'])
                ->map(static function (ServicioClinico $s): array {
                    return [
                        'id' => $s->id,
                        'nombre' => $s->nombre,
                        'precio_lista' => (string) $s->precio_lista,
                        'categoria' => $s->categoria?->nombre,
                        'productos_count' => (int) ($s->productos_count ?? 0),
                    ];
                })
                ->all();
        }

        $vacunaPrefill = $this->vacunaPrefillDesdeQuery($request);

        return Inertia::render('clinica/vacunaciones/index', [
            'vacunas' => $vacunas,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'servicios_vacuna_opciones' => $serviciosVacunaOpciones,
            'vacuna_prefill' => $vacunaPrefill,
            'vacuna_abrir_editar' => $vacunaAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'aplicada_desde' => $aplicadaDesde,
                'aplicada_hasta' => $aplicadaHasta,
                'sin_rango' => $omitirRango && $search === '',
                'cobro' => $cobroFiltro,
            ],
            'aplicacion_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $vacunas->total(),
            ],
        ]);
    }

    public function productosVacuna(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = addcslashes(mb_strtolower($q, 'UTF-8'), '%_\\');
                $term = '%'.$escaped.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereRaw('LOWER(nombre) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term]);
                });
            })
            ->orderBy('nombre')
            ->limit(25)
            ->get(['id', 'nombre', 'sku', 'unidad']);

        return response()->json(['data' => $items]);
    }

    /**
     * PDF del carnet de vacunación de un paciente (branding desde configuración general).
     *
     * Por defecto: stream con visualización en el navegador (p. ej. nueva pestaña e imprimir).
     * Query `?download=1`: forzar descarga del archivo (p. ej. adjunto por WhatsApp).
     */
    public function carnetPdf(Request $request, Paciente $paciente): Response
    {
        abort_unless($request->user()?->can('vacunaciones.view') ?? false, 403);

        $paciente->load(['propietario:id,nombres,apellidos,razon_social']);

        $vacunas = VacunaAplicada::query()
            ->where('paciente_id', $paciente->id)
            ->with(['veterinario:id,name', 'sede:id,nombre,codigo'])
            ->orderByDesc('aplicada_at')
            ->get();

        $clinic = ClinicSetting::current();
        $logoDataUri = $this->clinicLogoDataUri($clinic);
        $colorPrimario = $this->sanitizeHexColor($clinic->color_primario, '#166534');
        $colorSecundario = $this->sanitizeHexColor($clinic->color_secundario, '#f0fdf4');

        $clinicNombre = $clinic->nombre_comercial
            ?: $clinic->razon_social
            ?: (string) config('app.name', 'Clínica');

        $propietarioNombre = $this->propietarioNombreParaPdf($paciente);
        $tz = (string) config('app.timezone', 'UTC');

        $vacunasRows = $vacunas->map(function (VacunaAplicada $v) use ($tz): array {
            $aplicada = '—';
            if ($v->aplicada_at !== null) {
                $aplicada = Carbon::parse($v->aplicada_at)->timezone($tz)->format('d/m/Y H:i');
            }

            $proxima = '—';
            if ($v->fecha_proxima_sugerida !== null) {
                $proxima = Carbon::parse($v->fecha_proxima_sugerida)->format('d/m/Y');
            }

            $esquema = $v->esquema_antigenos !== null && trim((string) $v->esquema_antigenos) !== ''
                ? trim((string) $v->esquema_antigenos)
                : '—';

            $categoriaLabel = match ($v->categoria_registro) {
                VacunaAplicada::CATEGORIA_DESPARASITACION => __('carnet_vacunacion.categoria_desparasitacion'),
                VacunaAplicada::CATEGORIA_OTRO => __('carnet_vacunacion.categoria_otro'),
                VacunaAplicada::CATEGORIA_VACUNA => __('carnet_vacunacion.categoria_vacuna'),
                default => __('carnet_vacunacion.categoria_vacuna'),
            };

            $sedeTxt = null;
            if ($v->sede !== null) {
                $sedeTxt = $v->sede->nombre;
                if ($v->sede->codigo) {
                    $sedeTxt .= ' ('.$v->sede->codigo.')';
                }
            }

            return [
                'aplicada_at' => $aplicada,
                'categoria_label' => $categoriaLabel,
                'nombre_vacuna' => $v->nombre_vacuna,
                'esquema_antigenos' => $esquema,
                'numero_dosis' => $v->numero_dosis,
                'fecha_proxima_sugerida' => $proxima,
                'lote' => $v->lote,
                'veterinario' => $v->veterinario?->name,
                'sede' => $sedeTxt,
            ];
        });

        $generadoEn = now($tz)->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.carnet-vacunacion', [
            'clinicNombre' => $clinicNombre,
            'logoDataUri' => $logoDataUri,
            'colorPrimario' => $colorPrimario,
            'colorSecundario' => $colorSecundario,
            'clinicEmail' => $clinic->email_institucional,
            'clinicTelefono' => $clinic->telefono_principal,
            'clinicWeb' => $clinic->web_url,
            'clinicDireccion' => $clinic->direccion_fiscal,
            'paciente' => $paciente,
            'propietarioNombre' => $propietarioNombre,
            'vacunas' => $vacunasRows,
            'generadoEn' => $generadoEn,
            'vacunasCount' => $vacunas->count(),
        ]);
        $pdf->setPaper('a4', 'portrait');

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'carnet-vacunas-'.$slug.'.pdf';

        // Por defecto: inline en el navegador (nueva pestaña, imprimir sin forzar descarga).
        // ?download=1 para adjuntar como descarga (p. ej. envío por WhatsApp en el futuro).
        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function aplicacionPdf(Request $request, VacunaAplicada $vacuna_aplicada): Response
    {
        abort_unless($request->user()?->can('vacunaciones.view') ?? false, 403);

        return $this->renderAplicacionPdf($request, $vacuna_aplicada);
    }

    public function publicAplicacionPdf(Request $request, VacunaAplicada $vacuna_aplicada): Response
    {
        return $this->renderAplicacionPdf($request, $vacuna_aplicada);
    }

    private function renderAplicacionPdf(Request $request, VacunaAplicada $vacuna_aplicada): Response
    {
        $vacuna_aplicada->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'producto:id,nombre,sku',
        ]);

        $paciente = $vacuna_aplicada->paciente;
        abort_if($paciente === null, 404);

        $entry = HistorialClinicoPdfBuilder::make()->fromAplicacion($vacuna_aplicada);

        $pdf = Pdf::loadView('pdf.aplicacion-clinica', array_merge(
            $this->clinicPdfBranding(),
            [
                'paciente' => $paciente,
                'propietarioNombre' => $this->propietarioNombreParaPdf($paciente),
                'entry' => $entry,
            ],
        ));

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'aplicacion-'.$slug.'-'.Str::substr($vacuna_aplicada->id, 0, 8).'.pdf';

        return $this->respondClinicPdf($request, $pdf, $filename);
    }

    public function store(StoreVacunaAplicadaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['nombre_vacuna'] = Str::limit(trim($data['nombre_vacuna']), 500, '');
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        $proxima = [
            'proxima_servicio_clinico_id' => $data['proxima_servicio_clinico_id'] ?? null,
            'proxima_inicio_at' => $data['proxima_inicio_at'] ?? null,
            'proxima_duracion_minutos' => $data['proxima_duracion_minutos'] ?? 30,
        ];
        unset(
            $data['proxima_servicio_clinico_id'],
            $data['proxima_inicio_at'],
            $data['proxima_duracion_minutos'],
        );

        try {
            /** @var VacunaAplicada|null $created */
            $created = null;
            DB::transaction(function () use ($data, &$created): void {
                /** @var VacunaAplicada $vacuna */
                $vacuna = VacunaAplicada::query()->create($data);

                if (VacunaAplicadaStockSync::debeDescontar($vacuna)) {
                    $mov = VacunaAplicadaStockSync::registrarSalida($vacuna, Auth::id() !== null ? (string) Auth::id() : null);
                    $vacuna->forceFill(['movimiento_inventario_id' => $mov->id])->save();
                }

                $created = $vacuna;
            });

            if ($created !== null) {
                app(VacunaProximaCitaSync::class)->sync($created, $proxima);
            }
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.insufficient_stock')])
                    ->withInput();
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()
            ->back()
            ->with('success', __('vacunaciones.flash.created'));
    }

    public function update(
        UpdateVacunaAplicadaRequest $request,
        VacunaAplicada $vacuna_aplicada,
    ): RedirectResponse {
        $data = $request->validated();
        $data['nombre_vacuna'] = Str::limit(trim($data['nombre_vacuna']), 500, '');
        $data['updated_by_id'] = Auth::id();

        $proxima = [
            'proxima_servicio_clinico_id' => $data['proxima_servicio_clinico_id'] ?? null,
            'proxima_inicio_at' => $data['proxima_inicio_at'] ?? null,
            'proxima_duracion_minutos' => $data['proxima_duracion_minutos'] ?? 30,
        ];
        unset(
            $data['proxima_servicio_clinico_id'],
            $data['proxima_inicio_at'],
            $data['proxima_duracion_minutos'],
        );

        try {
            DB::transaction(function () use ($vacuna_aplicada, $data): void {
                $movAnterior = null;
                if ($vacuna_aplicada->movimiento_inventario_id !== null) {
                    $movAnterior = MovimientoInventario::query()->find($vacuna_aplicada->movimiento_inventario_id);
                    if ($movAnterior !== null) {
                        VacunaAplicadaStockSync::revertirPorMovimiento(
                            $movAnterior,
                            Auth::id() !== null ? (string) Auth::id() : null,
                        );
                    }
                }

                $vacuna_aplicada->fill($data);
                $vacuna_aplicada->movimiento_inventario_id = null;
                $vacuna_aplicada->save();

                if (VacunaAplicadaStockSync::debeDescontar($vacuna_aplicada)) {
                    $mov = VacunaAplicadaStockSync::registrarSalida(
                        $vacuna_aplicada,
                        Auth::id() !== null ? (string) Auth::id() : null,
                    );
                    $vacuna_aplicada->forceFill(['movimiento_inventario_id' => $mov->id])->save();
                }
            });

            app(VacunaProximaCitaSync::class)->sync($vacuna_aplicada->fresh() ?? $vacuna_aplicada, $proxima);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.insufficient_stock')])
                    ->withInput();
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()
            ->back()
            ->with('success', __('vacunaciones.flash.updated'));
    }

    public function destroy(Request $request, VacunaAplicada $vacuna_aplicada): RedirectResponse
    {
        abort_unless($request->user()?->can('vacunaciones.delete') ?? false, 403);

        try {
            DB::transaction(function () use ($vacuna_aplicada): void {
                if ($vacuna_aplicada->movimiento_inventario_id !== null) {
                    $mov = MovimientoInventario::query()->find($vacuna_aplicada->movimiento_inventario_id);
                    if ($mov !== null) {
                        VacunaAplicadaStockSync::revertirPorMovimiento(
                            $mov,
                            Auth::id() !== null ? (string) Auth::id() : null,
                        );
                    }
                    $vacuna_aplicada->forceFill(['movimiento_inventario_id' => null])->save();
                }

                $vacuna_aplicada->delete();
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.revert_failed')]);
            }

            return redirect()
                ->back()
                ->withErrors($errors);
        }

        return redirect()
            ->route('clinica.vacunaciones.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'aplicada_desde', 'aplicada_hasta',
            ]))
            ->with('success', __('vacunaciones.flash.deleted'));
    }

    /**
     * @return array{paciente_id: string, consulta_id: string|null}|null
     */
    private function vacunaPrefillDesdeQuery(Request $request): ?array
    {
        if (! $request->user()?->can('vacunaciones.create')) {
            return null;
        }

        $pid = $request->query('prefill_paciente_id');
        if (! is_string($pid) || ! Str::isUuid($pid)) {
            return null;
        }

        if (! Paciente::query()->whereKey($pid)->where('activo', true)->exists()) {
            return null;
        }

        $out = [
            'paciente_id' => $pid,
            'consulta_id' => null,
        ];

        $cid = $request->query('prefill_consulta_id');
        if (! is_string($cid) || ! Str::isUuid($cid)) {
            return $out;
        }

        $consulta = Consulta::query()
            ->with('historiaClinica:id,paciente_id')
            ->find($cid);

        if ($consulta === null || $consulta->historiaClinica === null) {
            return $out;
        }

        if ((string) $consulta->historiaClinica->paciente_id !== $pid || $consulta->cerrada_at !== null) {
            return $out;
        }

        $out['consulta_id'] = $cid;

        return $out;
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('vacunaciones.create') ?? false, 403);

        $filename = 'plantilla_vacunaciones_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function (): void {
            (new VacunasAplicadasImportTemplateXlsx)->streamTo('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request, VacunaAplicadaImportService $importService): JsonResponse
    {
        abort_unless($request->user()?->can('vacunaciones.create') ?? false, 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return response()->json([
                'ok' => false,
                'error' => 'No se recibió el archivo.',
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
            ], 422);
        }

        $extension = strtolower($uploaded->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'El archivo debe ser .xlsx',
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
            ], 422);
        }

        $result = $importService->import($uploaded);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
