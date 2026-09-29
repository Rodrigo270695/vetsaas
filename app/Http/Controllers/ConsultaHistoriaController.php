<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\StoreConsultaHistoriaRequest;
use App\Http\Requests\UpdateConsultaHistoriaRequest;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\ConsultaExamen;
use App\Models\ConsultaResultado;
use App\Models\ConsultaTerapiaLinea;
use App\Models\Farmaco;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\ServicioClinico;
use App\Support\ConsultaCargo\ConsultaCargoCobroEstado;
use App\Support\Pdf\HistorialClinicoPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ConsultaHistoriaController extends Controller
{
    use ResolvesClinicPdfBranding;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'atendido_at',
        'created_at',
        'paciente',
    ];

    public function index(Request $request): Response
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

        $soloAbiertas = filter_var($request->query('solo_abiertas'), FILTER_VALIDATE_BOOLEAN);
        $estadoRaw = $request->query('estado');
        $estado = in_array($estadoRaw, ['abierta', 'cerrada', 'todas'], true) ? $estadoRaw : 'todas';
        if ($soloAbiertas) {
            $estado = 'abierta';
        }

        $filtrarAbiertas = $estado === 'abierta';
        $filtrarCerradas = $estado === 'cerrada';
        $fechasExplicitas = $request->has('atendido_desde') || $request->has('atendido_hasta');
        $omitirRangoMes = $filtrarAbiertas && ! $fechasExplicitas;

        $atendidoDesde = $this->parseDateParam($request->query('atendido_desde'));
        $atendidoHasta = $this->parseDateParam($request->query('atendido_hasta'));

        if ($omitirRangoMes) {
            $atendidoDesde = null;
            $atendidoHasta = null;
            $atencionFueraDelMesActual = false;
            $inicioRango = null;
            $finRango = null;
        } elseif ($atendidoDesde === null || $atendidoHasta === null) {
            $atendidoDesde = $defaultDesde;
            $atendidoHasta = $defaultHasta;
            $atencionFueraDelMesActual = false;
            $inicioRango = Carbon::parse($atendidoDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($atendidoHasta, $tz)->endOfDay();
        } else {
            if ($atendidoDesde > $atendidoHasta) {
                [$atendidoDesde, $atendidoHasta] = [$atendidoHasta, $atendidoDesde];
            }
            $atencionFueraDelMesActual = ($atendidoDesde !== $defaultDesde) || ($atendidoHasta !== $defaultHasta);
            $inicioRango = Carbon::parse($atendidoDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($atendidoHasta, $tz)->endOfDay();
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Consulta::query()
            ->with([
                'historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                'historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                'veterinario:id,name',
                'cerradaPor:id,name',
                'planTratamiento.lineas.producto:id,nombre,unidad,sku',
                'cargo:id,consulta_id,estado,total,venta_id',
                'examenes',
                'terapiaLineas',
                ...$this->withResultados(),
            ]);

        ConsultaCargoCobroEstado::withCobradosCount($query);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($inicioRango !== null && $finRango !== null) {
            $query->whereBetween('consultas.atendido_at', [$inicioRango, $finRango]);
        }

        if ($filtrarAbiertas) {
            $query->whereNull('consultas.cerrada_at');
        } elseif ($filtrarCerradas) {
            $query->whereNotNull('consultas.cerrada_at');
        }

        $cobroFiltro = strtolower(trim((string) $request->string('cobro', 'todos')));
        if (! in_array($cobroFiltro, ConsultaCargoCobroEstado::FILTERS, true)) {
            $cobroFiltro = ConsultaCargoCobroEstado::FILTER_TODOS;
        }
        ConsultaCargoCobroEstado::applyListFilter($query, $cobroFiltro);

        if ($sort === 'paciente') {
            $query
                ->join('historias_clinicas as hc_p', 'hc_p.id', '=', 'consultas.historia_clinica_id')
                ->join('pacientes as pac_p', 'pac_p.id', '=', 'hc_p.paciente_id')
                ->orderBy('pac_p.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('consultas.atendido_at')
                ->select('consultas.*');
        } elseif ($sortValid) {
            $query->orderBy('consultas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'atendido_at') {
                $query->orderByDesc('consultas.atendido_at');
            }
        } elseif ($filtrarAbiertas) {
            $query->orderBy('consultas.atendido_at', 'asc');
        } elseif ($filtrarCerradas) {
            $query->orderByDesc('consultas.atendido_at');
        } else {
            // Todas: abiertas primero (fecha ASC), luego cerradas (fecha DESC).
            $query
                ->orderByRaw('CASE WHEN consultas.cerrada_at IS NULL THEN 0 ELSE 1 END ASC')
                ->orderByRaw('CASE WHEN consultas.cerrada_at IS NULL THEN consultas.atendido_at END ASC')
                ->orderByRaw('CASE WHEN consultas.cerrada_at IS NOT NULL THEN consultas.atendido_at END DESC');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('consultas.motivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.anotaciones', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.subjetivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.objetivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.analisis', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.plan', 'ILIKE', "%{$search}%")
                    ->orWhereHas('historiaClinica.paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    });
            });
        }

        $consultas = $query->paginate($perPage)->withQueryString()->through(function (Consulta $consulta): array {
            $row = $consulta->toArray();
            $row['estado_cobro'] = $consulta->estadoCobro();

            return $row;
        });

        $consultaAbrirEditar = $this->consultaParaAbrirEnModal($request, $canAudit);
        $pacientePrefillNuevaConsulta = $this->pacientePrefillNuevaConsultaDesdeQuery($request);

        $limiteConsultaAntigua = $now->copy()->subHours(24);

        $abiertasTotal = Consulta::query()
            ->whereNull('cerrada_at')
            ->count();

        $abiertasAntiguas = Consulta::query()
            ->whereNull('cerrada_at')
            ->where('atendido_at', '<', $limiteConsultaAntigua)
            ->count();

        if ($omitirRangoMes) {
            $totalEnRango = $abiertasTotal;
        } elseif ($inicioRango !== null && $finRango !== null) {
            $totalEnRangoQuery = Consulta::query()
                ->whereBetween('consultas.atendido_at', [$inicioRango, $finRango]);

            if ($filtrarAbiertas) {
                $totalEnRangoQuery->whereNull('cerrada_at');
            } elseif ($filtrarCerradas) {
                $totalEnRangoQuery->whereNotNull('cerrada_at');
            }

            $totalEnRango = $totalEnRangoQuery->count();
        } else {
            $totalEnRango = 0;
        }

        $pacientesOpciones = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

        $serviciosClinicosOpciones = ServicioClinico::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre']);

        $farmacosOpciones = Farmaco::query()
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre']);

        return Inertia::render('clinica/historias-clinicas/index', [
            'consultas' => $consultas,
            'consulta_abrir_editar' => $consultaAbrirEditar,
            'paciente_prefill_nueva_consulta' => $pacientePrefillNuevaConsulta,
            'pacientes_opciones' => $pacientesOpciones,
            'servicios_clinicos_opciones' => $serviciosClinicosOpciones,
            'farmacos_opciones' => $farmacosOpciones,
            'medico_tratante_default' => $request->user()?->name ?? '',
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'atendido_desde' => $atendidoDesde,
                'atendido_hasta' => $atendidoHasta,
                'estado' => $estado,
                'solo_abiertas' => $filtrarAbiertas,
                'cobro' => $cobroFiltro,
            ],
            'atencion_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $atencionFueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $consultas->total(),
                'abiertas_total' => $abiertasTotal,
                'abiertas_antiguas' => $abiertasAntiguas,
            ],
        ]);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Payload JSON para abrir el modal de edición sin salir del historial del paciente.
     */
    public function formJson(Request $request, Consulta $consulta): JsonResponse
    {
        abort_unless(
            ($request->user()?->can('historias-clinicas.view') ?? false)
            || ($request->user()?->can('historias-clinicas.update') ?? false),
            403,
        );

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $consulta->load([
            'historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
            'historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
            'veterinario:id,name',
            'cerradaPor:id,name',
            'planTratamiento.lineas.producto:id,nombre,unidad,sku',
            'cargo:id,consulta_id,estado,total',
            'examenes',
            'terapiaLineas',
            ...$this->withResultados(),
        ]);

        if ($canAudit) {
            $consulta->load([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $row = $consulta->toArray();
        $row['estado_cobro'] = $consulta->estadoCobro();

        return response()->json(['consulta' => $row]);
    }

    /**
     * Carga una consulta para abrir el modal de edición cuando el listado
     * llega con `?editar_consulta=<uuid>` (p. ej. desde la vista de plan).
     */
    private function consultaParaAbrirEnModal(Request $request, bool $canAudit): ?Consulta
    {
        $raw = $request->query('editar_consulta');
        if (! is_string($raw) || ! Str::isUuid($raw)) {
            return null;
        }

        if (! $request->user()?->can('historias-clinicas.update')) {
            return null;
        }

        $query = Consulta::query()
            ->with([
                'historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                'historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                'veterinario:id,name',
                'cerradaPor:id,name',
                'planTratamiento.lineas.producto:id,nombre,unidad,sku',
                'cargo:id,consulta_id,estado,total',
                'examenes',
                'terapiaLineas',
                ...$this->withResultados(),
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        return $query->whereKey($raw)->first();
    }

    /**
     * @return array{id: string, nombre: string, propietario: array<string, mixed>|null, motivo: ?string, cita_id: ?string}|null
     */
    private function pacientePrefillNuevaConsultaDesdeQuery(Request $request): ?array
    {
        $raw = $request->query('nuevo_para_paciente');
        if (! is_string($raw) || ! Str::isUuid($raw)) {
            return null;
        }

        if (! $request->user()?->can('historias-clinicas.create')) {
            return null;
        }

        $paciente = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
            ->whereKey($raw)
            ->where('activo', true)
            ->first(['id', 'nombre', 'propietario_id']);

        if ($paciente === null) {
            return null;
        }

        $prop = $paciente->propietario;
        $motivoRaw = $request->query('motivo');
        $motivo = is_string($motivoRaw) ? trim($motivoRaw) : '';
        if (mb_strlen($motivo) > 2000) {
            $motivo = mb_substr($motivo, 0, 2000);
        }

        $citaIdRaw = $request->query('cita_id');
        $citaId = is_string($citaIdRaw) && Str::isUuid($citaIdRaw) ? $citaIdRaw : null;

        return [
            'id' => $paciente->id,
            'nombre' => $paciente->nombre,
            'propietario' => $prop !== null ? [
                'id' => $prop->id,
                'nombres' => $prop->nombres,
                'apellidos' => $prop->apellidos,
                'razon_social' => $prop->razon_social,
            ] : null,
            'motivo' => $motivo !== '' ? $motivo : null,
            'cita_id' => $citaId,
        ];
    }

    public function store(StoreConsultaHistoriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uid = Auth::id();
        $medicoTratante = isset($validated['medico_tratante'])
            ? trim((string) $validated['medico_tratante'])
            : trim((string) ($request->user()?->name ?? ''));
        $medicoTratante = $medicoTratante !== ''
            ? Str::limit($medicoTratante, 200, '')
            : null;

        $consultaCreada = null;
        $this->assertCupoResultados(null, $request, []);

        DB::transaction(function () use ($validated, $uid, $medicoTratante, $request, &$consultaCreada): void {
            $historia = HistoriaClinica::query()->firstOrCreate(
                ['paciente_id' => $validated['paciente_id']],
                [
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ],
            );

            if ($historia->wasRecentlyCreated === false) {
                $historia->update(['updated_by_id' => $uid]);
            }

            $peso = $validated['peso_kg'] ?? null;
            $temp = $validated['temperatura_c'] ?? null;
            $fc = $validated['fc_lpm'] ?? null;
            $fr = $validated['fr_rpm'] ?? null;
            $citaId = $validated['cita_id'] ?? null;

            $consultaCreada = $historia->consultas()->create([
                'cita_id' => $citaId,
                'atendido_at' => $validated['atendido_at'],
                'motivo' => $validated['motivo'] ?? null,
                'anotaciones' => $validated['anotaciones'] ?? null,
                'subjetivo' => $validated['subjetivo'] ?? null,
                'objetivo' => $validated['objetivo'] ?? null,
                'analisis' => $validated['analisis'] ?? null,
                'plan' => null,
                'peso_kg' => $peso === null || $peso === '' ? null : $peso,
                'temperatura_c' => $temp === null || $temp === '' ? null : $temp,
                'fc_lpm' => $fc === null || $fc === '' ? null : (int) $fc,
                'fr_rpm' => $fr === null || $fr === '' ? null : (int) $fr,
                'cerrada_at' => null,
                'cerrada_por_id' => null,
                'veterinario_id' => $uid,
                'medico_tratante' => $medicoTratante,
                'created_by_id' => $uid,
                'updated_by_id' => $uid,
            ]);

            $this->syncConsultaExamenes($consultaCreada, $validated['examenes'] ?? []);
            $this->syncConsultaTerapiaLineas($consultaCreada, $validated['terapia_lineas'] ?? []);
            $this->guardarResultadosNuevos($consultaCreada, $request, is_string($uid) ? $uid : null);

            if (is_string($citaId) && $citaId !== '') {
                Cita::query()
                    ->whereKey($citaId)
                    ->where('paciente_id', $validated['paciente_id'])
                    ->whereIn('estado', [...Cita::ESTADOS_EN_ESPERA, Cita::ESTADO_EN_ATENCION])
                    ->update([
                        'estado' => Cita::ESTADO_EN_ATENCION,
                        'updated_by_id' => $uid,
                    ]);
            }
        });

        if (str_contains(url()->previous(), '/clinica/pacientes/')) {
            return back()->with('success', __('historias-clinicas.flash.created'));
        }

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.created'));
    }

    public function update(
        UpdateConsultaHistoriaRequest $request,
        Consulta $consulta,
    ): RedirectResponse {
        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->back()
                ->withErrors(['consulta_cerrada' => __('historias-clinicas.errors.no_editable_cerrada')]);
        }

        $validated = $request->validated();
        $uid = Auth::id();
        $medicoTratante = isset($validated['medico_tratante'])
            ? trim((string) $validated['medico_tratante'])
            : '';
        $medicoTratante = $medicoTratante !== ''
            ? Str::limit($medicoTratante, 200, '')
            : null;
        $quitar = array_values(array_filter(
            $validated['resultados_quitar'] ?? [],
            fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
        $this->assertCupoResultados($consulta, $request, $quitar);

        DB::transaction(function () use ($consulta, $validated, $uid, $medicoTratante, $request, $quitar): void {
            $peso = $validated['peso_kg'] ?? null;
            $temp = $validated['temperatura_c'] ?? null;
            $fc = $validated['fc_lpm'] ?? null;
            $fr = $validated['fr_rpm'] ?? null;
            $consulta->update([
                'atendido_at' => $validated['atendido_at'],
                'motivo' => $validated['motivo'] ?? null,
                'anotaciones' => $validated['anotaciones'] ?? null,
                'subjetivo' => $validated['subjetivo'] ?? null,
                'objetivo' => $validated['objetivo'] ?? null,
                'analisis' => $validated['analisis'] ?? null,
                'plan' => null,
                'medico_tratante' => $medicoTratante,
                'peso_kg' => $peso === null || $peso === '' ? null : $peso,
                'temperatura_c' => $temp === null || $temp === '' ? null : $temp,
                'fc_lpm' => $fc === null || $fc === '' ? null : (int) $fc,
                'fr_rpm' => $fr === null || $fr === '' ? null : (int) $fr,
                'updated_by_id' => $uid,
            ]);

            $this->syncConsultaExamenes($consulta, $validated['examenes'] ?? []);
            $this->syncConsultaTerapiaLineas($consulta, $validated['terapia_lineas'] ?? []);
            $this->quitarResultados($consulta, $quitar);
            $this->guardarResultadosNuevos($consulta, $request, is_string($uid) ? $uid : null);
        });

        return redirect()
            ->back()
            ->with('success', __('historias-clinicas.flash.updated'));
    }

    /**
     * Alta rápida de fármaco (catálogo tenant) desde el combobox creable de HC.
     */
    public function storeFarmaco(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->can('historias-clinicas.create')
            || $request->user()?->can('historias-clinicas.update'), 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
        ]);

        $nombre = Str::limit(trim($data['nombre']), 200, '');
        $farmaco = Farmaco::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($farmaco === null) {
            $farmaco = Farmaco::query()->create(['nombre' => $nombre]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $farmaco->id,
                'nombre' => $farmaco->nombre,
            ]);
        }

        return redirect()->route('clinica.historias-clinicas');
    }

    /**
     * Alta rápida de servicio clínico solo con nombre (precio 0, sin categoría).
     */
    public function storeServicioClinicoRapido(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->can('historias-clinicas.create')
            || $request->user()?->can('historias-clinicas.update'), 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
        ]);

        $nombre = Str::limit(trim($data['nombre']), 200, '');
        $existente = ServicioClinico::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente !== null) {
            $servicio = $existente;
        } else {
            $maxOrden = (int) ServicioClinico::query()->max('orden');
            $servicio = ServicioClinico::query()->create([
                'nombre' => $nombre,
                'categoria_id' => null,
                'precio_lista' => 0,
                'precio_costo' => null,
                'moneda' => 'PEN',
                'duracion_minutos' => null,
                'activo' => true,
                'orden' => $maxOrden + 1,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
            ]);
        }

        return redirect()->route('clinica.historias-clinicas');
    }

    public function resultado(Request $request, Consulta $consulta, ConsultaResultado $resultado): BinaryFileResponse
    {
        abort_unless($request->user()?->can('historias-clinicas.view') ?? false, 403);
        abort_unless($resultado->consulta_id === $consulta->id, 404);

        $tid = tenant_id();
        $path = $resultado->archivo_path;
        if (! is_string($tid) || $tid === '' || $path === '') {
            abort(404);
        }

        $expectedPrefix = 'consultas/'.$tid.'/'.$consulta->id.'/';
        if (! str_starts_with($path, $expectedPrefix) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        if (! in_array($resultado->mime, ConsultaResultado::MIMES, true)) {
            abort(404);
        }

        $downloadName = $resultado->original_name !== ''
            ? $resultado->original_name
            : ('resultado-'.Str::lower(Str::substr($resultado->id, 0, 8)));

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $resultado->mime,
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', "\r", "\n"], '', $downloadName).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $quitar
     */
    private function assertCupoResultados(?Consulta $consulta, Request $request, array $quitar): void
    {
        $nuevos = count($this->archivosResultado($request));
        if ($nuevos === 0 && $quitar === []) {
            return;
        }

        if (! Schema::hasTable('consulta_resultados')) {
            throw ValidationException::withMessages([
                'resultados' => __('historias-clinicas.errors.resultados_migracion'),
            ]);
        }

        $actuales = $consulta === null ? 0 : $consulta->resultados()->count();
        $quitarValidos = $consulta === null || $quitar === []
            ? 0
            : $consulta->resultados()->whereIn('id', $quitar)->count();

        if (($actuales - $quitarValidos + $nuevos) > 12) {
            throw ValidationException::withMessages([
                'resultados' => __('historias-clinicas.errors.resultados_max'),
            ]);
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function quitarResultados(Consulta $consulta, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable('consulta_resultados')) {
            return;
        }

        $consulta->resultados()->whereIn('id', $ids)->get()->each->delete();
    }

    private function guardarResultadosNuevos(Consulta $consulta, Request $request, ?string $uid): void
    {
        $files = $this->archivosResultado($request);
        if ($files === []) {
            return;
        }

        if (! Schema::hasTable('consulta_resultados')) {
            throw ValidationException::withMessages([
                'resultados' => __('historias-clinicas.errors.resultados_migracion'),
            ]);
        }

        $tid = tenant_id();
        if (! is_string($tid) || $tid === '') {
            abort(403);
        }

        $orden = (int) $consulta->resultados()->max('orden');
        foreach ($files as $file) {
            $mime = (string) ($file->getMimeType() ?: '');
            if (! in_array($mime, ConsultaResultado::MIMES, true)) {
                throw ValidationException::withMessages([
                    'resultados' => __('historias-clinicas.errors.resultados_tipo'),
                ]);
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
            $safe = Str::lower(Str::random(24)).'.'.$ext;
            $path = $file->storeAs('consultas/'.$tid.'/'.$consulta->id, $safe, 'local');
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'resultados' => __('historias-clinicas.errors.resultados_tipo'),
                ]);
            }

            $nombre = trim(str_replace(['"', "\r", "\n", '\\', '/'], '', $file->getClientOriginalName()));
            $nombre = $nombre !== '' ? Str::limit($nombre, 255, '') : 'resultado.'.$ext;

            ConsultaResultado::query()->create([
                'consulta_id' => $consulta->id,
                'archivo_path' => $path,
                'original_name' => $nombre,
                'mime' => $mime,
                'bytes' => $file->getSize() !== false ? (int) $file->getSize() : null,
                'orden' => ++$orden,
                'created_by_id' => $uid,
            ]);
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function archivosResultado(Request $request): array
    {
        $files = $request->file('resultados');
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (! is_array($files)) {
            return [];
        }

        $validos = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $validos[] = $file;
            }
        }

        return $validos;
    }

    /**
     * @return list<string>
     */
    private function withResultados(): array
    {
        return Schema::hasTable('consulta_resultados') ? ['resultados'] : [];
    }

    /**
     * @param  list<array{servicio_clinico_id?: ?string, nombre: string}>  $examenes
     */
    private function syncConsultaExamenes(Consulta $consulta, array $examenes): void
    {
        $consulta->examenes()->delete();

        $orden = 0;
        foreach ($examenes as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nombre = trim((string) ($row['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            ConsultaExamen::query()->create([
                'consulta_id' => $consulta->id,
                'servicio_clinico_id' => isset($row['servicio_clinico_id']) && is_string($row['servicio_clinico_id']) && $row['servicio_clinico_id'] !== ''
                    ? $row['servicio_clinico_id']
                    : null,
                'nombre' => Str::limit($nombre, 500, ''),
                'orden' => $orden++,
            ]);
        }
    }

    /**
     * @param  list<array{farmaco_id?: ?string, farmaco_nombre: string, dosis_volumen?: ?string}>  $lineas
     */
    private function syncConsultaTerapiaLineas(Consulta $consulta, array $lineas): void
    {
        $consulta->terapiaLineas()->delete();

        $orden = 0;
        foreach ($lineas as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nombre = trim((string) ($row['farmaco_nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $dosis = isset($row['dosis_volumen']) ? trim((string) $row['dosis_volumen']) : '';
            $farmacoId = isset($row['farmaco_id']) && is_string($row['farmaco_id']) && $row['farmaco_id'] !== ''
                ? $row['farmaco_id']
                : null;

            if ($farmacoId === null) {
                $farmaco = Farmaco::query()
                    ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                    ->first();
                if ($farmaco === null) {
                    $farmaco = Farmaco::query()->create(['nombre' => Str::limit($nombre, 200, '')]);
                }
                $farmacoId = $farmaco->id;
                $nombre = $farmaco->nombre;
            }

            ConsultaTerapiaLinea::query()->create([
                'consulta_id' => $consulta->id,
                'farmaco_id' => $farmacoId,
                'farmaco_nombre' => Str::limit($nombre, 200, ''),
                'dosis_volumen' => $dosis !== '' ? Str::limit($dosis, 200, '') : null,
                'orden' => $orden++,
            ]);
        }
    }

    public function cerrar(Consulta $consulta): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('success', __('historias-clinicas.flash.ya_cerrada'));
        }

        $uid = Auth::id();
        $consulta->update([
            'cerrada_at' => now(),
            'cerrada_por_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        $this->marcarCitaCompletadaSiCorresponde($consulta, $uid);

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.cerrada'));
    }

    public function cerrarAbiertas(): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        $uid = Auth::id();
        $now = now();

        $abiertas = Consulta::query()
            ->whereNull('cerrada_at')
            ->get(['id', 'cita_id']);

        $count = Consulta::query()
            ->whereNull('cerrada_at')
            ->update([
                'cerrada_at' => $now,
                'cerrada_por_id' => $uid,
                'updated_by_id' => $uid,
                'updated_at' => $now,
            ]);

        $citaIds = $abiertas
            ->pluck('cita_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($citaIds !== []) {
            Cita::query()
                ->whereIn('id', $citaIds)
                ->where('estado', Cita::ESTADO_EN_ATENCION)
                ->update([
                    'estado' => Cita::ESTADO_COMPLETADA,
                    'updated_by_id' => $uid,
                ]);
        }

        if ($count === 0) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('info', __('historias-clinicas.flash.cerrar_abiertas_ninguna'));
        }

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.cerrar_abiertas', ['count' => $count]));
    }

    public function reabrir(Consulta $consulta): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        if ($consulta->cerrada_at === null) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('success', __('historias-clinicas.flash.ya_abierta'));
        }

        $uid = Auth::id();
        $consulta->update([
            'cerrada_at' => null,
            'cerrada_por_id' => null,
            'updated_by_id' => $uid,
        ]);

        if (is_string($consulta->cita_id) && $consulta->cita_id !== '') {
            Cita::query()
                ->whereKey($consulta->cita_id)
                ->where('estado', Cita::ESTADO_COMPLETADA)
                ->update([
                    'estado' => Cita::ESTADO_EN_ATENCION,
                    'updated_by_id' => $uid,
                ]);
        }

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.reabierta'));
    }

    public function destroy(Consulta $consulta): RedirectResponse
    {
        $consulta->delete();

        return redirect()
            ->back()
            ->with('success', __('historias-clinicas.flash.deleted'));
    }

    private function marcarCitaCompletadaSiCorresponde(Consulta $consulta, ?string $uid): void
    {
        if (! is_string($consulta->cita_id) || $consulta->cita_id === '') {
            return;
        }

        Cita::query()
            ->whereKey($consulta->cita_id)
            ->where('estado', Cita::ESTADO_EN_ATENCION)
            ->update([
                'estado' => Cita::ESTADO_COMPLETADA,
                'updated_by_id' => $uid,
            ]);
    }

    public function pdf(Request $request, Consulta $consulta): HttpResponse
    {
        abort_unless($request->user()?->can('historias-clinicas.view') ?? false, 403);

        return $this->renderPdf($request, $consulta);
    }

    public function publicPdf(Request $request, Consulta $consulta): HttpResponse
    {
        return $this->renderPdf($request, $consulta);
    }

    private function renderPdf(Request $request, Consulta $consulta): HttpResponse
    {
        $consulta->load([
            'historiaClinica.paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'examenes',
            'terapiaLineas',
            'recetas:id,consulta_id,estado',
            'pedidosLaboratorio:id,consulta_id,estado',
            'cirugias:id,consulta_id,estado,nombre_procedimiento',
            'internamientos:id,consulta_id,estado,motivo_ingreso',
        ]);

        $paciente = $consulta->historiaClinica?->paciente;
        abort_if($paciente === null, 404);

        $entry = HistorialClinicoPdfBuilder::make()->fromConsulta($consulta);

        $pdf = Pdf::loadView('pdf.consulta-clinica', array_merge(
            $this->clinicPdfBranding('consulta'),
            [
                'paciente' => $paciente,
                'propietarioNombre' => $this->propietarioNombreParaPdf($paciente),
                'entry' => $entry,
            ],
        ));

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'consulta-'.$slug.'-'.Str::substr($consulta->id, 0, 8).'.pdf';

        return $this->respondClinicPdf($request, $pdf, $filename);
    }
}
