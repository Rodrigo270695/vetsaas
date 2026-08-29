<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SendVeterinariaProspectoOutreachBatchJob;
use App\Models\VeterinariaProspecto;
use App\Models\VeterinariaProspectoOutreachSetting;
use App\Models\VeterinariaProspectoScrapeRun;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Prospectos\VeterinariaProspectoOutreachService;
use App\Services\Prospectos\VeterinariaProspectoScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Panel de prospección comercial: clínicas/hospitales veterinarios
 * capturados por scraping diario (cron) o registrados manualmente,
 * para que Rodrigo decida a quién contactar para vender VetSaaS.
 */
final class ProspectoVeterinariaController extends Controller
{
    private const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    public function index(Request $request, PlatformWhatsAppMessenger $messenger): Response
    {
        $search = trim((string) $request->input('search', ''));
        $estado = (string) $request->input('estado', 'todos');
        $tipo = (string) $request->input('tipo', 'todos');
        $departamento = trim((string) $request->input('departamento', ''));
        $provincia = trim((string) $request->input('provincia', ''));
        $distrito = trim((string) $request->input('distrito', ''));
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

        if ($distrito !== '') {
            $query->where('distrito', $distrito);
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

        $outreachSetting = VeterinariaProspectoOutreachSetting::current();
        $elegiblesOutreach = VeterinariaProspecto::query()
            ->where('estado', 'nuevo')
            ->whereNull('mensaje_enviado_at')
            ->whereNotNull('telefono_normalizado')
            ->where('telefono_normalizado', '!=', '')
            ->count();

        return Inertia::render('plataforma/prospectos-veterinarias/index', [
            'prospectos' => $prospectos,
            'filters' => [
                'search' => $search,
                'estado' => $estado,
                'tipo' => $tipo,
                'departamento' => $departamento !== '' ? $departamento : null,
                'provincia' => $provincia !== '' ? $provincia : null,
                'distrito' => $distrito !== '' ? $distrito : null,
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
            'outreach' => [
                'whatsapp_listo' => $messenger->isReady(),
                'automatico_activo' => $outreachSetting->automatico_activo,
                'mensajes_por_corrida' => $outreachSetting->mensajes_por_corrida,
                'hora_envio' => $outreachSetting->hora_envio,
                'ultima_corrida_at' => optional($outreachSetting->ultima_corrida_at)->toIso8601String(),
                'elegibles' => $elegiblesOutreach,
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
     * Departamentos → provincias y → distritos realmente presentes en la
     * data (para los filtros en cascada). Se calcula sobre TODA la tabla,
     * no solo el resultado filtrado, así el usuario siempre ve todas las
     * opciones disponibles.
     *
     * El distrito se agrupa solo por departamento (no por provincia):
     * en la práctica casi toda la data con distrito es de Lima, que
     * siempre cae en la provincia "Lima", así que exigir elegir provincia
     * antes de distrito sería un clic redundante.
     *
     * @return array{provincias: array<string, list<string>>, distritos: array<string, list<string>>}
     */
    private function geoFiltroOptions(): array
    {
        $rows = VeterinariaProspecto::query()
            ->whereNotNull('departamento')
            ->select('departamento', 'provincia', 'distrito')
            ->distinct()
            ->orderBy('departamento')
            ->orderBy('provincia')
            ->orderBy('distrito')
            ->get();

        $provincias = [];
        $distritos = [];
        foreach ($rows as $row) {
            $dep = $row->departamento;
            $provincias[$dep] ??= [];
            $distritos[$dep] ??= [];

            if ($row->provincia !== null && ! in_array($row->provincia, $provincias[$dep], true)) {
                $provincias[$dep][] = $row->provincia;
            }

            if ($row->distrito !== null && ! in_array($row->distrito, $distritos[$dep], true)) {
                $distritos[$dep][] = $row->distrito;
            }
        }

        return ['provincias' => $provincias, 'distritos' => $distritos];
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

    /**
     * Envía el mensaje de contacto (IA + WhatsApp) a UN prospecto puntual
     * (botón individual de la fila). Se ejecuta de forma síncrona: es una
     * sola llamada a OpenAI + WhatsApp, dura pocos segundos.
     */
    public function enviarMensaje(
        Request $request,
        VeterinariaProspecto $prospecto,
        VeterinariaProspectoOutreachService $service,
    ): RedirectResponse {
        try {
            $service->enviarIndividual($prospecto, usuarioId: $request->user()?->id);
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo enviar el mensaje: '.$e->getMessage());
        }

        return back()->with('success', "Mensaje enviado a {$prospecto->nombre}.");
    }

    /**
     * Dispara el envío masivo ("Enviar ahora") a los prospectos elegibles.
     * Se procesa en cola (Job) porque el envío real dura varios minutos
     * (delay anti-bloqueo entre cada mensaje).
     */
    public function enviarMasivo(Request $request, PlatformWhatsAppMessenger $messenger): RedirectResponse
    {
        if (! $messenger->isReady()) {
            return back()->with('error', 'OpenWA (WhatsApp de plataforma) no está conectado. Conéctalo antes de enviar.');
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.VeterinariaProspectoOutreachService::MAX_LIMIT],
        ]);

        $limit = $data['limit'] ?? VeterinariaProspectoOutreachSetting::current()->mensajes_por_corrida;

        SendVeterinariaProspectoOutreachBatchJob::dispatch($limit);

        return back()->with(
            'success',
            "Envío en marcha: hasta {$limit} prospectos recibirán su mensaje de contacto en los próximos minutos (con pausas entre cada uno para evitar bloqueos de WhatsApp)."
        );
    }

    /**
     * Guarda la configuración del envío automático diario (checkbox
     * activo, cantidad de mensajes, hora de la corrida).
     */
    public function outreachConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'automatico_activo' => ['required', 'boolean'],
            'mensajes_por_corrida' => [
                'required',
                'integer',
                'min:'.VeterinariaProspectoOutreachSetting::MIN_MENSAJES_POR_CORRIDA,
                'max:'.VeterinariaProspectoOutreachSetting::MAX_MENSAJES_POR_CORRIDA,
            ],
            'hora_envio' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        $setting = VeterinariaProspectoOutreachSetting::current();
        $setting->update([
            'automatico_activo' => $data['automatico_activo'],
            'mensajes_por_corrida' => $data['mensajes_por_corrida'],
            'hora_envio' => $data['hora_envio'],
            'actualizado_por_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Configuración de envío guardada.');
    }
}
