<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePedidoLaboratorioRequest;
use App\Http\Requests\UpdatePedidoLaboratorioRequest;
use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaboratorioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'solicitado_at',
        'paciente',
        'lineas',
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

        $pedidoDesde = $this->parseDateParam($request->query('pedido_desde'));
        $pedidoHasta = $this->parseDateParam($request->query('pedido_hasta'));

        if ($pedidoDesde === null || $pedidoHasta === null) {
            $pedidoDesde = $defaultDesde;
            $pedidoHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($pedidoDesde > $pedidoHasta) {
                [$pedidoDesde, $pedidoHasta] = [$pedidoHasta, $pedidoDesde];
            }
            $fueraDelMesActual = ($pedidoDesde !== $defaultDesde) || ($pedidoHasta !== $defaultHasta);
        }

        $pedidoAbrirEditar = null;
        $editarPedidoRaw = $request->query('editar_pedido_laboratorio');
        if (is_string($editarPedidoRaw) && Str::isUuid($editarPedidoRaw) && ($request->user()?->can('laboratorio.update') ?? false)) {
            $canAuditEdit = $request->user()?->can('audit-trail.view') ?? false;
            $qEdit = PedidoLaboratorio::query()
                ->withCount('lineas')
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'consulta:id,atendido_at,cerrada_at,historia_clinica_id',
                    'consulta.historiaClinica:id,paciente_id',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                    'lineas' => fn ($q) => $q->orderBy('orden'),
                ])
                ->whereKey($editarPedidoRaw);

            if ($canAuditEdit) {
                $qEdit->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $pedModel = $qEdit->first();

            if ($pedModel !== null) {
                $pedidoAbrirEditar = $pedModel;
                $atPed = $pedModel->solicitado_at->copy()->timezone($tz);
                $pedidoDesde = $atPed->copy()->startOfMonth()->toDateString();
                $pedidoHasta = $atPed->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($pedidoDesde !== $defaultDesde) || ($pedidoHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($pedidoDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($pedidoHasta, $tz)->endOfDay();

        $tenantId = tenant_id();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = PedidoLaboratorio::query()
            ->whereBetween('pedidos_laboratorio.solicitado_at', [$inicioRango, $finRango]);

        $estadoFiltroRaw = trim((string) ($request->query('estado') ?? ''));
        $estadoFiltro = in_array($estadoFiltroRaw, PedidoLaboratorio::ESTADOS, true) ? $estadoFiltroRaw : null;
        if ($estadoFiltro !== null) {
            $query->where('pedidos_laboratorio.estado', $estadoFiltro);
        }

        $this->applyPedidoLaboratorioListFilters($query, $search);

        $query
            ->withCount('lineas')
            ->with([
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'consulta:id,atendido_at,historia_clinica_id',
                'consulta.historiaClinica:id,paciente_id',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
                'lineas' => fn ($q) => $q->orderBy('orden'),
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as lab_pac', 'lab_pac.id', '=', 'pedidos_laboratorio.paciente_id')
                ->orderBy('lab_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('pedidos_laboratorio.solicitado_at')
                ->select('pedidos_laboratorio.*');
        } elseif ($sort === 'lineas') {
            $query->orderBy('lineas_count', $directionValid ? $direction : 'desc')
                ->orderByDesc('pedidos_laboratorio.solicitado_at');
        } elseif ($sortValid) {
            $query->orderBy('pedidos_laboratorio.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'solicitado_at') {
                $query->orderByDesc('pedidos_laboratorio.solicitado_at');
            }
        } else {
            $query->orderByDesc('pedidos_laboratorio.solicitado_at');
        }

        $pedidos = $query->paginate($perPage)->withQueryString();

        $totalEnRango = PedidoLaboratorio::query()
            ->whereBetween('solicitado_at', [$inicioRango, $finRango])
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

        return Inertia::render('clinica/laboratorio/index', [
            'pedidos' => $pedidos,
            'pedido_abrir_editar' => $pedidoAbrirEditar,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'consultas_opciones' => $consultasOpciones,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'pedido_desde' => $pedidoDesde,
                'pedido_hasta' => $pedidoHasta,
                'estado' => $estadoFiltro ?? '',
            ],
            'pedido_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $pedidos->total(),
            ],
        ]);
    }

    public function store(StorePedidoLaboratorioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $lineas = $data['lineas'];
        unset($data['lineas']);

        $data['estado'] = $data['estado'] ?? PedidoLaboratorio::ESTADO_BORRADOR;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        DB::transaction(function () use ($data, $lineas, $request): void {
            /** @var PedidoLaboratorio $pedido */
            $pedido = PedidoLaboratorio::query()->create($data);
            $this->syncLineas($pedido, $lineas, $request);
        });

        return redirect()
            ->route('clinica.laboratorio.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'pedido_desde', 'pedido_hasta', 'estado',
            ]))
            ->with('success', __('laboratorio.flash.created'));
    }

    public function update(UpdatePedidoLaboratorioRequest $request, PedidoLaboratorio $pedidoLaboratorio): RedirectResponse
    {
        $data = $request->validated();
        $lineas = $data['lineas'];
        unset($data['lineas']);

        $data['updated_by_id'] = Auth::id();

        DB::transaction(function () use ($pedidoLaboratorio, $data, $lineas, $request): void {
            $archivosPrevios = $pedidoLaboratorio->lineas()
                ->orderBy('orden')
                ->get(['resultado_archivo_path', 'resultado_archivo_original_name'])
                ->values()
                ->all();

            $pedidoLaboratorio->fill($data);
            $pedidoLaboratorio->save();

            PedidoLaboratorioLinea::withoutEvents(function () use ($pedidoLaboratorio): void {
                $pedidoLaboratorio->lineas()->delete();
            });

            $this->syncLineas($pedidoLaboratorio, $lineas, $request, $archivosPrevios);
        });

        return redirect()
            ->route('clinica.laboratorio.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'pedido_desde', 'pedido_hasta', 'estado',
            ]))
            ->with('success', __('laboratorio.flash.updated'));
    }

    public function downloadResultadoArchivo(Request $request, PedidoLaboratorioLinea $linea): BinaryFileResponse
    {
        abort_unless($request->user()?->can('laboratorio.view') ?? false, 403);

        return $this->streamResultadoArchivo($linea);
    }

    public function publicDownloadResultadoArchivo(PedidoLaboratorioLinea $linea): BinaryFileResponse
    {
        return $this->streamResultadoArchivo($linea);
    }

    private function streamResultadoArchivo(PedidoLaboratorioLinea $linea): BinaryFileResponse
    {
        $tid = tenant_id();
        if ($tid === null || $linea->resultado_archivo_path === null) {
            abort(404);
        }

        $expectedPrefix = 'laboratorio/'.$tid.'/';
        if (! str_starts_with((string) $linea->resultado_archivo_path, $expectedPrefix)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($linea->resultado_archivo_path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('local')->path((string) $linea->resultado_archivo_path);
        $downloadName = $linea->resultado_archivo_original_name
            ?: ('resultado-'.Str::lower(Str::substr((string) $linea->id, 0, 8)).'.bin');

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '\\"', $downloadName).'"',
        ]);
    }

    public function destroy(Request $request, PedidoLaboratorio $pedidoLaboratorio): RedirectResponse
    {
        abort_unless($request->user()?->can('laboratorio.delete') ?? false, 403);

        $pedidoLaboratorio->delete();

        return redirect()
            ->route('clinica.laboratorio.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'pedido_desde', 'pedido_hasta', 'estado',
            ]))
            ->with('success', __('laboratorio.flash.deleted'));
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

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<PedidoLaboratorioLinea|array{resultado_archivo_path: ?string, resultado_archivo_original_name: ?string}>  $archivosPrevios
     */
    private function syncLineas(
        PedidoLaboratorio $pedido,
        array $lineas,
        Request $request,
        array $archivosPrevios = [],
    ): void {
        $tid = tenant_id();
        if ($tid === null) {
            abort(403);
        }

        $archivosReutilizados = [];

        foreach ($lineas as $idx => $row) {
            $nombre = Str::limit(trim((string) ($row['nombre_examen'] ?? '')), 500, '');
            if ($nombre === '') {
                continue;
            }
            $ind = isset($row['indicaciones']) && is_string($row['indicaciones']) ? trim($row['indicaciones']) : '';
            $res = isset($row['resultado']) && is_string($row['resultado']) ? trim($row['resultado']) : '';

            $archivoPath = null;
            $archivoName = null;
            $previo = $archivosPrevios[$idx] ?? null;
            $prevPath = is_array($previo)
                ? ($previo['resultado_archivo_path'] ?? null)
                : ($previo?->resultado_archivo_path ?? null);
            $prevName = is_array($previo)
                ? ($previo['resultado_archivo_original_name'] ?? null)
                : ($previo?->resultado_archivo_original_name ?? null);
            $clearArchivo = filter_var($row['clear_resultado_archivo'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($request->hasFile("lineas.{$idx}.resultado_archivo")) {
                $file = $request->file("lineas.{$idx}.resultado_archivo");
                $ext = Str::lower((string) ($file->getClientOriginalExtension() ?: 'bin'));
                $safe = Str::lower(Str::random(24)).'.'.$ext;
                $baseDir = 'laboratorio/'.$tid.'/'.$pedido->id;
                $archivoPath = $file->storeAs($baseDir, $safe, 'local');
                $archivoName = $file->getClientOriginalName();
                $this->deleteArchivoSiExiste($tid, $prevPath);
            } elseif ($clearArchivo) {
                $this->deleteArchivoSiExiste($tid, $prevPath);
            } elseif (is_string($prevPath) && $prevPath !== '') {
                $archivoPath = $prevPath;
                $archivoName = is_string($prevName) && $prevName !== '' ? $prevName : null;
                $archivosReutilizados[] = $prevPath;
            }

            PedidoLaboratorioLinea::query()->create([
                'pedido_laboratorio_id' => $pedido->id,
                'nombre_examen' => $nombre,
                'indicaciones' => $ind !== '' ? Str::limit($ind, 2000, '') : null,
                'resultado' => $res !== '' ? Str::limit($res, 20000, '') : null,
                'resultado_at' => isset($row['resultado_at']) && $row['resultado_at'] !== null && $row['resultado_at'] !== ''
                    ? Carbon::parse((string) $row['resultado_at'])
                    : null,
                'resultado_archivo_path' => $archivoPath,
                'resultado_archivo_original_name' => $archivoName,
                'orden' => (int) ($row['orden'] ?? $idx),
            ]);
        }

        if ($archivosPrevios !== []) {
            foreach ($archivosPrevios as $previo) {
                $prevPath = is_array($previo)
                    ? ($previo['resultado_archivo_path'] ?? null)
                    : ($previo->resultado_archivo_path ?? null);

                if (! is_string($prevPath) || $prevPath === '') {
                    continue;
                }

                if (! in_array($prevPath, $archivosReutilizados, true)) {
                    $this->deleteArchivoSiExiste($tid, $prevPath);
                }
            }
        }
    }

    private function deleteArchivoSiExiste(string $tenantId, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $expectedPrefix = 'laboratorio/'.$tenantId.'/';
        if (! str_starts_with($path, $expectedPrefix)) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function applyPedidoLaboratorioListFilters(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('pedidos_laboratorio.observaciones', 'ILIKE', "%{$search}%")
                ->orWhere('pedidos_laboratorio.estado', 'ILIKE', "%{$search}%")
                ->orWhere('pedidos_laboratorio.laboratorio_destino', 'ILIKE', "%{$search}%")
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
                })
                ->orWhereHas('lineas', function ($q6) use ($search) {
                    $q6->where('nombre_examen', 'ILIKE', "%{$search}%")
                        ->orWhere('indicaciones', 'ILIKE', "%{$search}%")
                        ->orWhere('resultado', 'ILIKE', "%{$search}%");
                });
        });
    }
}
