<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\StoreConsultaHistoriaRequest;
use App\Http\Requests\UpdateConsultaHistoriaRequest;
use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Support\Pdf\HistorialClinicoPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ConsultaHistoriaController extends Controller
{
    use ResolvesClinicPdfBranding;
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'atendido_at',
        'created_at',
        'paciente',
    ];

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

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $soloAbiertas = filter_var($request->query('solo_abiertas'), FILTER_VALIDATE_BOOLEAN);
        $estadoRaw = $request->query('estado');
        $estado = in_array($estadoRaw, ['abierta', 'cerrada', 'todas'], true) ? $estadoRaw : 'todas';
        if ($soloAbiertas) {
            $estado = 'abierta';
        }

        $filtrarAbiertas = $estado === 'abierta';
        $filtrarCerradas = $estado === 'cerrada';
        $fechasExplicitas = $request->has('atendido_desde') || $request->has('atendido_hasta');
        $omitirRangoMes = $filtrarAbiertas && ! $fechasExplicitas;

        $atendidoDesde = $this->parseDateParam($request->query('atendido_desde'));
        $atendidoHasta = $this->parseDateParam($request->query('atendido_hasta'));

        if ($omitirRangoMes) {
            $atendidoDesde = null;
            $atendidoHasta = null;
            $atencionFueraDelMesActual = false;
            $inicioRango = null;
            $finRango = null;
        } elseif ($atendidoDesde === null || $atendidoHasta === null) {
            $atendidoDesde = $defaultDesde;
            $atendidoHasta = $defaultHasta;
            $atencionFueraDelMesActual = false;
            $inicioRango = Carbon::parse($atendidoDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($atendidoHasta, $tz)->endOfDay();
        } else {
            if ($atendidoDesde > $atendidoHasta) {
                [$atendidoDesde, $atendidoHasta] = [$atendidoHasta, $atendidoDesde];
            }
            $atencionFueraDelMesActual = ($atendidoDesde !== $defaultDesde) || ($atendidoHasta !== $defaultHasta);
            $inicioRango = Carbon::parse($atendidoDesde, $tz)->startOfDay();
            $finRango = Carbon::parse($atendidoHasta, $tz)->endOfDay();
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Consulta::query()
            ->with([
                'historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                'historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                'veterinario:id,name',
                'cerradaPor:id,name',
                'planTratamiento.lineas.producto:id,nombre,unidad,sku',
                'cargo:id,consulta_id,estado,total',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        if ($inicioRango !== null && $finRango !== null) {
            $query->whereBetween('consultas.atendido_at', [$inicioRango, $finRango]);
        }

        if ($filtrarAbiertas) {
            $query->whereNull('consultas.cerrada_at');
        } elseif ($filtrarCerradas) {
            $query->whereNotNull('consultas.cerrada_at');
        }

        if ($sort === 'paciente') {
            $query
                ->join('historias_clinicas as hc_p', 'hc_p.id', '=', 'consultas.historia_clinica_id')
                ->join('pacientes as pac_p', 'pac_p.id', '=', 'hc_p.paciente_id')
                ->orderBy('pac_p.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('consultas.atendido_at')
                ->select('consultas.*');
        } elseif ($sortValid) {
            $query->orderBy('consultas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'atendido_at') {
                $query->orderByDesc('consultas.atendido_at');
            }
        } elseif ($filtrarAbiertas && ! $sortValid) {
            $query->orderBy('consultas.atendido_at', 'asc');
        } else {
            $query->orderByDesc('consultas.atendido_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('consultas.motivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.subjetivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.objetivo', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.analisis', 'ILIKE', "%{$search}%")
                    ->orWhere('consultas.plan', 'ILIKE', "%{$search}%")
                    ->orWhereHas('historiaClinica.paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    });
            });
        }

        $consultas = $query->paginate($perPage)->withQueryString();

        $consultaAbrirEditar = $this->consultaParaAbrirEnModal($request, $canAudit);
        $pacientePrefillNuevaConsulta = $this->pacientePrefillNuevaConsultaDesdeQuery($request);

        $limiteConsultaAntigua = $now->copy()->subHours(24);

        $abiertasTotal = Consulta::query()
            ->whereNull('cerrada_at')
            ->count();

        $abiertasAntiguas = Consulta::query()
            ->whereNull('cerrada_at')
            ->where('atendido_at', '<', $limiteConsultaAntigua)
            ->count();

        if ($omitirRangoMes) {
            $totalEnRango = $abiertasTotal;
        } elseif ($inicioRango !== null && $finRango !== null) {
            $totalEnRangoQuery = Consulta::query()
                ->whereBetween('consultas.atendido_at', [$inicioRango, $finRango]);

            if ($filtrarAbiertas) {
                $totalEnRangoQuery->whereNull('cerrada_at');
            } elseif ($filtrarCerradas) {
                $totalEnRangoQuery->whereNotNull('cerrada_at');
            }

            $totalEnRango = $totalEnRangoQuery->count();
        } else {
            $totalEnRango = 0;
        }

        $pacientesOpciones = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

        return Inertia::render('clinica/historias-clinicas/index', [
            'consultas' => $consultas,
            'consulta_abrir_editar' => $consultaAbrirEditar,
            'paciente_prefill_nueva_consulta' => $pacientePrefillNuevaConsulta,
            'pacientes_opciones' => $pacientesOpciones,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'atendido_desde' => $atendidoDesde,
                'atendido_hasta' => $atendidoHasta,
                'estado' => $estado,
                'solo_abiertas' => $filtrarAbiertas,
            ],
            'atencion_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $atencionFueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $consultas->total(),
                'abiertas_total' => $abiertasTotal,
                'abiertas_antiguas' => $abiertasAntiguas,
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
     * Carga una consulta para abrir el modal de edición cuando el listado
     * llega con `?editar_consulta=<uuid>` (p. ej. desde la vista de plan).
     */
    private function consultaParaAbrirEnModal(Request $request, bool $canAudit): ?Consulta
    {
        $raw = $request->query('editar_consulta');
        if (! is_string($raw) || ! Str::isUuid($raw)) {
            return null;
        }

        if (! $request->user()?->can('historias-clinicas.update')) {
            return null;
        }

        $query = Consulta::query()
            ->with([
                'historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                'historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
                'veterinario:id,name',
                'cerradaPor:id,name',
                'planTratamiento.lineas.producto:id,nombre,unidad,sku',
                'cargo:id,consulta_id,estado,total',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        return $query->whereKey($raw)->first();
    }

    /**
     * @return array{id: string, nombre: string, propietario: array<string, mixed>}|null
     */
    private function pacientePrefillNuevaConsultaDesdeQuery(Request $request): ?array
    {
        $raw = $request->query('nuevo_para_paciente');
        if (! is_string($raw) || ! Str::isUuid($raw)) {
            return null;
        }

        if (! $request->user()?->can('historias-clinicas.create')) {
            return null;
        }

        $paciente = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
            ->whereKey($raw)
            ->where('activo', true)
            ->first(['id', 'nombre', 'propietario_id']);

        if ($paciente === null) {
            return null;
        }

        $prop = $paciente->propietario;

        return [
            'id' => $paciente->id,
            'nombre' => $paciente->nombre,
            'propietario' => $prop !== null ? [
                'id' => $prop->id,
                'nombres' => $prop->nombres,
                'apellidos' => $prop->apellidos,
                'razon_social' => $prop->razon_social,
            ] : null,
        ];
    }

    public function store(StoreConsultaHistoriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uid = Auth::id();

        $consultaCreada = null;

        DB::transaction(function () use ($validated, $uid, &$consultaCreada): void {
            $historia = HistoriaClinica::query()->firstOrCreate(
                ['paciente_id' => $validated['paciente_id']],
                [
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ],
            );

            if ($historia->wasRecentlyCreated === false) {
                $historia->update(['updated_by_id' => $uid]);
            }

            $peso = $validated['peso_kg'] ?? null;
            $temp = $validated['temperatura_c'] ?? null;
            $fc = $validated['fc_lpm'] ?? null;
            $fr = $validated['fr_rpm'] ?? null;
            $consultaCreada = $historia->consultas()->create([
                'atendido_at' => $validated['atendido_at'],
                'motivo' => $validated['motivo'] ?? null,
                'subjetivo' => $validated['subjetivo'] ?? null,
                'objetivo' => $validated['objetivo'] ?? null,
                'analisis' => $validated['analisis'] ?? null,
                'plan' => $validated['plan'] ?? null,
                'peso_kg' => $peso === null || $peso === '' ? null : $peso,
                'temperatura_c' => $temp === null || $temp === '' ? null : $temp,
                'fc_lpm' => $fc === null || $fc === '' ? null : (int) $fc,
                'fr_rpm' => $fr === null || $fr === '' ? null : (int) $fr,
                'cerrada_at' => null,
                'cerrada_por_id' => null,
                'veterinario_id' => $uid,
                'created_by_id' => $uid,
                'updated_by_id' => $uid,
            ]);

        });

        if ($consultaCreada !== null && ($request->user()?->can('historias-clinicas-planes.view') ?? false)) {
            return redirect()
                ->route('clinica.historias-clinicas.consultas.plan-tratamiento', $consultaCreada)
                ->with('success', __('historias-clinicas.flash.created_open_plan'));
        }

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.created'));
    }

    public function update(
        UpdateConsultaHistoriaRequest $request,
        Consulta $consulta,
    ): RedirectResponse {
        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->withErrors(['consulta_cerrada' => __('historias-clinicas.errors.no_editable_cerrada')]);
        }

        $validated = $request->validated();
        $uid = Auth::id();

        DB::transaction(function () use ($consulta, $validated, $uid): void {
            $peso = $validated['peso_kg'] ?? null;
            $temp = $validated['temperatura_c'] ?? null;
            $fc = $validated['fc_lpm'] ?? null;
            $fr = $validated['fr_rpm'] ?? null;
            $consulta->update([
                'atendido_at' => $validated['atendido_at'],
                'motivo' => $validated['motivo'] ?? null,
                'subjetivo' => $validated['subjetivo'] ?? null,
                'objetivo' => $validated['objetivo'] ?? null,
                'analisis' => $validated['analisis'] ?? null,
                'plan' => $validated['plan'] ?? null,
                'peso_kg' => $peso === null || $peso === '' ? null : $peso,
                'temperatura_c' => $temp === null || $temp === '' ? null : $temp,
                'fc_lpm' => $fc === null || $fc === '' ? null : (int) $fc,
                'fr_rpm' => $fr === null || $fr === '' ? null : (int) $fr,
                'updated_by_id' => $uid,
            ]);

        });

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.updated'));
    }

    public function cerrar(Consulta $consulta): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('success', __('historias-clinicas.flash.ya_cerrada'));
        }

        $uid = Auth::id();
        $consulta->update([
            'cerrada_at' => now(),
            'cerrada_por_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.cerrada'));
    }

    public function cerrarAbiertas(): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        $uid = Auth::id();
        $now = now();

        $count = Consulta::query()
            ->whereNull('cerrada_at')
            ->update([
                'cerrada_at' => $now,
                'cerrada_por_id' => $uid,
                'updated_by_id' => $uid,
                'updated_at' => $now,
            ]);

        if ($count === 0) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('info', __('historias-clinicas.flash.cerrar_abiertas_ninguna'));
        }

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.cerrar_abiertas', ['count' => $count]));
    }

    public function reabrir(Consulta $consulta): RedirectResponse
    {
        abort_unless(auth()->user()?->can('historias-clinicas.update') ?? false, 403);

        if ($consulta->cerrada_at === null) {
            return redirect()
                ->route('clinica.historias-clinicas')
                ->with('success', __('historias-clinicas.flash.ya_abierta'));
        }

        $uid = Auth::id();
        $consulta->update([
            'cerrada_at' => null,
            'cerrada_por_id' => null,
            'updated_by_id' => $uid,
        ]);

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.reabierta'));
    }

    public function destroy(Consulta $consulta): RedirectResponse
    {
        $consulta->delete();

        return redirect()
            ->route('clinica.historias-clinicas')
            ->with('success', __('historias-clinicas.flash.deleted'));
    }

    public function pdf(Request $request, Consulta $consulta): HttpResponse
    {
        abort_unless($request->user()?->can('historias-clinicas.view') ?? false, 403);

        $consulta->load([
            'historiaClinica.paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'recetas:id,consulta_id,estado',
            'pedidosLaboratorio:id,consulta_id,estado',
            'cirugias:id,consulta_id,estado,nombre_procedimiento',
            'internamientos:id,consulta_id,estado,motivo_ingreso',
        ]);

        $paciente = $consulta->historiaClinica?->paciente;
        abort_if($paciente === null, 404);

        $entry = HistorialClinicoPdfBuilder::make()->fromConsulta($consulta);

        $pdf = Pdf::loadView('pdf.consulta-clinica', array_merge(
            $this->clinicPdfBranding(),
            [
                'paciente' => $paciente,
                'propietarioNombre' => $this->propietarioNombreParaPdf($paciente),
                'entry' => $entry,
            ],
        ));

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'consulta-'.$slug.'-'.Str::substr($consulta->id, 0, 8).'.pdf';

        return $this->respondClinicPdf($request, $pdf, $filename);
    }
}
