<?php

namespace App\Http\Controllers;

use App\Exports\CitasXlsxExport;
use App\Http\Requests\StoreCitaRequest;
use App\Http\Requests\UpdateCitaRequest;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CitaController extends Controller
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

        $vista = (string) $request->string('vista', 'calendario');
        if (! in_array($vista, ['calendario', 'lista'], true)) {
            $vista = 'calendario';
        }

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();
        $defaultMes = $now->format('Y-m');

        $mes = (string) $request->string('mes', '');
        if (preg_match('/^\d{4}-\d{2}$/', $mes) !== 1) {
            $mes = $defaultMes;
        }

        if ($vista === 'calendario') {
            $mesStart = Carbon::parse($mes.'-01', $tz);
            $citaDesde = $mesStart->copy()->startOfMonth()->toDateString();
            $citaHasta = $mesStart->copy()->endOfMonth()->toDateString();
            $fueraDelMesActual = ($mes !== $defaultMes);
        } else {
            $citaDesde = $this->parseDateParam($request->query('cita_desde'));
            $citaHasta = $this->parseDateParam($request->query('cita_hasta'));

            if ($citaDesde === null || $citaHasta === null) {
                $citaDesde = $defaultDesde;
                $citaHasta = $defaultHasta;
                $fueraDelMesActual = false;
            } else {
                if ($citaDesde > $citaHasta) {
                    [$citaDesde, $citaHasta] = [$citaHasta, $citaDesde];
                }
                $fueraDelMesActual = ($citaDesde !== $defaultDesde) || ($citaHasta !== $defaultHasta);
            }
        }

        $citaAbrirEditar = null;
        $editarCitaRaw = $request->query('editar_cita');
        if (is_string($editarCitaRaw) && Str::isUuid($editarCitaRaw) && ($request->user()?->can('citas.update') ?? false)) {
            $canAuditCita = $request->user()?->can('audit-trail.view') ?? false;
            $citaEditQuery = Cita::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                ])
                ->whereKey($editarCitaRaw);

            if ($canAuditCita) {
                $citaEditQuery->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $citaModel = $citaEditQuery->first();

            if ($citaModel !== null) {
                $citaAbrirEditar = $citaModel;
                $atCita = $citaModel->inicio_at->copy()->timezone($tz);

                if ($vista === 'calendario') {
                    $mes = $atCita->format('Y-m');
                    $citaDesde = $atCita->copy()->startOfMonth()->toDateString();
                    $citaHasta = $atCita->copy()->endOfMonth()->toDateString();
                    $fueraDelMesActual = ($mes !== $defaultMes);
                } else {
                    $citaDesde = $atCita->copy()->startOfMonth()->toDateString();
                    $citaHasta = $atCita->copy()->endOfMonth()->toDateString();
                    $fueraDelMesActual = ($citaDesde !== $defaultDesde) || ($citaHasta !== $defaultHasta);
                }
            }
        }

        $inicioRango = Carbon::parse($citaDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($citaHasta, $tz)->endOfDay();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Cita::query()
            ->with([
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('citas.inicio_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as cita_pac', 'cita_pac.id', '=', 'citas.paciente_id')
                ->orderBy('cita_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('citas.inicio_at')
                ->select('citas.*');
        } elseif ($sortValid) {
            $query->orderBy('citas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'inicio_at') {
                $query->orderByDesc('citas.inicio_at');
            }
        } else {
            $query->orderByDesc('citas.inicio_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('citas.motivo', 'ILIKE', "%{$search}%")
                    ->orWhere('citas.notas', 'ILIKE', "%{$search}%")
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

        $totalEnRango = Cita::query()
            ->whereBetween('inicio_at', [$inicioRango, $finRango])
            ->count();

        if ($vista === 'calendario') {
            $citasAgenda = (clone $query)->limit(500)->get();
            $citas = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                ['path' => $request->url(), 'query' => $request->query()],
            );
            $coincidencias = $citasAgenda->count();
        } else {
            $citasAgenda = collect();
            $citas = $query->paginate($perPage)->withQueryString();
            $coincidencias = $citas->total();
        }

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

        return Inertia::render('clinica/citas/index', [
            'citas' => $citas,
            'citas_agenda' => $citasAgenda->values()->all(),
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'cita_abrir_editar' => $citaAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'cita_desde' => $citaDesde,
                'cita_hasta' => $citaHasta,
                'vista' => $vista,
                'mes' => $vista === 'calendario' ? $mes : null,
            ],
            'cita_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'default_mes' => $defaultMes,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $coincidencias,
            ],
        ]);
    }

    /**
     * Exporta citas a XLSX respetando vista, mes/rango, búsqueda y orden.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('citas.view'), 403);

        $ctx = $this->resolveCitasExportContext($request);

        $query = clone $ctx['list_query'];
        $query->reorder()->orderBy('citas.inicio_at', 'asc');
        $query->withOnly([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'creadoPor:id,name',
        ]);

        $filename = 'citas-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new CitasXlsxExport;

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

    public function store(StoreCitaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['estado'] = Cita::ESTADO_PROGRAMADA;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        Cita::query()->create($data);

        return redirect()
            ->route('clinica.citas.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'cita_desde', 'cita_hasta', 'vista', 'mes',
            ]))
            ->with('success', __('citas.flash.created'));
    }

    public function update(UpdateCitaRequest $request, Cita $cita): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by_id'] = Auth::id();

        $cita->fill($data);
        $cita->save();

        return redirect()
            ->route('clinica.citas.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'cita_desde', 'cita_hasta', 'vista', 'mes',
            ]))
            ->with('success', __('citas.flash.updated'));
    }

    public function destroy(Request $request, Cita $cita): RedirectResponse
    {
        abort_unless($request->user()?->can('citas.delete') ?? false, 403);

        $cita->delete();

        return redirect()
            ->route('clinica.citas.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'cita_desde', 'cita_hasta', 'vista', 'mes',
            ]))
            ->with('success', __('citas.flash.deleted'));
    }

    public function cancelar(Request $request, Cita $cita): RedirectResponse
    {
        abort_unless($request->user()?->can('citas.cancel') ?? false, 403);

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_COMPLETADA], true)) {
            throw ValidationException::withMessages([
                'estado' => __('citas.validation.cancel_not_allowed'),
            ]);
        }

        $cita->forceFill([
            'estado' => Cita::ESTADO_CANCELADA,
            'updated_by_id' => Auth::id(),
        ])->save();

        return redirect()
            ->route('clinica.citas.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'cita_desde', 'cita_hasta', 'vista', 'mes',
            ]))
            ->with('success', __('citas.flash.cancelled'));
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Filtros compartidos para la exportación XLSX (misma lógica de rango que el listado).
     *
     * @return array{
     *     list_query: Builder<Cita>,
     * }
     */
    private function resolveCitasExportContext(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $vista = (string) $request->string('vista', 'calendario');
        if (! in_array($vista, ['calendario', 'lista'], true)) {
            $vista = 'calendario';
        }

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();
        $defaultMes = $now->format('Y-m');

        $mes = (string) $request->string('mes', '');
        if (preg_match('/^\d{4}-\d{2}$/', $mes) !== 1) {
            $mes = $defaultMes;
        }

        if ($vista === 'calendario') {
            $mesStart = Carbon::parse($mes.'-01', $tz);
            $citaDesde = $mesStart->copy()->startOfMonth()->toDateString();
            $citaHasta = $mesStart->copy()->endOfMonth()->toDateString();
        } else {
            $citaDesde = $this->parseDateParam($request->query('cita_desde'));
            $citaHasta = $this->parseDateParam($request->query('cita_hasta'));

            if ($citaDesde === null || $citaHasta === null) {
                $citaDesde = $defaultDesde;
                $citaHasta = $defaultHasta;
            } elseif ($citaDesde > $citaHasta) {
                [$citaDesde, $citaHasta] = [$citaHasta, $citaDesde];
            }
        }

        $inicioRango = Carbon::parse($citaDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($citaHasta, $tz)->endOfDay();

        $query = Cita::query()->whereBetween('citas.inicio_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as cita_pac', 'cita_pac.id', '=', 'citas.paciente_id')
                ->orderBy('cita_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('citas.inicio_at')
                ->select('citas.*');
        } elseif ($sortValid) {
            $query->orderBy('citas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'inicio_at') {
                $query->orderByDesc('citas.inicio_at');
            }
        } else {
            $query->orderByDesc('citas.inicio_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('citas.motivo', 'ILIKE', "%{$search}%")
                    ->orWhere('citas.notas', 'ILIKE', "%{$search}%")
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

        return [
            'list_query' => $query,
        ];
    }
}
