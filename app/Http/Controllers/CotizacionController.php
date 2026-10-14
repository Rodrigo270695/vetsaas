<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CotizacionRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Cotizacion;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Notifications\Cotizacion\CotizacionMailNotification;
use App\Services\Cotizacion\CotizacionPdfService;
use App\Services\Cotizacion\CotizacionService;
use App\Services\Cotizacion\CotizacionWhatsAppSender;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Support\Clinica\PropietarioSearch;
use App\Support\Inventario\ProductoCatalogoSearch;
use App\Support\Servicios\ServicioTarifaSearch;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class CotizacionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    private const SORTABLE_COLUMNS = ['numero', 'fecha', 'vigencia_hasta', 'cliente_nombre', 'total', 'created_at'];

    private const ESTADO_FILTROS = ['todas', 'emitida', 'aceptada', 'rechazada', 'vencida'];

    private const VIGENCIA_DIAS = 15;

    public function index(Request $request): InertiaResponse
    {
        $this->ensureTables();

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_FILTROS, true)) {
            $estado = 'todas';
        }

        $hoy = now(config('app.timezone'))->toDateString();

        $query = Cotizacion::query()
            ->withCount('lineas')
            ->with(['creadoPor:id,name', 'sede:id,nombre']);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('numero', 'ILIKE', $like)
                    ->orWhere('cliente_nombre', 'ILIKE', $like)
                    ->orWhere('cliente_documento', 'ILIKE', $like)
                    ->orWhere('cliente_telefono', 'ILIKE', $like);
            });
        }

        match ($estado) {
            'vencida' => $query->where('estado', Cotizacion::ESTADO_EMITIDA)->where('vigencia_hasta', '<', $hoy),
            'emitida' => $query->where('estado', Cotizacion::ESTADO_EMITIDA)->where('vigencia_hasta', '>=', $hoy),
            'aceptada', 'rechazada' => $query->where('estado', $estado),
            default => null,
        };

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'desc');
        }
        $query->orderByDesc('fecha')->orderByDesc('correlativo');

        $cotizaciones = $query->paginate($perPage)->withQueryString();
        $cotizaciones->getCollection()->transform(fn (Cotizacion $c): array => $this->row($c));

        $creada = null;
        $creadaId = (string) $request->query('creada', '');
        if (Str::isUuid($creadaId)) {
            $model = Cotizacion::query()->withCount('lineas')->with(['creadoPor:id,name', 'sede:id,nombre'])->find($creadaId);
            $creada = $model !== null ? $this->row($model) : null;
        }

        return Inertia::render('caja/cotizaciones/index', [
            'cotizaciones' => $cotizaciones,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Cotizacion::query()->count(),
                'vigentes' => Cotizacion::query()->where('estado', Cotizacion::ESTADO_EMITIDA)->where('vigencia_hasta', '>=', $hoy)->count(),
                'aceptadas' => Cotizacion::query()->where('estado', Cotizacion::ESTADO_ACEPTADA)->count(),
                'coincidencias' => $cotizaciones->total(),
            ],
            'cotizacion_creada' => $creada,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->ensureTables();

        $tz = config('app.timezone');

        return Inertia::render('caja/cotizaciones/form', [
            ...$this->formPayload($request),
            'cotizacion' => null,
            'defaults' => [
                'sede_id' => $this->defaultSedeId(),
                'fecha' => now($tz)->toDateString(),
                'vigencia_hasta' => now($tz)->addDays(self::VIGENCIA_DIAS)->toDateString(),
            ],
        ]);
    }

    public function store(CotizacionRequest $request, CotizacionService $service): RedirectResponse
    {
        $this->ensureTables();
        $data = $this->validatedWithSede($request);

        $cotizacion = $service->crear($data, (string) Auth::id());

        return redirect()
            ->route('caja.cotizaciones.index', ['creada' => $cotizacion->id])
            ->with('success', "Cotización {$cotizacion->numero} creada.");
    }

    public function edit(Request $request, Cotizacion $cotizacion): InertiaResponse
    {
        $cotizacion->load([
            'lineas',
            'propietario:id,nombres,apellidos,razon_social,numero_documento',
            'paciente:id,nombre',
        ]);

        return Inertia::render('caja/cotizaciones/form', [
            ...$this->formPayload($request),
            'cotizacion' => [
                'id' => $cotizacion->id,
                'numero' => $cotizacion->numero,
                'sede_id' => $cotizacion->sede_id,
                'propietario_id' => $cotizacion->propietario_id,
                'propietario' => $cotizacion->propietario !== null ? PropietarioSearch::opcion($cotizacion->propietario) : null,
                'paciente_id' => $cotizacion->paciente_id,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_documento' => $cotizacion->cliente_documento,
                'cliente_telefono' => $cotizacion->cliente_telefono,
                'cliente_email' => $cotizacion->cliente_email,
                'fecha' => $cotizacion->fecha->toDateString(),
                'vigencia_hasta' => $cotizacion->vigencia_hasta->toDateString(),
                'notas' => $cotizacion->notas,
                'igv_porcentaje' => (string) $cotizacion->igv_porcentaje,
                'precio_incluye_igv' => (bool) $cotizacion->precio_incluye_igv,
                'lineas' => $cotizacion->lineas->map(fn ($l): array => [
                    'tipo_linea' => $l->tipo_linea,
                    'producto_id' => $l->producto_id,
                    'descripcion' => $l->descripcion,
                    'unidad' => $l->unidad,
                    'cantidad' => (float) $l->cantidad,
                    'precio_unitario' => (string) round((float) $l->precio_unitario, 2),
                    'descuento_pct' => (float) $l->descuento_pct,
                ])->values()->all(),
            ],
            'defaults' => null,
        ]);
    }

    public function update(CotizacionRequest $request, Cotizacion $cotizacion, CotizacionService $service): RedirectResponse
    {
        $data = $this->validatedWithSede($request);
        $service->actualizar($cotizacion, $data, (string) Auth::id());

        return redirect()
            ->route('caja.cotizaciones.index')
            ->with('success', "Cotización {$cotizacion->numero} actualizada.");
    }

    public function estado(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(Cotizacion::ESTADOS)],
        ]);

        $cotizacion->update([
            'estado' => $data['estado'],
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Estado de la cotización actualizado.');
    }

    public function destroy(Cotizacion $cotizacion): RedirectResponse
    {
        $cotizacion->update(['updated_by_id' => Auth::id()]);
        $cotizacion->delete();

        return back()->with('success', "Cotización {$cotizacion->numero} eliminada.");
    }

    public function pdf(Request $request, Cotizacion $cotizacion, CotizacionPdfService $pdf): Response
    {
        $file = $pdf->render($cotizacion);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($file['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$file['filename'].'"',
        ]);
    }

    public function enviarWhatsApp(
        Request $request,
        Cotizacion $cotizacion,
        CotizacionWhatsAppSender $sender,
    ): RedirectResponse {
        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $phone = trim((string) ($data['telefono'] ?? '')) ?: $cotizacion->cliente_telefono;
        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', 'Indica un número de WhatsApp válido para enviar la cotización.');
        }

        $tenantId = resolve_clinic_tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return back()->with('warning', 'No se pudo identificar la clínica para enviar por WhatsApp.');
        }

        try {
            $sender->send($cotizacion, $tenant, $chatId, ClinicSetting::current());
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar cotización por WhatsApp', [
                'cotizacion_id' => $cotizacion->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', trim('No se pudo enviar la cotización por WhatsApp. '.$e->getMessage()));
        }

        $cotizacion->forceFill([
            'enviada_whatsapp_at' => now(),
            'cliente_telefono' => $cotizacion->cliente_telefono ?: $phone,
        ])->save();

        return back()->with('success', "Cotización {$cotizacion->numero} enviada por WhatsApp.");
    }

    public function enviarCorreo(
        Request $request,
        Cotizacion $cotizacion,
        CotizacionPdfService $pdf,
        ReminderMessageBuilder $messages,
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
        ], [], ['email' => 'correo']);

        $clinic = ClinicSetting::current();
        $clinicName = $messages->clinicDisplayName($clinic);
        $file = $pdf->render($cotizacion);
        $replyTo = trim((string) ($clinic->email_institucional ?? ''));
        $simbolo = strtoupper((string) $cotizacion->moneda) === 'USD' ? 'US$' : 'S/';

        try {
            Notification::route('mail', $data['email'])->notify(new CotizacionMailNotification(
                clinicName: $clinicName,
                clienteNombre: $cotizacion->cliente_nombre,
                numero: $cotizacion->numero,
                total: $simbolo.' '.number_format((float) $cotizacion->total, 2, '.', ','),
                vigencia: $cotizacion->vigencia_hasta->format('d/m/Y'),
                pdfBinary: $file['binary'],
                pdfFilename: $file['filename'],
                replyTo: filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null,
                mensaje: $data['mensaje'] ?? null,
            ));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar cotización por correo', [
                'cotizacion_id' => $cotizacion->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', 'No se pudo enviar el correo. Revisa la dirección e inténtalo de nuevo.');
        }

        $cotizacion->forceFill([
            'enviada_email_at' => now(),
            'cliente_email' => $cotizacion->cliente_email ?: $data['email'],
        ])->save();

        return back()->with('success', "Cotización {$cotizacion->numero} enviada a {$data['email']}.");
    }

    public function buscarPropietarios(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q', ''));

        return response()->json([
            'data' => PropietarioSearch::opcionesActivas($q, 30),
        ]);
    }

    public function propietario(Propietario $propietario): JsonResponse
    {
        $pacientes = Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre']);

        return response()->json([
            'data' => [
                'id' => $propietario->id,
                'nombre' => $propietario->displayName(),
                'documento' => $propietario->numero_documento,
                'telefono' => $propietario->telefono ?: $propietario->telefono_alt,
                'email' => $propietario->email,
                'pacientes' => $pacientes,
            ],
        ]);
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $rows = ProductoCatalogoSearch::filas(
            $q,
            soloActivos: true,
            columnas: ['id', 'nombre', 'sku', 'unidad', 'precio_venta'],
            limit: 50,
        );

        return response()->json([
            'data' => $rows->map(fn ($p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'unidad' => $p->unidad,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
            ])->values(),
        ]);
    }

    public function buscarServicios(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ServicioTarifaSearch::search(trim((string) $request->string('q', ''))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Cotizacion $c): array
    {
        return [
            'id' => $c->id,
            'numero' => $c->numero,
            'fecha' => $c->fecha->toDateString(),
            'vigencia_hasta' => $c->vigencia_hasta->toDateString(),
            'estado' => $c->estado,
            'vencida' => $c->estaVencida(),
            'cliente_nombre' => $c->cliente_nombre,
            'cliente_documento' => $c->cliente_documento,
            'cliente_telefono' => $c->cliente_telefono,
            'cliente_email' => $c->cliente_email,
            'moneda' => $c->moneda,
            'total' => (string) $c->total,
            'lineas_count' => (int) ($c->lineas_count ?? 0),
            'sede_nombre' => $c->sede?->nombre,
            'creado_por' => $c->creadoPor?->name,
            'enviada_whatsapp_at' => $c->enviada_whatsapp_at?->toIso8601String(),
            'enviada_email_at' => $c->enviada_email_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Request $request): array
    {
        $clinic = ClinicSetting::current();

        return [
            'sedes' => $this->sedesQuery()->get(['id', 'nombre']),
            'clinica' => [
                'moneda' => (string) ($clinic->moneda ?: 'PEN'),
                'igv_porcentaje' => number_format($clinic->igvPorcentajeEfectivo(), 2, '.', ''),
                'precio_incluye_igv' => (bool) $clinic->precio_incluye_igv,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWithSede(CotizacionRequest $request): array
    {
        $data = $request->validated();
        $sedeId = $data['sede_id'] ?? null;

        if ($sedeId !== null && ! $this->sedesQuery()->whereKey($sedeId)->exists()) {
            throw ValidationException::withMessages(['sede_id' => 'La sede elegida no pertenece a la clínica.']);
        }

        return $data;
    }

    /**
     * @return Builder<Sede>
     */
    private function sedesQuery(): Builder
    {
        $tenantId = (string) (resolve_clinic_tenant_id() ?? '');

        return Sede::query()
            ->where('tenant_id', Str::isUuid($tenantId) ? $tenantId : '00000000-0000-0000-0000-000000000000')
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre');
    }

    private function defaultSedeId(): ?string
    {
        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first(['sede_id']);

        if ($sesion?->sede_id !== null && $this->sedesQuery()->whereKey($sesion->sede_id)->exists()) {
            return (string) $sesion->sede_id;
        }

        $first = $this->sedesQuery()->first(['id']);

        return $first?->id;
    }

    private function ensureTables(): void
    {
        if (! Schema::hasTable('cotizaciones')) {
            abort(503, 'Faltan migraciones del tenant (cotizaciones). Ejecuta: php artisan vetsaas:tenant-migrate-all --slug=<tu-clinica>');
        }
    }
}
