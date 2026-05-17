<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\StoreRecetaRequest;
use App\Http\Requests\UpdateRecetaRequest;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\RecetaLinea;
use App\Models\Sede;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RecetaController extends Controller
{
    use ResolvesClinicPdfBranding;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'emitida_at',
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

        $recetaDesde = $this->parseDateParam($request->query('receta_desde'));
        $recetaHasta = $this->parseDateParam($request->query('receta_hasta'));

        if ($recetaDesde === null || $recetaHasta === null) {
            $recetaDesde = $defaultDesde;
            $recetaHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($recetaDesde > $recetaHasta) {
                [$recetaDesde, $recetaHasta] = [$recetaHasta, $recetaDesde];
            }
            $fueraDelMesActual = ($recetaDesde !== $defaultDesde) || ($recetaHasta !== $defaultHasta);
        }

        $recetaAbrirEditar = null;
        $editarRecetaRaw = $request->query('editar_receta');
        if (is_string($editarRecetaRaw) && Str::isUuid($editarRecetaRaw) && ($request->user()?->can('recetas.update') ?? false)) {
            $canAudit = $request->user()?->can('audit-trail.view') ?? false;
            $qEdit = Receta::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'consulta:id,atendido_at,cerrada_at,historia_clinica_id',
                    'consulta.historiaClinica:id,paciente_id',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                    'lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
                ])
                ->withCount('lineas')
                ->whereKey($editarRecetaRaw);

            if ($canAudit) {
                $qEdit->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $recModel = $qEdit->first();

            if ($recModel !== null) {
                $recetaAbrirEditar = $recModel;
                $atRec = $recModel->emitida_at->copy()->timezone($tz);
                $recetaDesde = $atRec->copy()->startOfMonth()->toDateString();
                $recetaHasta = $atRec->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($recetaDesde !== $defaultDesde) || ($recetaHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($recetaDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($recetaHasta, $tz)->endOfDay();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Receta::query()
            ->withCount('lineas')
            ->with([
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'consulta:id,atendido_at,historia_clinica_id',
                'consulta.historiaClinica:id,paciente_id',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
                'lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('recetas.emitida_at', [$inicioRango, $finRango]);

        $estadoFiltroRaw = trim((string) ($request->query('estado') ?? ''));
        $estadoFiltro = in_array($estadoFiltroRaw, Receta::ESTADOS, true) ? $estadoFiltroRaw : null;
        if ($estadoFiltro !== null) {
            $query->where('recetas.estado', $estadoFiltro);
        }

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as rec_pac', 'rec_pac.id', '=', 'recetas.paciente_id')
                ->orderBy('rec_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('recetas.emitida_at')
                ->select('recetas.*');
        } elseif ($sort === 'lineas') {
            $query->orderBy('lineas_count', $directionValid ? $direction : 'desc')
                ->orderByDesc('recetas.emitida_at');
        } elseif ($sortValid) {
            $query->orderBy('recetas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'emitida_at') {
                $query->orderByDesc('recetas.emitida_at');
            }
        } else {
            $query->orderByDesc('recetas.emitida_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recetas.observaciones', 'ILIKE', "%{$search}%")
                    ->orWhere('recetas.estado', 'ILIKE', "%{$search}%")
                    ->orWhereHas('paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    })
                    ->orWhereHas('lineas', function ($q4) use ($search) {
                        $q4->where('nombre_medicamento', 'ILIKE', "%{$search}%")
                            ->orWhere('posologia', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $recetas = $query->paginate($perPage)->withQueryString();

        $totalEnRango = Receta::query()
            ->whereBetween('emitida_at', [$inicioRango, $finRango])
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

        $consultasOpciones = Consulta::query()
            ->whereNull('cerrada_at')
            ->with([
                'historiaClinica:id,paciente_id',
                'historiaClinica.paciente:id,nombre',
            ])
            ->orderByDesc('atendido_at')
            ->limit(150)
            ->get(['id', 'atendido_at', 'historia_clinica_id']);

        return Inertia::render('clinica/recetas/index', [
            'recetas' => $recetas,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'consultas_opciones' => $consultasOpciones,
            'receta_abrir_editar' => $recetaAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'receta_desde' => $recetaDesde,
                'receta_hasta' => $recetaHasta,
                'estado' => $estadoFiltro ?? '',
            ],
            'receta_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $recetas->total(),
            ],
        ]);
    }

    public function productosMedicamento(Request $request): JsonResponse
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
     * PDF de la receta para entrega al titular (vista en navegador o impresión).
     * Query `?download=1`: descarga del archivo.
     */
    public function pdf(Request $request, Receta $receta): Response
    {
        abort_unless($request->user()?->can('recetas.view') ?? false, 403);

        $receta->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'consulta:id,atendido_at',
        ]);

        $clinic = ClinicSetting::current();
        $logoDataUri = $this->clinicLogoDataUri($clinic);
        $colorPrimario = $this->sanitizeHexColor($clinic->color_primario, '#166534');
        $colorSecundario = $this->sanitizeHexColor($clinic->color_secundario, '#f0fdf4');

        $clinicNombre = $clinic->nombre_comercial
            ?: $clinic->razon_social
            ?: (string) config('app.name', 'Clínica');

        $tz = (string) config('app.timezone', 'UTC');
        $emitidaAt = $receta->emitida_at !== null
            ? $receta->emitida_at->copy()->timezone($tz)->format('d/m/Y H:i')
            : '—';

        $consultaAt = '—';
        if ($receta->consulta?->atendido_at !== null) {
            $consultaAt = Carbon::parse($receta->consulta->atendido_at)->timezone($tz)->format('d/m/Y H:i');
        }

        $propietarioNombre = $this->propietarioNombreParaPdf($receta->paciente);
        $generadoEn = now($tz)->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.receta', [
            'clinicNombre' => $clinicNombre,
            'logoDataUri' => $logoDataUri,
            'colorPrimario' => $colorPrimario,
            'colorSecundario' => $colorSecundario,
            'clinicEmail' => $clinic->email_institucional,
            'clinicTelefono' => $clinic->telefono_principal,
            'clinicWeb' => $clinic->web_url,
            'clinicDireccion' => $clinic->direccion_fiscal,
            'receta' => $receta,
            'propietarioNombre' => $propietarioNombre,
            'emitidaAt' => $emitidaAt,
            'consultaAt' => $consultaAt,
            'generadoEn' => $generadoEn,
        ]);
        $pdf->setPaper('a4', 'portrait');

        $slug = Str::slug($receta->paciente->nombre ?? 'paciente') ?: 'paciente';
        $filename = 'receta-'.$slug.'.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function store(StoreRecetaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $lineas = $data['lineas'];
        unset($data['lineas']);

        $data['estado'] = $data['estado'] ?? Receta::ESTADO_BORRADOR;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        DB::transaction(function () use ($data, $lineas): void {
            /** @var Receta $receta */
            $receta = Receta::query()->create($data);
            $this->syncLineas($receta, $lineas);
        });

        return redirect()
            ->route('clinica.recetas.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'receta_desde', 'receta_hasta', 'estado',
            ]))
            ->with('success', __('recetas.flash.created'));
    }

    public function update(UpdateRecetaRequest $request, Receta $receta): RedirectResponse
    {
        $data = $request->validated();
        $lineas = $data['lineas'];
        unset($data['lineas']);

        $data['updated_by_id'] = Auth::id();

        DB::transaction(function () use ($receta, $data, $lineas): void {
            $receta->fill($data);
            $receta->save();
            $receta->lineas()->delete();
            $this->syncLineas($receta, $lineas);
        });

        return redirect()
            ->route('clinica.recetas.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'receta_desde', 'receta_hasta', 'estado',
            ]))
            ->with('success', __('recetas.flash.updated'));
    }

    public function destroy(Request $request, Receta $receta): RedirectResponse
    {
        abort_unless($request->user()?->can('recetas.delete') ?? false, 403);

        $receta->delete();

        return redirect()
            ->route('clinica.recetas.index', $this->listIndexQuery($request, [
                'search', 'per_page', 'sort', 'direction', 'receta_desde', 'receta_hasta', 'estado',
            ]))
            ->with('success', __('recetas.flash.deleted'));
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
     */
    private function syncLineas(Receta $receta, array $lineas): void
    {
        foreach ($lineas as $idx => $row) {
            $nombre = Str::limit(trim((string) ($row['nombre_medicamento'] ?? '')), 500, '');
            if ($nombre === '') {
                continue;
            }
            $pos = isset($row['posologia']) && is_string($row['posologia']) ? trim($row['posologia']) : '';
            $ins = isset($row['instrucciones']) && is_string($row['instrucciones']) ? trim($row['instrucciones']) : '';
            RecetaLinea::query()->create([
                'receta_id' => $receta->id,
                'producto_id' => $row['producto_id'] ?? null,
                'nombre_medicamento' => $nombre,
                'posologia' => $pos !== '' ? Str::limit($pos, 2000, '') : null,
                'duracion_dias' => $row['duracion_dias'] ?? null,
                'instrucciones' => $ins !== '' ? Str::limit($ins, 2000, '') : null,
                'orden' => (int) ($row['orden'] ?? $idx),
            ]);
        }
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
