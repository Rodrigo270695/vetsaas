<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VeterinariaProspecto;
use App\Models\VeterinariaProspectoScrapeRun;
use App\Services\Prospectos\VeterinariaProspectoScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de prospección comercial: clínicas/hospitales veterinarios
 * capturados por scraping diario (cron) o registrados manualmente,
 * para que Rodrigo decida a quién contactar para vender VetSaaS.
 */
final class ProspectoVeterinariaController extends Controller
{
    private const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $estado = (string) $request->input('estado', 'todos');
        $tipo = (string) $request->input('tipo', 'todos');
        $departamento = trim((string) $request->input('departamento', ''));
        $provincia = trim((string) $request->input('provincia', ''));
        $sort = (string) $request->input('sort', 'capturado_at');
        $direction = (string) $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->input('per_page', 25);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $sortable = ['capturado_at', 'nombre', 'departamento', 'estado', 'created_at'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'capturado_at';
        }

        // Filtro de fechas: siempre arranca en el mes actual (1ro del mes → hoy).
        $defaultDesde = Carbon::now()->startOfMonth()->toDateString();
        $defaultHasta = Carbon::now()->toDateString();
        $capturadoDesde = $this->parseDateParam($request->query('capturado_desde')) ?? $defaultDesde;
        $capturadoHasta = $this->parseDateParam($request->query('capturado_hasta')) ?? $defaultHasta;

        if ($capturadoDesde > $capturadoHasta) {
            [$capturadoDesde, $capturadoHasta] = [$capturadoHasta, $capturadoDesde];
        }

        $query = VeterinariaProspecto::query();

        $query->whereBetween('capturado_at', [
            Carbon::parse($capturadoDesde)->startOfDay(),
            Carbon::parse($capturadoHasta)->endOfDay(),
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('telefono', 'ilike', "%{$search}%")
                    ->orWhere('correo', 'ilike', "%{$search}%")
                    ->orWhere('departamento', 'ilike', "%{$search}%")
                    ->orWhere('distrito', 'ilike', "%{$search}%");
            });
        }

        if ($estado !== 'todos' && in_array($estado, VeterinariaProspecto::ESTADOS, true)) {
            $query->where('estado', $estado);
        }

        if ($tipo === 'clinica' || $tipo === 'hospital') {
            $query->where('tipo', $tipo);
        }

        if ($departamento !== '') {
            $query->where('departamento', $departamento);
        }

        if ($provincia !== '') {
            $query->where('provincia', $provincia);
        }

        $query->orderBy($sort, $direction);

        $prospectos = $query
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => VeterinariaProspecto::query()->count(),
            'nuevos' => VeterinariaProspecto::query()->where('estado', 'nuevo')->count(),
            'con_telefono' => VeterinariaProspecto::query()->whereNotNull('telefono_normalizado')->count(),
            'con_correo' => VeterinariaProspecto::query()->whereNotNull('correo')->count(),
            'hoy' => VeterinariaProspecto::query()->whereDate('capturado_at', today())->count(),
            'coincidencias' => $prospectos->total(),
        ];

        $ultimaCorrida = VeterinariaProspectoScrapeRun::query()
            ->orderByDesc('iniciado_at')
            ->first();

        return Inertia::render('plataforma/prospectos-veterinarias/index', [
            'prospectos' => $prospectos,
            'filters' => [
                'search' => $search,
                'estado' => $estado,
                'tipo' => $tipo,
                'departamento' => $departamento !== '' ? $departamento : null,
                'provincia' => $provincia !== '' ? $provincia : null,
                'capturado_desde' => $capturadoDesde,
                'capturado_hasta' => $capturadoHasta,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'fecha_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
            ],
            'geo_filtro' => $this->geoFiltroOptions(),
            'departamentos_catalogo' => $this->departamentosCatalogo(),
            'stats' => $stats,
            'estados' => VeterinariaProspecto::ESTADOS,
            'ultima_corrida' => $ultimaCorrida ? [
                'iniciado_at' => optional($ultimaCorrida->iniciado_at)->toIso8601String(),
                'origen' => $ultimaCorrida->origen,
                'estado' => $ultimaCorrida->estado,
                'nuevos' => $ultimaCorrida->nuevos,
                'duplicados' => $ultimaCorrida->duplicados,
                'ubicaciones_visitadas' => $ultimaCorrida->ubicaciones_visitadas,
            ] : null,
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
     * Departamentos → provincias realmente presentes en la data (para el
     * filtro en cascada). Se calcula sobre TODA la tabla, no solo el
     * resultado filtrado, así el usuario siempre ve todas las opciones.
     *
     * @return array<string, list<string>>
     */
    private function geoFiltroOptions(): array
    {
        $rows = VeterinariaProspecto::query()
            ->whereNotNull('departamento')
            ->select('departamento', 'provincia')
            ->distinct()
            ->orderBy('departamento')
            ->orderBy('provincia')
            ->get();

        $options = [];
        foreach ($rows as $row) {
            $dep = $row->departamento;
            $options[$dep] ??= [];
            if ($row->provincia !== null && ! in_array($row->provincia, $options[$dep], true)) {
                $options[$dep][] = $row->provincia;
            }
        }

        return $options;
    }

    /**
     * Departamentos disponibles en el catálogo de scraping (config), para
     * que el usuario pueda dirigir manualmente una corrida a uno en
     * particular desde el botón "Traer nuevos".
     *
     * @return list<string>
     */
    private function departamentosCatalogo(): array
    {
        $deps = [];
        foreach (config('prospectos.ubicaciones', []) as $loc) {
            $dep = $loc['departamento'] ?? null;
            if ($dep !== null && ! in_array($dep, $deps, true)) {
                $deps[] = $dep;
            }
        }

        sort($deps);

        return $deps;
    }

    /**
     * Registro manual de un prospecto (botón "Agregar manual" en el panel).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:195'],
            'tipo' => ['required', Rule::in([VeterinariaProspecto::TIPO_CLINICA, VeterinariaProspecto::TIPO_HOSPITAL])],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:190'],
            'direccion' => ['nullable', 'string', 'max:295'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'capturado_at' => ['nullable', 'date'],
        ]);

        VeterinariaProspecto::query()->create([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'telefono' => $data['telefono'] ?? null,
            'telefono_normalizado' => VeterinariaProspecto::normalizarTelefono($data['telefono'] ?? null),
            'correo' => $data['correo'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'departamento' => $data['departamento'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'distrito' => $data['distrito'] ?? null,
            'origen' => VeterinariaProspecto::ORIGEN_MANUAL,
            'estado' => 'nuevo',
            'capturado_at' => $data['capturado_at'] ?? now(),
            'creado_por_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Prospecto registrado.');
    }

    /**
     * Dispara una corrida de scraping manual ("Traer nuevos ahora") desde
     * el panel. Corre de forma síncrona (dura pocos segundos): el frontend
     * muestra un loader mientras dura el request.
     *
     * Por defecto (sin `departamento`) reparte la búsqueda en varios
     * departamentos del país en una sola corrida; si se indica uno, se
     * dirige la búsqueda solo a ese departamento.
     */
    public function scrapeNow(Request $request, VeterinariaProspectoScraperService $scraper): RedirectResponse
    {
        $data = $request->validate([
            'departamento' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $scraper->run(
            origen: 'manual',
            iniciadoPorId: $request->user()?->id,
            departamento: $data['departamento'] ?? null,
        );

        $mensaje = $result['nuevos'] > 0
            ? "Se encontraron {$result['nuevos']} prospectos nuevos ({$result['duplicados']} ya existían)."
            : "No se encontraron prospectos nuevos ({$result['duplicados']} ya existían).";

        return back()->with('success', $mensaje);
    }

    public function updateEstado(Request $request, VeterinariaProspecto $prospecto): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(VeterinariaProspecto::ESTADOS)],
        ]);

        $prospecto->update(['estado' => $data['estado']]);

        return back()->with('success', 'Estado actualizado.');
    }
}
