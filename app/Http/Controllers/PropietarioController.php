<?php

namespace App\Http\Controllers;

use App\Exports\PropietariosImportTemplateXlsx;
use App\Exports\PropietariosXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Controllers\Concerns\RespondsToApiPeruConsulta;
use App\Http\Requests\PropietarioRequest;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Services\Clinica\PropietarioImportService;
use App\Services\Integrations\ApiPeruDniService;
use App\Services\Integrations\ApiPeruRucService;
use App\Support\Pacientes\PacienteEspecieRazaCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropietarioController extends Controller
{
    use LogsAuditExports;
    use RespondsToApiPeruConsulta;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'nombres',
        'apellidos',
        'numero_documento',
        'email',
        'telefono',
        'activo',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'activo', 'inactivo'];

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

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Propietario::query()->withCount('pacientes');

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->with([
            'distritoModel:id,name,provincia_id',
            'distritoModel.provincia:id,name,departamento_id',
            'distritoModel.provincia.departamento:id,name',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'ILIKE', "%{$search}%")
                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                    ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                    ->orWhere('numero_documento', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('telefono', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activo') {
            $query->where('activo', true);
        } elseif ($estado === 'inactivo') {
            $query->where('activo', false);
        }

        $propietarios = $query->paginate($perPage)->withQueryString();

        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('clinica/propietarios/index', [
            'propietarios' => $propietarios,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Propietario::count(),
                'activos' => Propietario::where('activo', true)->count(),
                'inactivos' => Propietario::where('activo', false)->count(),
                'coincidencias' => $propietarios->total(),
            ],
            'departamentos' => $departamentos,
        ]);
    }

    public function show(Request $request, Propietario $propietario): Response
    {
        $propietario->load([
            'distritoModel:id,name,provincia_id',
            'distritoModel.provincia:id,name,departamento_id',
            'distritoModel.provincia.departamento:id,name',
        ]);

        if ($request->user()?->can('audit-trail.view')) {
            $propietario->load([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $pacienteQuery = Paciente::query()
            ->where('propietario_id', $propietario->id);

        if ($request->user()?->can('audit-trail.view')) {
            $pacienteQuery->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $pacientes = $pacienteQuery
            ->orderByDesc('created_at')
            ->limit(120)
            ->get();

        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('clinica/propietarios/show', [
            'propietario' => $propietario,
            'pacientes' => $pacientes,
            'departamentos' => $departamentos,
            'especie_raza_catalogo' => PacienteEspecieRazaCatalogo::payload(),
        ]);
    }

    /**
     * Consulta RUC en SUNAT (apiperu.dev) desde el servidor.
     */
    public function consultaRuc(Request $request, ApiPeruRucService $apiPeru): JsonResponse
    {
        $ruc = preg_replace('/\D+/', '', (string) $request->query('ruc', ''));
        $request->merge(['ruc' => $ruc]);

        $validated = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^[0-9]{11}$/'],
        ]);

        return $this->consultaApiPeruResponse(
            fn () => $apiPeru->consultar($validated['ruc']),
        );
    }

    /**
     * Consulta DNI (apiperu.dev) desde el servidor.
     */
    public function consultaDni(Request $request, ApiPeruDniService $apiPeru): JsonResponse
    {
        $dni = preg_replace('/\D+/', '', (string) $request->query('dni', ''));
        $request->merge(['dni' => $dni]);

        $validated = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
        ]);

        return $this->consultaApiPeruResponse(
            fn () => $apiPeru->consultar($validated['dni']),
        );
    }

    public function store(PropietarioRequest $request): RedirectResponse
    {
        $userId = Auth::id();
        $data = $this->hydrateLocationFromDistrito($request->validated());

        Propietario::create([
            ...$data,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Propietario creado correctamente.');
    }

    public function update(PropietarioRequest $request, Propietario $propietario): RedirectResponse
    {
        $data = $this->hydrateLocationFromDistrito($request->validated());

        $propietario->update([
            ...$data,
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Propietario actualizado correctamente.');
    }

    public function destroy(Propietario $propietario): RedirectResponse
    {
        $propietario->update(['updated_by_id' => Auth::id()]);
        $propietario->delete();

        return redirect()
            ->route('clinica.propietarios.index')
            ->with('success', 'Propietario eliminado correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $userId = Auth::id();

        Propietario::whereIn('id', $data['ids'])->update(['updated_by_id' => $userId]);
        $count = Propietario::whereIn('id', $data['ids'])->delete();

        $msg = $count === 1
            ? '1 propietario eliminado correctamente.'
            : "{$count} propietarios eliminados correctamente.";

        return back()->with('success', $msg);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = Propietario::query();

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'ILIKE', "%{$search}%")
                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                    ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                    ->orWhere('numero_documento', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('telefono', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activo') {
            $query->where('activo', true);
        } elseif ($estado === 'inactivo') {
            $query->where('activo', false);
        }

        $filename = 'propietarios-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new PropietariosXlsxExport;

        $this->auditExport('propietarios', $filename);

        return response()->streamDownload(
            function () use ($exporter, $query) {
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

    public function downloadImportTemplate(): StreamedResponse
    {
        $filename = 'plantilla_propietarios_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function (): void {
            (new PropietariosImportTemplateXlsx)->streamTo('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request, PropietarioImportService $importService): JsonResponse
    {
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateLocationFromDistrito(array $data): array
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
}
