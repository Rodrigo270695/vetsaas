<?php

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\ClinicSetting;
use App\Models\GroomingServicio;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\HotelTipoEstancia;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ServiciosAgendaController extends Controller
{
    public function index(Request $request, TenantManager $tenants): InertiaResponse
    {
        $user = $request->user();
        abort_unless(
            ($user?->can('grooming.view') ?? false) || ($user?->can('hotel.view') ?? false),
            403,
        );

        /** @var Tenant|null $tenant */
        $tenant = $tenants->current()?->tenant;
        $groomingEnabled = TenantModuleAccess::isEnabled($tenant, 'grooming')
            && ($user?->can('grooming.view') ?? false);
        $hotelEnabled = TenantModuleAccess::isEnabled($tenant, 'hotel')
            && ($user?->can('hotel.view') ?? false);

        abort_unless($groomingEnabled || $hotelEnabled, 403);

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultMes = $now->format('Y-m');
        $mesRaw = (string) $request->string('mes', $defaultMes);
        $mes = preg_match('/^\d{4}-\d{2}$/', $mesRaw) === 1 ? $mesRaw : $defaultMes;

        $inicioRango = Carbon::createFromFormat('Y-m-d', $mes.'-01', $tz)->startOfMonth()->startOfDay();
        $finRango = $inicioRango->copy()->endOfMonth()->endOfDay();

        $search = trim((string) $request->string('search', ''));
        $propietarioSelect = ['id', 'nombres', 'apellidos', 'razon_social', 'telefono'];

        $eventos = collect();

        if ($groomingEnabled) {
            $groomingQuery = GroomingTurno::query()
                ->with([
                    'paciente' => fn ($q) => $q->withTrashed(),
                    'paciente.propietario' => fn ($q) => $q->withTrashed()->select($propietarioSelect),
                    'responsable:id,name',
                    'sede:id,nombre,codigo',
                    'groomingServicio:id,nombre',
                ])
                ->whereBetween('inicio_at', [$inicioRango, $finRango])
                ->orderBy('inicio_at')
                ->limit(500);

            if ($search !== '') {
                $groomingQuery->where(function ($q) use ($search) {
                    $q->where('servicio', 'ILIKE', "%{$search}%")
                        ->orWhere('servicio_detalle', 'ILIKE', "%{$search}%")
                        ->orWhere('notas', 'ILIKE', "%{$search}%")
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

            foreach ($groomingQuery->get() as $turno) {
                $eventos->push([
                    'id' => $turno->id,
                    'tipo' => 'grooming',
                    'inicio_at' => $turno->inicio_at?->toIso8601String(),
                    'estado' => $turno->estado,
                    'titulo' => $turno->paciente?->nombre ?? '—',
                    'subtitulo' => $turno->servicio_label
                        ?? $turno->groomingServicio?->nombre
                        ?? $turno->servicio,
                    'paciente' => $turno->paciente,
                    'responsable' => $turno->responsable,
                    'sede' => $turno->sede,
                ]);
            }
        }

        if ($hotelEnabled) {
            $hotelQuery = HotelEstancia::query()
                ->with([
                    'paciente' => fn ($q) => $q->withTrashed(),
                    'paciente.propietario' => fn ($q) => $q->withTrashed()->select($propietarioSelect),
                    'responsable:id,name',
                    'sede:id,nombre,codigo',
                    'hotelTipo:id,nombre',
                ])
                ->where('ingreso_at', '<=', $finRango)
                ->where(function ($q) use ($inicioRango) {
                    $q->whereNull('egreso_at')
                        ->orWhere('egreso_at', '>=', $inicioRango);
                })
                ->orderBy('ingreso_at')
                ->limit(500);

            if ($search !== '') {
                $hotelQuery->where(function ($q) use ($search) {
                    $q->where('tipo_estancia', 'ILIKE', "%{$search}%")
                        ->orWhere('tipo_detalle', 'ILIKE', "%{$search}%")
                        ->orWhere('notas', 'ILIKE', "%{$search}%")
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

            foreach ($hotelQuery->get() as $estancia) {
                $inicioDisplay = $estancia->ingreso_at;
                if ($inicioDisplay !== null && $inicioDisplay->lt($inicioRango)) {
                    $inicioDisplay = $inicioRango->copy()->setTime(
                        (int) $estancia->ingreso_at->format('H'),
                        (int) $estancia->ingreso_at->format('i'),
                    );
                }

                $eventos->push([
                    'id' => $estancia->id,
                    'tipo' => 'hotel',
                    'inicio_at' => $inicioDisplay?->toIso8601String(),
                    'fin_at' => $estancia->egreso_at?->toIso8601String(),
                    'estado' => $estancia->estado,
                    'titulo' => $estancia->paciente?->nombre ?? '—',
                    'subtitulo' => $estancia->hotelTipo?->nombre
                        ?? $estancia->tipo_estancia,
                    'paciente' => $estancia->paciente,
                    'responsable' => $estancia->responsable,
                    'sede' => $estancia->sede,
                ]);
            }
        }

        $eventos = $eventos
            ->sortBy(fn (array $e) => $e['inicio_at'] ?? '')
            ->values();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

        $tenantId = tenant_id();
        $sedesOpciones = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo']);

        $groomingCatalogoPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();
        $groomingServicios = $groomingCatalogoPersonalizado
            ? GroomingServicio::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden'])
            : collect();

        $hotelCatalogoPersonalizado = HotelCatalogoMode::usaCatalogoPersonalizado();
        $hotelTipos = $hotelCatalogoPersonalizado
            ? HotelTipoEstancia::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'activo', 'orden'])
            : collect();

        $clinicSetting = ClinicSetting::current();

        return Inertia::render('servicios/agenda/index', [
            'eventos' => $eventos,
            'filters' => [
                'search' => $search,
                'mes' => $mes,
                'per_page' => 10,
                'sort' => null,
                'direction' => null,
            ],
            'agenda_filtro_ui' => [
                'default_mes' => $defaultMes,
            ],
            'agenda_horario' => [
                'hora_inicio' => $clinicSetting->agendaHoraInicio(),
                'hora_fin' => $clinicSetting->agendaHoraFin(),
            ],
            'stats' => [
                'total' => $eventos->count(),
                'grooming' => $eventos->where('tipo', 'grooming')->count(),
                'hotel' => $eventos->where('tipo', 'hotel')->count(),
            ],
            'capabilities' => [
                'grooming' => $groomingEnabled,
                'hotel' => $hotelEnabled,
                'grooming_create' => $groomingEnabled && ($user?->can('grooming.create') ?? false),
                'hotel_create' => $hotelEnabled && ($user?->can('hotel.create') ?? false),
            ],
            'pacientes_opciones' => $pacientesOpciones,
            'sedes_opciones' => $sedesOpciones,
            'grooming_catalogo_personalizado' => $groomingCatalogoPersonalizado,
            'grooming_servicios' => $groomingServicios,
            'grooming_servicio_grupos' => $groomingCatalogoPersonalizado ? [] : GroomingCatalogoServicio::grupos(),
            'grooming_servicio_duraciones' => $groomingCatalogoPersonalizado
                ? $groomingServicios->mapWithKeys(fn ($s) => [$s->id => $s->duracion_minutos])->all()
                : GroomingCatalogoServicio::duracionesSugeridas(),
            'hotel_catalogo_personalizado' => $hotelCatalogoPersonalizado,
            'hotel_tipos' => $hotelTipos,
            'hotel_tipo_grupos' => $hotelCatalogoPersonalizado ? [] : HotelCatalogoTipoEstancia::grupos(),
        ]);
    }
}
