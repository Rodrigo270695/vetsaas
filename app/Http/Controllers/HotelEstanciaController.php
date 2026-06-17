<?php

namespace App\Http\Controllers;

use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Http\Requests\StoreHotelEstanciaDiarioRequest;
use App\Http\Requests\StoreHotelEstanciaRequest;
use App\Http\Requests\UpdateHotelEstanciaRequest;
use App\Models\HotelEstancia;
use App\Models\HotelEstanciaDiario;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\User;
use App\Support\Hotel\HotelEstanciaTipoRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HotelEstanciaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'ingreso_at',
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

        $hotelDesde = $this->parseDateParam($request->query('hotel_desde'));
        $hotelHasta = $this->parseDateParam($request->query('hotel_hasta'));

        if ($hotelDesde === null || $hotelHasta === null) {
            $hotelDesde = $defaultDesde;
            $hotelHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($hotelDesde > $hotelHasta) {
                [$hotelDesde, $hotelHasta] = [$hotelHasta, $hotelDesde];
            }
            $fueraDelMesActual = ($hotelDesde !== $defaultDesde) || ($hotelHasta !== $defaultHasta);
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $estanciaAbrirEditar = null;
        $editarRaw = $request->query('editar_hotel_estancia');
        if (is_string($editarRaw) && Str::isUuid($editarRaw) && ($request->user()?->can('hotel.update') ?? false)) {
            $q = HotelEstancia::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
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
                $estanciaAbrirEditar = $model;
                $at = $model->ingreso_at->copy()->timezone($tz);
                $hotelDesde = $at->copy()->startOfMonth()->toDateString();
                $hotelHasta = $at->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($hotelDesde !== $defaultDesde) || ($hotelHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($hotelDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($hotelHasta, $tz)->endOfDay();

        $query = HotelEstancia::query()
            ->with([
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'responsable:id,name',
                'sede:id,nombre,codigo',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('hotel_estancias.ingreso_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as he_pac', 'he_pac.id', '=', 'hotel_estancias.paciente_id')
                ->orderBy('he_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('hotel_estancias.ingreso_at')
                ->select('hotel_estancias.*');
        } elseif ($sortValid) {
            $query->orderBy('hotel_estancias.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'ingreso_at') {
                $query->orderByDesc('hotel_estancias.ingreso_at');
            }
        } else {
            $query->orderByDesc('hotel_estancias.ingreso_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('hotel_estancias.tipo_estancia', 'ILIKE', "%{$search}%")
                    ->orWhere('hotel_estancias.tipo_detalle', 'ILIKE', "%{$search}%")
                    ->orWhere('hotel_estancias.notas', 'ILIKE', "%{$search}%")
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

        $estancias = $query->paginate($perPage)->withQueryString();

        $totalEnRango = HotelEstancia::query()
            ->whereBetween('ingreso_at', [$inicioRango, $finRango])
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

        $catalogoPersonalizado = HotelCatalogoMode::usaCatalogoPersonalizado();

        $hotelTipos = $catalogoPersonalizado
            ? \App\Models\HotelTipoEstancia::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'activo', 'orden'])
            : collect();

        return Inertia::render('servicios/hotel/index', [
            'estancias' => $estancias,
            'hotel_catalogo_personalizado' => $catalogoPersonalizado,
            'hotel_tipos' => $hotelTipos,
            'hotel_tipo_grupos' => $catalogoPersonalizado ? [] : HotelCatalogoTipoEstancia::grupos(),
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'estancia_abrir_editar' => $estanciaAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'hotel_desde' => $hotelDesde,
                'hotel_hasta' => $hotelHasta,
            ],
            'hotel_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $estancias->total(),
            ],
        ]);
    }

    public function store(StoreHotelEstanciaRequest $request): RedirectResponse
    {
        $data = HotelEstanciaTipoRules::normalizarParaPersistencia($request->validated());
        $data['estado'] = HotelEstancia::ESTADO_PROGRAMADA;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        HotelEstancia::query()->create($data);

        return redirect()
            ->route('servicios.hotel', $request->only([
                'search', 'per_page', 'sort', 'direction', 'hotel_desde', 'hotel_hasta',
            ]))
            ->with('success', __('hotel.flash.created'));
    }

    public function update(UpdateHotelEstanciaRequest $request, HotelEstancia $hotelEstancia): RedirectResponse
    {
        $data = HotelEstanciaTipoRules::normalizarParaPersistencia($request->validated());
        $data['updated_by_id'] = Auth::id();

        $hotelEstancia->fill($data);
        $hotelEstancia->save();

        return redirect()
            ->route('servicios.hotel', $request->only([
                'search', 'per_page', 'sort', 'direction', 'hotel_desde', 'hotel_hasta',
            ]))
            ->with('success', __('hotel.flash.updated'));
    }

    public function destroy(Request $request, HotelEstancia $hotelEstancia): RedirectResponse
    {
        abort_unless($request->user()?->can('hotel.delete') ?? false, 403);

        $hotelEstancia->delete();

        return redirect()
            ->route('servicios.hotel', $request->only([
                'search', 'per_page', 'sort', 'direction', 'hotel_desde', 'hotel_hasta',
            ]))
            ->with('success', __('hotel.flash.deleted'));
    }

    public function diariosIndex(Request $request, HotelEstancia $hotelEstancia): JsonResponse
    {
        abort_unless($request->user()?->can('hotel.view') ?? false, 403);

        $data = $hotelEstancia->diarios()
            ->with(['creadoPor:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HotelEstanciaDiario $d): array => [
                'id' => $d->id,
                'fecha' => $d->fecha->toDateString(),
                'notas' => $d->notas,
                'created_at' => $d->created_at?->toIso8601String(),
                'creado_por' => $d->creadoPor !== null
                    ? ['id' => $d->creadoPor->id, 'name' => $d->creadoPor->name]
                    : null,
            ]);

        return response()->json(['data' => $data]);
    }

    public function diariosStore(StoreHotelEstanciaDiarioRequest $request, HotelEstancia $hotelEstancia): JsonResponse
    {
        $validated = $request->validated();

        $diario = $hotelEstancia->diarios()->create([
            'fecha' => $validated['fecha'],
            'notas' => $validated['notas'] ?? null,
            'created_by_id' => Auth::id(),
        ]);

        $diario->load(['creadoPor:id,name']);

        return response()->json([
            'data' => [
                'id' => $diario->id,
                'fecha' => $diario->fecha->toDateString(),
                'notas' => $diario->notas,
                'created_at' => $diario->created_at?->toIso8601String(),
                'creado_por' => $diario->creadoPor !== null
                    ? ['id' => $diario->creadoPor->id, 'name' => $diario->creadoPor->name]
                    : null,
            ],
        ], 201);
    }

    public function diariosDestroy(
        Request $request,
        HotelEstancia $hotelEstancia,
        HotelEstanciaDiario $hotelEstanciaDiario,
    ): JsonResponse {
        abort_unless($request->user()?->can('hotel.update') ?? false, 403);

        if ((string) $hotelEstanciaDiario->hotel_estancia_id !== (string) $hotelEstancia->id) {
            abort(404);
        }

        $hotelEstanciaDiario->delete();

        return response()->json(['ok' => true]);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
