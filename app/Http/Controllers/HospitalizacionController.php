<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternamientoEvolucionRequest;
use App\Http\Requests\StoreInternamientoRequest;
use App\Http\Requests\UpdateInternamientoEvolucionRequest;
use App\Http\Requests\UpdateInternamientoRequest;
use App\Models\Consulta;
use App\Models\ConsultaCargo;
use App\Models\Internamiento;
use App\Models\InternamientoEvolucion;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HospitalizacionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'ingreso_at',
        'paciente',
        'estado',
        'motivo_ingreso',
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

        $ingresoDesde = $this->parseDateParam($request->query('ingreso_desde'));
        $ingresoHasta = $this->parseDateParam($request->query('ingreso_hasta'));

        if ($ingresoDesde === null || $ingresoHasta === null) {
            $ingresoDesde = $defaultDesde;
            $ingresoHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($ingresoDesde > $ingresoHasta) {
                [$ingresoDesde, $ingresoHasta] = [$ingresoHasta, $ingresoDesde];
            }
            $fueraDelMesActual = ($ingresoDesde !== $defaultDesde) || ($ingresoHasta !== $defaultHasta);
        }

        $internamientoAbrirEditar = null;
        $editarRaw = $request->query('editar_internamiento');
        if (is_string($editarRaw) && Str::isUuid($editarRaw) && ($request->user()?->can('hospitalizacion.update') ?? false)) {
            $canAuditEdit = $request->user()?->can('audit-trail.view') ?? false;
            $qEdit = Internamiento::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'consulta:id,atendido_at,cerrada_at,historia_clinica_id',
                    'consulta.historiaClinica:id,paciente_id',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                ])
                ->whereKey($editarRaw);

            if ($canAuditEdit) {
                $qEdit->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $model = $qEdit->first();

            if ($model !== null) {
                $internamientoAbrirEditar = $model;
                $at = $model->ingreso_at->copy()->timezone($tz);
                $ingresoDesde = $at->copy()->startOfMonth()->toDateString();
                $ingresoHasta = $at->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($ingresoDesde !== $defaultDesde) || ($ingresoHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($ingresoDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($ingresoHasta, $tz)->endOfDay();

        $tenantId = tenant_id();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Internamiento::query()
            ->whereBetween('internamientos.ingreso_at', [$inicioRango, $finRango]);

        $estadoFiltroRaw = trim((string) ($request->query('estado') ?? ''));
        $estadoFiltro = in_array($estadoFiltroRaw, Internamiento::ESTADOS, true) ? $estadoFiltroRaw : null;
        if ($estadoFiltro !== null) {
            $query->where('internamientos.estado', $estadoFiltro);
        }

        $this->applyListFilters($query, $search);

        $query->with([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'consulta:id,atendido_at,historia_clinica_id',
            'consulta.historiaClinica:id,paciente_id',
            'veterinario:id,name',
            'sede:id,nombre,codigo',
        ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as int_pac', 'int_pac.id', '=', 'internamientos.paciente_id')
                ->orderBy('int_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('internamientos.ingreso_at')
                ->select('internamientos.*');
        } elseif ($sortValid) {
            $query->orderBy('internamientos.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'ingreso_at') {
                $query->orderByDesc('internamientos.ingreso_at');
            }
        } else {
            $query->orderByDesc('internamientos.ingreso_at');
        }

        $internamientos = $query->paginate($perPage)->withQueryString();

        $totalEnRango = Internamiento::query()
            ->whereBetween('ingreso_at', [$inicioRango, $finRango])
            ->count();

        $activosEnRango = Internamiento::query()
            ->whereBetween('ingreso_at', [$inicioRango, $finRango])
            ->where('estado', Internamiento::ESTADO_ACTIVO)
            ->count();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

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

        $consultasOpciones = Consulta::query()
            ->whereNull('cerrada_at')
            ->with([
                'historiaClinica:id,paciente_id',
                'historiaClinica.paciente:id,nombre',
            ])
            ->orderByDesc('atendido_at')
            ->limit(150)
            ->get(['id', 'atendido_at', 'historia_clinica_id']);

        return Inertia::render('clinica/hospitalizacion/index', [
            'internamientos' => $internamientos,
            'internamiento_abrir_editar' => $internamientoAbrirEditar,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'consultas_opciones' => $consultasOpciones,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'ingreso_desde' => $ingresoDesde,
                'ingreso_hasta' => $ingresoHasta,
                'estado' => $estadoFiltro ?? '',
            ],
            'hospitalizacion_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'activos' => $activosEnRango,
                'coincidencias' => $internamientos->total(),
            ],
        ]);
    }

    public function show(Request $request, Internamiento $internamiento): InertiaResponse
    {
        abort_unless($request->user()?->can('hospitalizacion.view') ?? false, 403);

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;
        $tenantId = tenant_id();

        $with = [
            'paciente.propietario:id,nombres,apellidos,razon_social,telefono',
            'consulta:id,atendido_at,historia_clinica_id',
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'evoluciones' => fn ($q) => $q->orderByDesc('registrado_at')->with('veterinario:id,name'),
        ];

        if ($canAudit) {
            $with['evoluciones'] = fn ($q) => $q->orderByDesc('registrado_at')->with([
                'veterinario:id,name',
                'creadoPor:id,name,email',
            ]);
            $with[] = 'creadoPor:id,name,email';
            $with[] = 'actualizadoPor:id,name,email';
        }

        $internamiento->load($with);

        $cargoInternamiento = ConsultaCargo::query()
            ->where('internamiento_id', $internamiento->id)
            ->first(['id', 'estado', 'total', 'moneda', 'venta_id']);

        $cargoConsulta = null;
        if ($internamiento->consulta_id !== null) {
            $cargoConsulta = ConsultaCargo::query()
                ->where('consulta_id', $internamiento->consulta_id)
                ->first(['id', 'estado', 'total', 'moneda', 'venta_id']);
        }

        $user = $request->user();
        $puedeVerCargos = $user?->can('consulta-cargos.view') ?? false;
        $puedeGestionarCargos = $user !== null && (
            $user->can('consulta-cargos.manage')
            || $user->can('hospitalizacion.update')
        );

        $usuariosOpciones = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        return Inertia::render('clinica/hospitalizacion/show', [
            'internamiento' => $internamiento,
            'usuarios_opciones' => $usuariosOpciones,
            'cobro' => [
                'consulta_id' => $internamiento->consulta_id,
                'cargo' => $cargoInternamiento ?? $cargoConsulta,
                'cargo_internamiento' => $cargoInternamiento,
                'cargo_consulta' => $cargoConsulta,
                'url_cargos_internamiento' => $puedeVerCargos
                    ? route('clinica.hospitalizacion.cargos.show', $internamiento)
                    : null,
                'url_cargos_consulta' => $internamiento->consulta_id !== null && $puedeVerCargos
                    ? route('clinica.historias-clinicas.consultas.cargos.show', $internamiento->consulta_id)
                    : null,
                'puede_ver_cargos' => $puedeVerCargos,
                'puede_gestionar_cargos' => $puedeGestionarCargos && $puedeVerCargos,
            ],
        ]);
    }

    public function storeEvolucion(
        StoreInternamientoEvolucionRequest $request,
        Internamiento $internamiento,
    ): RedirectResponse {
        $data = $request->validated();
        $data['internamiento_id'] = $internamiento->id;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        InternamientoEvolucion::query()->create($data);

        return redirect()
            ->route('clinica.hospitalizacion.show', $internamiento)
            ->with('success', __('hospitalizacion.flash.evolucion_created'));
    }

    public function updateEvolucion(
        UpdateInternamientoEvolucionRequest $request,
        Internamiento $internamiento,
        InternamientoEvolucion $evolucion,
    ): RedirectResponse {
        abort_unless($evolucion->internamiento_id === $internamiento->id, 404);

        $data = $request->validated();
        $data['updated_by_id'] = Auth::id();

        $evolucion->fill($data);
        $evolucion->save();

        return redirect()
            ->route('clinica.hospitalizacion.show', $internamiento)
            ->with('success', __('hospitalizacion.flash.evolucion_updated'));
    }

    public function destroyEvolucion(
        Request $request,
        Internamiento $internamiento,
        InternamientoEvolucion $evolucion,
    ): RedirectResponse {
        abort_unless($request->user()?->can('hospitalizacion.update') ?? false, 403);
        abort_unless($evolucion->internamiento_id === $internamiento->id, 404);

        $evolucion->delete();

        return redirect()
            ->route('clinica.hospitalizacion.show', $internamiento)
            ->with('success', __('hospitalizacion.flash.evolucion_deleted'));
    }

    public function store(StoreInternamientoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->normalizeEstadoFechas($data);
        $data['estado'] = $data['estado'] ?? Internamiento::ESTADO_ACTIVO;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        Internamiento::query()->create($data);

        return redirect()
            ->route('clinica.hospitalizacion.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'ingreso_desde', 'ingreso_hasta', 'estado',
            ]))
            ->with('success', __('hospitalizacion.flash.created'));
    }

    public function update(UpdateInternamientoRequest $request, Internamiento $internamiento): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->normalizeEstadoFechas($data);
        $data['updated_by_id'] = Auth::id();

        $internamiento->fill($data);
        $internamiento->save();

        return redirect()
            ->route('clinica.hospitalizacion.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'ingreso_desde', 'ingreso_hasta', 'estado',
            ]))
            ->with('success', __('hospitalizacion.flash.updated'));
    }

    public function destroy(Request $request, Internamiento $internamiento): RedirectResponse
    {
        abort_unless($request->user()?->can('hospitalizacion.delete') ?? false, 403);

        $internamiento->delete();

        return redirect()
            ->route('clinica.hospitalizacion.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'ingreso_desde', 'ingreso_hasta', 'estado',
            ]))
            ->with('success', __('hospitalizacion.flash.deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeEstadoFechas(array $data): array
    {
        $tz = config('app.timezone');
        $estado = (string) ($data['estado'] ?? Internamiento::ESTADO_ACTIVO);

        if ($estado === Internamiento::ESTADO_ALTA) {
            if (empty($data['alta_at'])) {
                $data['alta_at'] = now($tz);
            }
        } else {
            $data['alta_at'] = null;
        }

        return $data;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function listIndexQuery(Request $request, array $keys): array
    {
        return array_intersect_key($request->query->all(), array_flip($keys));
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function applyListFilters(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('internamientos.notas', 'ILIKE', "%{$search}%")
                ->orWhere('internamientos.estado', 'ILIKE', "%{$search}%")
                ->orWhere('internamientos.motivo_ingreso', 'ILIKE', "%{$search}%")
                ->orWhere('internamientos.ubicacion', 'ILIKE', "%{$search}%")
                ->orWhere('internamientos.diagnostico_ingreso', 'ILIKE', "%{$search}%")
                ->orWhereHas('paciente', function ($q2) use ($search) {
                    $q2->where('nombre', 'ILIKE', "%{$search}%")
                        ->orWhereHas('propietario', function ($q3) use ($search) {
                            $q3->where('nombres', 'ILIKE', "%{$search}%")
                                ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                        });
                })
                ->orWhereHas('veterinario', function ($q4) use ($search) {
                    $q4->where('name', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('sede', function ($q5) use ($search) {
                    $q5->where('nombre', 'ILIKE', "%{$search}%")
                        ->orWhere('codigo', 'ILIKE', "%{$search}%");
                });
        });
    }
}
