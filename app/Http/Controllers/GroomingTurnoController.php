<?php

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Http\Requests\StoreGroomingTurnoRequest;
use App\Http\Requests\UpdateGroomingTurnoRequest;
use App\Models\GroomingServicio;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\User;
use App\Support\Grooming\GroomingTurnoServicioRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GroomingTurnoController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'inicio_at',
        'paciente',
        'estado',
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

        $groomingDesde = $this->parseDateParam($request->query('grooming_desde'));
        $groomingHasta = $this->parseDateParam($request->query('grooming_hasta'));

        if ($groomingDesde === null || $groomingHasta === null) {
            $groomingDesde = $defaultDesde;
            $groomingHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($groomingDesde > $groomingHasta) {
                [$groomingDesde, $groomingHasta] = [$groomingHasta, $groomingDesde];
            }
            $fueraDelMesActual = ($groomingDesde !== $defaultDesde) || ($groomingHasta !== $defaultHasta);
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $turnoAbrirEditar = null;
        $editarRaw = $request->query('editar_grooming_turno');
        if (is_string($editarRaw) && Str::isUuid($editarRaw) && ($request->user()?->can('grooming.update') ?? false)) {
            $q = GroomingTurno::query()
                ->with([
                    'paciente' => fn ($q) => $q->withTrashed(),
                    'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                    'responsable:id,name',
                    'sede:id,nombre,codigo',
                ])
                ->whereKey($editarRaw);

            if ($canAudit) {
                $q->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $model = $q->first();

            if ($model !== null) {
                $turnoAbrirEditar = $model;
                $at = $model->inicio_at->copy()->timezone($tz);
                $groomingDesde = $at->copy()->startOfMonth()->toDateString();
                $groomingHasta = $at->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($groomingDesde !== $defaultDesde) || ($groomingHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($groomingDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($groomingHasta, $tz)->endOfDay();

        $query = GroomingTurno::query()
            ->with([
                'paciente' => fn ($q) => $q->withTrashed(),
                'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                'responsable:id,name',
                'sede:id,nombre,codigo',
                'groomingServicio:id,nombre',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('grooming_turnos.inicio_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as gt_pac', 'gt_pac.id', '=', 'grooming_turnos.paciente_id')
                ->orderBy('gt_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('grooming_turnos.inicio_at')
                ->select('grooming_turnos.*');
        } elseif ($sortValid) {
            $query->orderBy('grooming_turnos.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'inicio_at') {
                $query->orderByDesc('grooming_turnos.inicio_at');
            }
        } else {
            $query->orderByDesc('grooming_turnos.inicio_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('grooming_turnos.servicio', 'ILIKE', "%{$search}%")
                    ->orWhere('grooming_turnos.servicio_detalle', 'ILIKE', "%{$search}%")
                    ->orWhere('grooming_turnos.notas', 'ILIKE', "%{$search}%")
                    ->orWhereHas('paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    });
            });
        }

        $turnos = $query->paginate($perPage)->withQueryString();

        $totalEnRango = GroomingTurno::query()
            ->whereBetween('inicio_at', [$inicioRango, $finRango])
            ->count();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
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

        $catalogoPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();

        $groomingServicios = $catalogoPersonalizado
            ? GroomingServicio::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden'])
            : collect();

        return Inertia::render('servicios/grooming/index', [
            'turnos' => $turnos,
            'grooming_catalogo_personalizado' => $catalogoPersonalizado,
            'grooming_servicios' => $groomingServicios,
            'grooming_servicio_grupos' => $catalogoPersonalizado ? [] : GroomingCatalogoServicio::grupos(),
            'grooming_servicio_duraciones' => $catalogoPersonalizado
                ? $groomingServicios->mapWithKeys(fn ($s) => [$s->id => $s->duracion_minutos])->all()
                : GroomingCatalogoServicio::duracionesSugeridas(),
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'turno_abrir_editar' => $turnoAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'grooming_desde' => $groomingDesde,
                'grooming_hasta' => $groomingHasta,
            ],
            'grooming_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $turnos->total(),
            ],
        ]);
    }

    public function store(StoreGroomingTurnoRequest $request): RedirectResponse
    {
        $data = GroomingTurnoServicioRules::normalizarParaPersistencia($request->validated());
        $data['estado'] = GroomingTurno::ESTADO_PROGRAMADA;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        GroomingTurno::query()->create($data);

        return redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]))
            ->with('success', __('grooming.flash.created'));
    }

    public function update(UpdateGroomingTurnoRequest $request, GroomingTurno $groomingTurno): RedirectResponse
    {
        $data = GroomingTurnoServicioRules::normalizarParaPersistencia($request->validated());
        $data['updated_by_id'] = Auth::id();

        $groomingTurno->fill($data);
        $groomingTurno->save();

        return redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]))
            ->with('success', __('grooming.flash.updated'));
    }

    public function destroy(Request $request, GroomingTurno $groomingTurno): RedirectResponse
    {
        abort_unless($request->user()?->can('grooming.delete') ?? false, 403);

        $groomingTurno->delete();

        return redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]))
            ->with('success', __('grooming.flash.deleted'));
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
