<?php

namespace App\Http\Controllers;

use App\Exports\PacientesImportTemplateXlsx;
use App\Exports\PacientesXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\PacienteRequest;
use App\Models\Cirugia;
use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Propietario;
use App\Models\Receta;
use App\Models\VacunaAplicada;
use App\Services\Clinica\PacienteImportService;
use App\Support\Clinica\PublicClinicalHistoryPayload;
use App\Support\Pacientes\PacienteEspecieRazaCatalogo;
use App\Support\Pdf\HistorialClinicoPdfBuilder;
use App\Tenancy\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PacienteController extends Controller
{
    use LogsAuditExports;
    use ResolvesClinicPdfBranding;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'nombre',
        'especie',
        'propietario',
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

        $query = Paciente::query()->with(['propietario:id,nombres,apellidos,razon_social']);

        if ($sort === 'propietario') {
            $query->leftJoin('propietarios as p', 'p.id', '=', 'pacientes.propietario_id')
                ->orderByRaw(
                    'COALESCE(p.razon_social, TRIM(CONCAT(COALESCE(p.nombres,\'\'),\' \',COALESCE(p.apellidos,\'\')))) '
                    .($directionValid ? $direction : 'asc'),
                )
                ->select('pacientes.*');
        } elseif ($sortValid) {
            $query->orderBy('pacientes.'.$sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('pacientes.created_at');
        } else {
            $query->orderByDesc('pacientes.created_at');
        }

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pacientes.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('pacientes.especie', 'ILIKE', "%{$search}%")
                    ->orWhere('pacientes.microchip', 'ILIKE', "%{$search}%")
                    ->orWhereHas('propietario', function ($q2) use ($search) {
                        $q2->where('nombres', 'ILIKE', "%{$search}%")
                            ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                            ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('telefono', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($estado === 'activo') {
            $query->where('pacientes.activo', true);
        } elseif ($estado === 'inactivo') {
            $query->where('pacientes.activo', false);
        }

        $pacientes = $query->paginate($perPage)->withQueryString();

        $propietariosOpciones = Propietario::query()
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->limit(500)
            ->get(['id', 'nombres', 'apellidos', 'razon_social']);

        return Inertia::render('clinica/pacientes/index', [
            'pacientes' => $pacientes,
            'propietarios_opciones' => $propietariosOpciones,
            'especie_raza_catalogo' => PacienteEspecieRazaCatalogo::payload(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Paciente::count(),
                'activos' => Paciente::where('activo', true)->count(),
                'inactivos' => Paciente::where('activo', false)->count(),
                'coincidencias' => $pacientes->total(),
            ],
        ]);
    }

    public function catalogoEspecieRaza(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('pacientes.view') ?? false, 403);

        return response()->json(PacienteEspecieRazaCatalogo::payload());
    }

    public function show(Request $request, Paciente $paciente): Response
    {
        abort_unless($request->user()?->can('pacientes.view') ?? false, 403);

        $paciente->load(['propietario:id,nombres,apellidos,razon_social,telefono']);

        $user = $request->user();
        $canVerConsultas = $user?->can('historias-clinicas.view') ?? false;
        $canCrearConsulta = $user?->can('historias-clinicas.create') ?? false;
        $canVerVacunas = $user?->can('vacunaciones.view') ?? false;
        $canCrearVacuna = $user?->can('vacunaciones.create') ?? false;
        $canEditarVacuna = $user?->can('vacunaciones.update') ?? false;

        $tz = config('app.timezone');

        $timeline = [];

        if ($canVerConsultas) {
            $hc = HistoriaClinica::query()->where('paciente_id', $paciente->id)->first();
            if ($hc !== null) {
                $consultas = $hc->consultas()
                    ->with([
                        'veterinario:id,name',
                        'recetas' => fn ($q) => $q->withCount('lineas')->orderByDesc('emitida_at'),
                        'pedidosLaboratorio' => fn ($q) => $q
                            ->with(['lineas' => fn ($lq) => $lq->orderBy('orden')])
                            ->withCount('lineas')
                            ->orderByDesc('solicitado_at'),
                        'cirugias' => fn ($q) => $q->orderByDesc('programada_at'),
                        'internamientos' => fn ($q) => $q->orderByDesc('ingreso_at'),
                    ])
                    ->orderByDesc('atendido_at')
                    ->limit(200)
                    ->get();
                foreach ($consultas as $c) {
                    $at = $c->atendido_at;
                    $timeline[] = [
                        'kind' => 'consulta',
                        'id' => $c->id,
                        'ocurrido_at' => $at->toIso8601String(),
                        'titulo' => Str::limit(trim((string) ($c->motivo ?? '')), 120) ?: '—',
                        'cerrada' => $c->cerrada_at !== null,
                        'veterinario' => $c->veterinario?->name,
                        'historia_url' => route('clinica.historias-clinicas', [
                            'editar_consulta' => $c->id,
                            'atendido_desde' => $at->copy()->startOfMonth()->toDateString(),
                            'atendido_hasta' => $at->copy()->endOfMonth()->toDateString(),
                        ]),
                        'pdf_url' => route('clinica.historias-clinicas.consultas.pdf', $c),
                        'whatsapp_url' => route('clinica.historias-clinicas.consultas.whatsapp', $c),
                        'detalle' => [
                            'peso_kg' => $c->peso_kg !== null && trim((string) $c->peso_kg) !== '' ? trim((string) $c->peso_kg) : null,
                            'temperatura_c' => $c->temperatura_c !== null && trim((string) $c->temperatura_c) !== '' ? trim((string) $c->temperatura_c) : null,
                            'fc_lpm' => $c->fc_lpm,
                            'fr_rpm' => $c->fr_rpm,
                            'subjetivo' => $this->timelineTextPreview($c->subjetivo, 800),
                            'objetivo' => $this->timelineTextPreview($c->objetivo, 800),
                            'analisis' => $this->timelineTextPreview($c->analisis, 800),
                            'plan' => $this->timelineTextPreview($c->plan, 800),
                            'vinculos' => [
                                'recetas' => $this->timelineRecetasVinculo($user, $c->recetas, $tz),
                                'laboratorio' => $this->timelineLaboratorioVinculo($user, $c->pedidosLaboratorio, $tz),
                                'cirugias' => $this->timelineCirugiasVinculo($user, $c->cirugias, $tz),
                                'internamientos' => $this->timelineInternamientosVinculo($user, $c->internamientos, $tz),
                            ],
                        ],
                    ];
                }
            }
        }

        if ($canVerVacunas) {
            $vacunas = VacunaAplicada::query()
                ->where('paciente_id', $paciente->id)
                ->with(['veterinario:id,name', 'consulta:id', 'producto:id,nombre,sku'])
                ->orderByDesc('aplicada_at')
                ->limit(200)
                ->get();
            foreach ($vacunas as $v) {
                $ap = $v->aplicada_at;
                $desde = $ap->copy()->startOfMonth()->toDateString();
                $hasta = $ap->copy()->endOfMonth()->toDateString();
                $vacunacionesParams = [
                    'aplicada_desde' => $desde,
                    'aplicada_hasta' => $hasta,
                ];
                if ($canEditarVacuna) {
                    $vacunacionesParams['editar_vacuna_aplicada'] = $v->id;
                }
                $timeline[] = [
                    'kind' => 'aplicacion',
                    'id' => $v->id,
                    'ocurrido_at' => $v->aplicada_at->toIso8601String(),
                    'titulo' => $v->nombre_vacuna,
                    'categoria' => $v->categoria_registro,
                    'consulta_id' => $v->consulta_id,
                    'veterinario' => $v->veterinario?->name,
                    'vacunaciones_url' => route('clinica.vacunaciones.index', $vacunacionesParams),
                    'pdf_url' => route('clinica.vacunaciones.aplicacion-pdf', $v),
                    'detalle' => [
                        'producto_nombre' => $v->producto?->nombre,
                        'producto_sku' => $v->producto?->sku,
                        'lote' => $v->lote !== null && trim((string) $v->lote) !== '' ? trim((string) $v->lote) : null,
                        'numero_dosis' => $v->numero_dosis,
                        'fecha_proxima_sugerida' => $v->fecha_proxima_sugerida?->toDateString(),
                        'esquema_antigenos' => $this->timelineTextPreview($v->esquema_antigenos, 600),
                        'notas' => $this->timelineTextPreview($v->notas, 600),
                    ],
                ];
            }
        }

        usort($timeline, fn (array $a, array $b): int => strcmp((string) $b['ocurrido_at'], (string) $a['ocurrido_at']));

        $consultasParaLab = [];
        if ($canVerConsultas) {
            $hcForLab = HistoriaClinica::query()->where('paciente_id', $paciente->id)->first();
            if ($hcForLab !== null) {
                $consultasParaLab = $hcForLab->consultas()
                    ->orderByDesc('atendido_at')
                    ->limit(30)
                    ->get(['id', 'atendido_at', 'motivo', 'cerrada_at'])
                    ->map(fn ($c): array => [
                        'id' => $c->id,
                        'label' => trim(
                            ($c->atendido_at?->timezone($tz)->format('d/m/Y H:i') ?? '—')
                            .' · '
                            .(Str::limit(trim((string) ($c->motivo ?? '')), 60) ?: 'Consulta')
                        ),
                        'abierta' => $c->cerrada_at === null,
                    ])
                    ->values()
                    ->all();
            }
        }

        $canLabCreate = $user?->can('laboratorio.create') ?? false;

        return Inertia::render('clinica/pacientes/show', [
            'paciente' => $paciente,
            'timeline' => $timeline,
            'consultas_para_lab' => $consultasParaLab,
            'links' => [
                'nueva_consulta' => route('clinica.historias-clinicas', ['nuevo_para_paciente' => $paciente->id]),
                'nueva_aplicacion' => route('clinica.vacunaciones.index', ['prefill_paciente_id' => $paciente->id]),
                'historial_pdf' => ($canVerConsultas || $canVerVacunas)
                    ? route('clinica.pacientes.historial-clinico-pdf', $paciente)
                    : null,
                'historial_whatsapp' => ($canVerConsultas || $canVerVacunas)
                    ? route('clinica.pacientes.historial-clinico-whatsapp', $paciente)
                    : null,
                'laboratorio_rapido' => $canLabCreate
                    ? route('clinica.pacientes.laboratorio-rapido', $paciente)
                    : null,
            ],
            'permisos' => [
                'consultas_ver' => $canVerConsultas,
                'consultas_crear' => $canCrearConsulta,
                'vacunas_ver' => $canVerVacunas,
                'vacunas_crear' => $canCrearVacuna,
                'laboratorio_crear' => $canLabCreate,
            ],
        ]);
    }

    public function storeLaboratorioRapido(Request $request, Paciente $paciente): RedirectResponse
    {
        abort_unless($request->user()?->can('laboratorio.create') ?? false, 403);

        $data = $request->validate([
            'consulta_id' => ['nullable', 'uuid', 'exists:consultas,id'],
            'nombre_examen' => ['required', 'string', 'max:500'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:20000'],
            'documento' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:12288'],
        ]);

        if (! empty($data['consulta_id'])) {
            $consulta = Consulta::query()
                ->with('historiaClinica:id,paciente_id')
                ->find($data['consulta_id']);

            abort_unless(
                $consulta !== null
                && $consulta->historiaClinica !== null
                && (string) $consulta->historiaClinica->paciente_id === (string) $paciente->id,
                422,
            );
        }

        $tid = tenant_id();
        abort_unless($tid !== null, 403);

        $fecha = Carbon::parse($data['fecha']);
        $descripcion = isset($data['descripcion']) ? trim((string) $data['descripcion']) : '';

        DB::transaction(function () use ($request, $paciente, $data, $fecha, $descripcion, $tid): void {
            $consultaId = $data['consulta_id'] ?? null;

            if ($consultaId === null || $consultaId === '') {
                $uid = Auth::id();
                $historia = HistoriaClinica::query()->firstOrCreate(
                    ['paciente_id' => $paciente->id],
                    [
                        'created_by_id' => $uid,
                        'updated_by_id' => $uid,
                    ],
                );

                if ($historia->wasRecentlyCreated === false) {
                    $historia->update(['updated_by_id' => $uid]);
                }

                $examenNombre = Str::limit(trim($data['nombre_examen']), 80, '');
                $consulta = $historia->consultas()->create([
                    'atendido_at' => $fecha,
                    'motivo' => 'Laboratorio: '.$examenNombre,
                    'plan' => $descripcion !== '' ? Str::limit($descripcion, 20000, '') : null,
                    'cerrada_at' => null,
                    'cerrada_por_id' => null,
                    'veterinario_id' => $uid,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
                $consultaId = $consulta->id;
            }

            $pedido = PedidoLaboratorio::query()->create([
                'paciente_id' => $paciente->id,
                'consulta_id' => $consultaId,
                'veterinario_id' => Auth::id(),
                'solicitado_at' => $fecha,
                'estado' => PedidoLaboratorio::ESTADO_COMPLETADO,
                'created_by_id' => Auth::id(),
                'updated_by_id' => Auth::id(),
            ]);

            $file = $request->file('documento');
            $ext = Str::lower((string) ($file->getClientOriginalExtension() ?: 'bin'));
            $safe = Str::lower(Str::random(24)).'.'.$ext;
            $baseDir = 'laboratorio/'.$tid.'/'.$pedido->id;
            $archivoPath = $file->storeAs($baseDir, $safe, 'local');

            PedidoLaboratorioLinea::query()->create([
                'pedido_laboratorio_id' => $pedido->id,
                'nombre_examen' => Str::limit(trim($data['nombre_examen']), 500, ''),
                'indicaciones' => null,
                'resultado' => $descripcion !== '' ? Str::limit($descripcion, 20000, '') : null,
                'resultado_at' => $fecha,
                'resultado_archivo_path' => $archivoPath,
                'resultado_archivo_original_name' => $file->getClientOriginalName(),
                'orden' => 0,
            ]);
        });

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', __('laboratorio.flash.created'));
    }

    public function historialClinicoPdf(Request $request, Paciente $paciente): HttpResponse
    {
        abort_unless($request->user()?->can('pacientes.view') ?? false, 403);

        $canVerConsultas = $request->user()?->can('historias-clinicas.view') ?? false;
        $canVerVacunas = $request->user()?->can('vacunaciones.view') ?? false;
        abort_unless($canVerConsultas || $canVerVacunas, 403);

        return $this->renderHistorialClinicoPdf(
            $request,
            $paciente,
            $canVerConsultas,
            $canVerVacunas,
            true,
        );
    }

    public function publicHistorialClinicoPdf(Request $request, Paciente $paciente): HttpResponse
    {
        return $this->renderHistorialClinicoPdf($request, $paciente, true, true, false);
    }

    public function publicHistorialView(Paciente $paciente): Response
    {
        return Inertia::render(
            'public/historial-clinico',
            PublicClinicalHistoryPayload::forPaciente($paciente),
        );
    }

    private function renderHistorialClinicoPdf(
        Request $request,
        Paciente $paciente,
        bool $canVerConsultas,
        bool $canVerVacunas,
        bool $audit,
    ): HttpResponse {
        $paciente->load(['propietario:id,nombres,apellidos,razon_social']);

        $entries = HistorialClinicoPdfBuilder::make()->entriesForPaciente(
            $paciente,
            $canVerConsultas,
            $canVerVacunas,
        );

        $pdf = Pdf::loadView('pdf.historial-clinico', array_merge(
            $this->clinicPdfBranding(),
            [
                'paciente' => $paciente,
                'propietarioNombre' => $this->propietarioNombreParaPdf($paciente),
                'entries' => $entries,
                'entriesCount' => $entries->count(),
            ],
        ));

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'historial-clinico-'.$slug.'.pdf';

        if ($audit) {
            $this->auditDownload('pacientes', (string) $paciente->getKey(), $paciente->nombre, $filename);
        }

        return $this->respondClinicPdf($request, $pdf, $filename);
    }

    public function store(PacienteRequest $request, TenantManager $tenants): RedirectResponse
    {
        $userId = Auth::id();
        $data = collect($request->validated())->except(['foto', 'clear_foto'])->all();

        $paciente = Paciente::create([
            'propietario_id' => $data['propietario_id'],
            'nombre' => $data['nombre'],
            'especie' => $data['especie'] ?? null,
            'raza' => $data['raza'] ?? null,
            'sexo' => $data['sexo'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'peso_kg' => $data['peso_kg'] ?? null,
            'microchip' => $data['microchip'] ?? null,
            'color' => $data['color'] ?? null,
            'esterilizado' => array_key_exists('esterilizado', $data) ? $data['esterilizado'] : null,
            'notas' => $data['notas'] ?? null,
            'activo' => $data['activo'],
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        $this->applyFoto($paciente, $request, $tenants);

        return back()->with('success', 'Paciente registrado correctamente.');
    }

    public function update(PacienteRequest $request, Paciente $paciente, TenantManager $tenants): RedirectResponse
    {
        $data = collect($request->validated())->except(['propietario_id', 'foto', 'clear_foto'])->all();

        $paciente->update([
            ...$data,
            'updated_by_id' => Auth::id(),
        ]);

        $this->applyFoto($paciente, $request, $tenants);

        return back()->with('success', 'Paciente actualizado correctamente.');
    }

    public function destroy(Paciente $paciente): RedirectResponse
    {
        $paciente->update(['updated_by_id' => Auth::id()]);
        $paciente->delete();

        return back()->with('success', 'Paciente eliminado correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $userId = Auth::id();

        Paciente::whereIn('id', $data['ids'])->update(['updated_by_id' => $userId]);
        $count = Paciente::whereIn('id', $data['ids'])->delete();

        $msg = $count === 1
            ? '1 paciente eliminado correctamente.'
            : "{$count} pacientes eliminados correctamente.";

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

        $query = Paciente::query()->with(['propietario:id,nombres,apellidos,razon_social']);

        if ($sort === 'propietario') {
            $query->leftJoin('propietarios as p', 'p.id', '=', 'pacientes.propietario_id')
                ->orderByRaw(
                    'COALESCE(p.razon_social, TRIM(CONCAT(COALESCE(p.nombres,\'\'),\' \',COALESCE(p.apellidos,\'\')))) '
                    .($directionValid ? $direction : 'asc'),
                )
                ->select('pacientes.*');
        } elseif ($sortValid) {
            $query->orderBy('pacientes.'.$sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('pacientes.created_at');
        } else {
            $query->orderByDesc('pacientes.created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pacientes.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('pacientes.especie', 'ILIKE', "%{$search}%")
                    ->orWhere('pacientes.microchip', 'ILIKE', "%{$search}%")
                    ->orWhereHas('propietario', function ($q2) use ($search) {
                        $q2->where('nombres', 'ILIKE', "%{$search}%")
                            ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                            ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($estado === 'activo') {
            $query->where('pacientes.activo', true);
        } elseif ($estado === 'inactivo') {
            $query->where('pacientes.activo', false);
        }

        $filename = 'pacientes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new PacientesXlsxExport;

        $this->auditExport('pacientes', $filename);

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
        $filename = 'plantilla_pacientes_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function (): void {
            (new PacientesImportTemplateXlsx)->streamTo('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request, PacienteImportService $importService): JsonResponse
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
     * Guarda o elimina la foto de la mascota en disco `public` (namespace por tenant).
     */
    private function applyFoto(Paciente $paciente, PacienteRequest $request, TenantManager $tenants): void
    {
        $disk = Storage::disk('public');

        if (($request->validated('clear_foto') ?? false) === true) {
            if ($paciente->foto_path && $disk->exists($paciente->foto_path)) {
                $disk->delete($paciente->foto_path);
            }
            $paciente->foto_path = null;
            $paciente->save();

            return;
        }

        if (! $request->hasFile('foto')) {
            return;
        }

        $tenant = $tenants->current();
        $slug = $tenant?->slug ?? 'shared';

        $previous = $paciente->foto_path;
        $file = $request->file('foto');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $dir = "tenants/{$slug}/pacientes";

        $disk->putFileAs($dir, $file, $filename, 'public');

        $path = "{$dir}/{$filename}";
        $paciente->foto_path = $path;
        $paciente->save();

        if ($previous && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }
    }

    /**
     * @param  Collection<int, Receta>  $recetas
     * @return list<array{id: string, estado: string, lineas_count: int, url: string}>
     */
    private function timelineRecetasVinculo(?Authenticatable $user, $recetas, string $tz): array
    {
        if ($user === null || ! $user->can('recetas.view')) {
            return [];
        }

        $out = [];
        foreach ($recetas as $r) {
            $out[] = [
                'id' => $r->id,
                'estado' => $r->estado,
                'lineas_count' => (int) ($r->lineas_count ?? 0),
                'url' => $this->recetaHistorialUrl($user, $r, $tz),
            ];
        }

        return $out;
    }

    private function recetaHistorialUrl(Authenticatable $user, Receta $r, string $tz): string
    {
        $at = $r->emitida_at->copy()->timezone($tz);
        $params = [
            'receta_desde' => $at->copy()->startOfMonth()->toDateString(),
            'receta_hasta' => $at->copy()->endOfMonth()->toDateString(),
        ];
        if ($user->can('recetas.update')) {
            $params['editar_receta'] = $r->id;
        }

        return route('clinica.recetas.index', $params);
    }

    /**
     * @param  Collection<int, PedidoLaboratorio>  $pedidos
     * @return list<array{
     *     id: string,
     *     estado: string,
     *     lineas_count: int,
     *     url: string,
     *     lineas: list<array{
     *         id: string,
     *         nombre_examen: string,
     *         resultado: string|null,
     *         resultado_at: string|null,
     *         resultado_archivo_url: string|null,
     *         resultado_archivo_original_name: string|null
     *     }>
     * }>
     */
    private function timelineLaboratorioVinculo(?Authenticatable $user, $pedidos, string $tz): array
    {
        if ($user === null || ! $user->can('laboratorio.view')) {
            return [];
        }

        $out = [];
        foreach ($pedidos as $p) {
            $lineas = [];
            foreach ($p->lineas ?? [] as $linea) {
                $lineas[] = [
                    'id' => $linea->id,
                    'nombre_examen' => $linea->nombre_examen,
                    'resultado' => $linea->resultado !== null && trim((string) $linea->resultado) !== ''
                        ? trim((string) $linea->resultado)
                        : null,
                    'resultado_at' => $linea->resultado_at?->timezone($tz)->toDateString(),
                    'resultado_archivo_url' => $linea->resultado_archivo_url,
                    'resultado_archivo_original_name' => $linea->resultado_archivo_original_name,
                ];
            }

            $out[] = [
                'id' => $p->id,
                'estado' => $p->estado,
                'lineas_count' => (int) ($p->lineas_count ?? count($lineas)),
                'url' => $this->pedidoLaboratorioHistorialUrl($user, $p, $tz),
                'lineas' => $lineas,
            ];
        }

        return $out;
    }

    private function pedidoLaboratorioHistorialUrl(Authenticatable $user, PedidoLaboratorio $p, string $tz): string
    {
        $at = $p->solicitado_at->copy()->timezone($tz);
        $params = [
            'pedido_desde' => $at->copy()->startOfMonth()->toDateString(),
            'pedido_hasta' => $at->copy()->endOfMonth()->toDateString(),
        ];
        if ($user->can('laboratorio.update')) {
            $params['editar_pedido_laboratorio'] = $p->id;
        }

        return route('clinica.laboratorio.index', $params);
    }

    /**
     * @param  Collection<int, Cirugia>  $cirugias
     * @return list<array{id: string, estado: string, titulo: string, url: string}>
     */
    private function timelineCirugiasVinculo(?Authenticatable $user, $cirugias, string $tz): array
    {
        if ($user === null || ! $user->can('cirugias.view')) {
            return [];
        }

        $out = [];
        foreach ($cirugias as $c) {
            $out[] = [
                'id' => $c->id,
                'estado' => $c->estado,
                'titulo' => Str::limit(trim((string) $c->nombre_procedimiento), 160) ?: '—',
                'url' => $this->cirugiaHistorialUrl($user, $c, $tz),
            ];
        }

        return $out;
    }

    private function cirugiaHistorialUrl(Authenticatable $user, Cirugia $c, string $tz): string
    {
        $at = $c->programada_at->copy()->timezone($tz);
        $params = [
            'programada_desde' => $at->copy()->startOfMonth()->toDateString(),
            'programada_hasta' => $at->copy()->endOfMonth()->toDateString(),
        ];
        if ($user->can('cirugias.update')) {
            $params['editar_cirugia'] = $c->id;
        }

        return route('clinica.cirugias.index', $params);
    }

    /**
     * @param  Collection<int, Internamiento>  $internamientos
     * @return list<array{id: string, estado: string, titulo: string, url: string}>
     */
    private function timelineInternamientosVinculo(?Authenticatable $user, $internamientos, string $tz): array
    {
        if ($user === null || ! $user->can('hospitalizacion.view')) {
            return [];
        }

        $out = [];
        foreach ($internamientos as $i) {
            $out[] = [
                'id' => $i->id,
                'estado' => $i->estado,
                'titulo' => Str::limit(trim((string) $i->motivo_ingreso), 160) ?: '—',
                'url' => $this->internamientoHistorialUrl($user, $i, $tz),
            ];
        }

        return $out;
    }

    private function internamientoHistorialUrl(Authenticatable $user, Internamiento $i, string $tz): string
    {
        $at = $i->ingreso_at->copy()->timezone($tz);
        $params = [
            'ingreso_desde' => $at->copy()->startOfMonth()->toDateString(),
            'ingreso_hasta' => $at->copy()->endOfMonth()->toDateString(),
        ];

        return route('clinica.hospitalizacion.show', $i);
    }

    private function timelineTextPreview(?string $value, int $max): ?string
    {
        $t = trim((string) ($value ?? ''));
        if ($t === '') {
            return null;
        }

        return Str::limit($t, $max);
    }
}
