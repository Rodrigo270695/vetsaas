<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCirugiaRequest;
use App\Http\Requests\UpdateCirugiaRequest;
use App\Models\Cirugia;
use App\Models\Consulta;
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

class CirugiaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'programada_at',
        'paciente',
        'estado',
        'nombre_procedimiento',
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

        $programadaDesde = $this->parseDateParam($request->query('programada_desde'));
        $programadaHasta = $this->parseDateParam($request->query('programada_hasta'));

        if ($programadaDesde === null || $programadaHasta === null) {
            $programadaDesde = $defaultDesde;
            $programadaHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($programadaDesde > $programadaHasta) {
                [$programadaDesde, $programadaHasta] = [$programadaHasta, $programadaDesde];
            }
            $fueraDelMesActual = ($programadaDesde !== $defaultDesde) || ($programadaHasta !== $defaultHasta);
        }

        $cirugiaAbrirEditar = null;
        $editarCirugiaRaw = $request->query('editar_cirugia');
        if (is_string($editarCirugiaRaw) && Str::isUuid($editarCirugiaRaw) && ($request->user()?->can('cirugias.update') ?? false)) {
            $canAuditEdit = $request->user()?->can('audit-trail.view') ?? false;
            $qEdit = Cirugia::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'consulta:id,atendido_at,cerrada_at,historia_clinica_id',
                    'consulta.historiaClinica:id,paciente_id',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                ])
                ->whereKey($editarCirugiaRaw);

            if ($canAuditEdit) {
                $qEdit->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $cirModel = $qEdit->first();

            if ($cirModel !== null) {
                $cirugiaAbrirEditar = $cirModel;
                $atCir = $cirModel->programada_at->copy()->timezone($tz);
                $programadaDesde = $atCir->copy()->startOfMonth()->toDateString();
                $programadaHasta = $atCir->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($programadaDesde !== $defaultDesde) || ($programadaHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($programadaDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($programadaHasta, $tz)->endOfDay();

        $tenantId = tenant_id();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Cirugia::query()
            ->whereBetween('cirugias.programada_at', [$inicioRango, $finRango]);

        $estadoFiltroRaw = trim((string) ($request->query('estado') ?? ''));
        $estadoFiltro = in_array($estadoFiltroRaw, Cirugia::ESTADOS, true) ? $estadoFiltroRaw : null;
        if ($estadoFiltro !== null) {
            $query->where('cirugias.estado', $estadoFiltro);
        }

        $this->applyCirugiaListFilters($query, $search);

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
                ->join('pacientes as cir_pac', 'cir_pac.id', '=', 'cirugias.paciente_id')
                ->orderBy('cir_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('cirugias.programada_at')
                ->select('cirugias.*');
        } elseif ($sortValid) {
            $query->orderBy('cirugias.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'programada_at') {
                $query->orderByDesc('cirugias.programada_at');
            }
        } else {
            $query->orderByDesc('cirugias.programada_at');
        }

        $cirugias = $query->paginate($perPage)->withQueryString();

        $totalEnRango = Cirugia::query()
            ->whereBetween('programada_at', [$inicioRango, $finRango])
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

        return Inertia::render('clinica/cirugias/index', [
            'cirugias' => $cirugias,
            'cirugia_abrir_editar' => $cirugiaAbrirEditar,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'consultas_opciones' => $consultasOpciones,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'programada_desde' => $programadaDesde,
                'programada_hasta' => $programadaHasta,
                'estado' => $estadoFiltro ?? '',
            ],
            'cirugia_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $cirugias->total(),
            ],
        ]);
    }

    public function store(StoreCirugiaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['estado'] = $data['estado'] ?? Cirugia::ESTADO_BORRADOR;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        Cirugia::query()->create($data);

        return redirect()
            ->route('clinica.cirugias.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'programada_desde', 'programada_hasta', 'estado',
            ]))
            ->with('success', __('cirugia.flash.created'));
    }

    public function update(UpdateCirugiaRequest $request, Cirugia $cirugia): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by_id'] = Auth::id();

        $cirugia->fill($data);
        $cirugia->save();

        return redirect()
            ->route('clinica.cirugias.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'programada_desde', 'programada_hasta', 'estado',
            ]))
            ->with('success', __('cirugia.flash.updated'));
    }

    public function destroy(Request $request, Cirugia $cirugia): RedirectResponse
    {
        abort_unless($request->user()?->can('cirugias.delete') ?? false, 403);

        $cirugia->delete();

        return redirect()
            ->route('clinica.cirugias.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'programada_desde', 'programada_hasta', 'estado',
            ]))
            ->with('success', __('cirugia.flash.deleted'));
    }

    /**
     * Solo parámetros de la query string (evita mezclar el body del POST del formulario).
     *
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

    private function applyCirugiaListFilters(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('cirugias.observaciones', 'ILIKE', "%{$search}%")
                ->orWhere('cirugias.estado', 'ILIKE', "%{$search}%")
                ->orWhere('cirugias.nombre_procedimiento', 'ILIKE', "%{$search}%")
                ->orWhere('cirugias.tipo_anestesia', 'ILIKE', "%{$search}%")
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
