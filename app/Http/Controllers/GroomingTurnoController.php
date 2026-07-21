<?php

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Http\Requests\CambiarEstadoGroomingTurnoRequest;
use App\Http\Requests\StoreGroomingTurnoFotoRequest;
use App\Http\Requests\StoreGroomingTurnoRequest;
use App\Http\Requests\UpdateGroomingTurnoRequest;
use App\Models\ClinicSetting;
use App\Models\GroomingServicio;
use App\Models\GroomingTurno;
use App\Models\GroomingTurnoFoto;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Grooming\GroomingProcesoWhatsAppSender;
use App\Support\Grooming\GroomingTurnoServicioRules;
use App\Support\WhatsApp\WhatsAppChatId;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class GroomingTurnoController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const MAX_FOTOS_POR_TURNO = 8;

    private const SORTABLE_COLUMNS = [
        'inicio_at',
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

        $groomingDesde = $this->parseDateParam($request->query('grooming_desde'));
        $groomingHasta = $this->parseDateParam($request->query('grooming_hasta'));

        if ($groomingDesde === null || $groomingHasta === null) {
            $groomingDesde = $defaultDesde;
            $groomingHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($groomingDesde > $groomingHasta) {
                [$groomingDesde, $groomingHasta] = [$groomingHasta, $groomingDesde];
            }
            $fueraDelMesActual = ($groomingDesde !== $defaultDesde) || ($groomingHasta !== $defaultHasta);
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;
        $propietarioSelect = ['id', 'nombres', 'apellidos', 'razon_social', 'telefono'];

        $turnoAbrirEditar = null;
        $editarRaw = $request->query('editar_grooming_turno');
        if (is_string($editarRaw) && Str::isUuid($editarRaw) && ($request->user()?->can('grooming.update') ?? false)) {
            $q = GroomingTurno::query()
                ->with([
                    'paciente' => fn ($q) => $q->withTrashed(),
                    'paciente.propietario' => fn ($q) => $q->withTrashed()->select($propietarioSelect),
                    'responsable:id,name',
                    'sede:id,nombre,codigo',
                    'fotos',
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
                $turnoAbrirEditar = $model;
                $at = $model->inicio_at->copy()->timezone($tz);
                $groomingDesde = $at->copy()->startOfMonth()->toDateString();
                $groomingHasta = $at->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($groomingDesde !== $defaultDesde) || ($groomingHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($groomingDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($groomingHasta, $tz)->endOfDay();

        $query = GroomingTurno::query()
            ->with([
                'paciente' => fn ($q) => $q->withTrashed(),
                'paciente.propietario' => fn ($q) => $q->withTrashed()->select($propietarioSelect),
                'responsable:id,name',
                'sede:id,nombre,codigo',
                'groomingServicio:id,nombre',
                'fotos',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('grooming_turnos.inicio_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as gt_pac', 'gt_pac.id', '=', 'grooming_turnos.paciente_id')
                ->orderBy('gt_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('grooming_turnos.inicio_at')
                ->select('grooming_turnos.*');
        } elseif ($sortValid) {
            $query->orderBy('grooming_turnos.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'inicio_at') {
                $query->orderByDesc('grooming_turnos.inicio_at');
            }
        } else {
            $query->orderByDesc('grooming_turnos.inicio_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('grooming_turnos.servicio', 'ILIKE', "%{$search}%")
                    ->orWhere('grooming_turnos.servicio_detalle', 'ILIKE', "%{$search}%")
                    ->orWhere('grooming_turnos.notas', 'ILIKE', "%{$search}%")
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

        $turnos = $query->paginate($perPage)->withQueryString();

        $totalEnRango = GroomingTurno::query()
            ->whereBetween('inicio_at', [$inicioRango, $finRango])
            ->count();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social')])
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

        $catalogoPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();
        $notificationSetting = ClinicSetting::current();

        $groomingServicios = $catalogoPersonalizado
            ? GroomingServicio::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden'])
            : collect();

        return Inertia::render('servicios/grooming/index', [
            'turnos' => $turnos,
            'grooming_catalogo_personalizado' => $catalogoPersonalizado,
            'grooming_servicios' => $groomingServicios,
            'grooming_servicio_grupos' => $catalogoPersonalizado ? [] : GroomingCatalogoServicio::grupos(),
            'grooming_servicio_duraciones' => $catalogoPersonalizado
                ? $groomingServicios->mapWithKeys(fn ($s) => [$s->id => $s->duracion_minutos])->all()
                : GroomingCatalogoServicio::duracionesSugeridas(),
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'turno_abrir_editar' => $turnoAbrirEditar,
            'grooming_whatsapp_preferences' => [
                GroomingTurno::ESTADO_EN_PROCESO => $notificationSetting->notificarGroomingWhatsAppActivo(GroomingTurno::ESTADO_EN_PROCESO),
                GroomingTurno::ESTADO_COMPLETADA => $notificationSetting->notificarGroomingWhatsAppActivo(GroomingTurno::ESTADO_COMPLETADA),
                GroomingTurno::ESTADO_CANCELADA => $notificationSetting->notificarGroomingWhatsAppActivo(GroomingTurno::ESTADO_CANCELADA),
                GroomingTurno::ESTADO_NO_ASISTIO => $notificationSetting->notificarGroomingWhatsAppActivo(GroomingTurno::ESTADO_NO_ASISTIO),
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'grooming_desde' => $groomingDesde,
                'grooming_hasta' => $groomingHasta,
            ],
            'grooming_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $turnos->total(),
            ],
        ]);
    }

    public function store(
        StoreGroomingTurnoRequest $request,
        GroomingProcesoWhatsAppSender $sender,
    ): RedirectResponse {
        $data = GroomingTurnoServicioRules::normalizarParaPersistencia($request->validated());
        $data['estado'] = GroomingTurno::ESTADO_PROGRAMADA;
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        $turno = GroomingTurno::query()->create($data);

        $wa = $this->tryNotifyAgenda($turno, $sender, 'programado');

        $redirect = redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]));

        if ($wa === 'ok') {
            return $redirect->with('success', __('grooming.flash.created_whatsapp'));
        }

        if ($wa === 'fail') {
            return $redirect->with('warning', __('grooming.flash.created_sin_whatsapp'));
        }

        return $redirect->with('success', __('grooming.flash.created'));
    }

    public function update(
        UpdateGroomingTurnoRequest $request,
        GroomingTurno $groomingTurno,
        GroomingProcesoWhatsAppSender $sender,
    ): RedirectResponse {
        $data = GroomingTurnoServicioRules::normalizarParaPersistencia($request->validated());
        $data['updated_by_id'] = Auth::id();

        $inicioAnterior = $groomingTurno->inicio_at?->copy();
        $groomingTurno->fill($data);
        $inicioCambio = $inicioAnterior === null
            || ! $groomingTurno->inicio_at?->equalTo($inicioAnterior);
        $groomingTurno->save();

        $redirect = redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]));

        if (! $inicioCambio) {
            return $redirect->with('success', __('grooming.flash.updated'));
        }

        $wa = $this->tryNotifyAgenda($groomingTurno, $sender, 'reprogramado');

        if ($wa === 'ok') {
            return $redirect->with('success', __('grooming.flash.updated_whatsapp'));
        }

        if ($wa === 'fail') {
            return $redirect->with('warning', __('grooming.flash.updated_sin_whatsapp'));
        }

        return $redirect->with('success', __('grooming.flash.updated'));
    }

    public function destroy(Request $request, GroomingTurno $groomingTurno): RedirectResponse
    {
        abort_unless($request->user()?->can('grooming.delete') ?? false, 403);

        $groomingTurno->delete();

        return redirect()
            ->route('servicios.grooming', $request->only([
                'search', 'per_page', 'sort', 'direction', 'grooming_desde', 'grooming_hasta',
            ]))
            ->with('success', __('grooming.flash.deleted'));
    }

    public function cambiarEstado(
        CambiarEstadoGroomingTurnoRequest $request,
        GroomingTurno $groomingTurno,
        TenantManager $tenants,
        GroomingProcesoWhatsAppSender $sender,
    ): RedirectResponse {
        $data = $request->validated();
        $nuevoEstado = (string) $data['estado'];
        $setting = ClinicSetting::current();
        $notificar = $setting->notificarGroomingWhatsAppActivo($nuevoEstado)
            && (($data['notificar_whatsapp'] ?? true) === true);

        $groomingTurno->estado = $nuevoEstado;
        $groomingTurno->updated_by_id = Auth::id();
        $groomingTurno->save();

        $fotosCreadas = collect();
        $files = $request->file('fotos', []);
        if (! is_array($files)) {
            $files = $files !== null ? [$files] : [];
        }

        $tipoFoto = $nuevoEstado === GroomingTurno::ESTADO_COMPLETADA
            ? GroomingTurnoFoto::TIPO_FINAL
            : GroomingTurnoFoto::TIPO_PROCESO;

        $remaining = self::MAX_FOTOS_POR_TURNO - $groomingTurno->fotos()->count();
        $tenant = $tenants->current();
        $slug = $tenant?->slug ?? 'shared';
        $dir = "tenants/{$slug}/grooming/{$groomingTurno->id}";
        $disk = Storage::disk('public');

        foreach (array_slice($files, 0, max(0, $remaining)) as $file) {
            if ($file === null || ! $file->isValid()) {
                continue;
            }
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
            $filename = Str::uuid()->toString().'.'.$extension;
            $disk->putFileAs($dir, $file, $filename, 'public');

            $fotosCreadas->push(GroomingTurnoFoto::query()->create([
                'grooming_turno_id' => $groomingTurno->id,
                'tipo' => $tipoFoto,
                'path' => $dir.'/'.$filename,
                'created_by_id' => Auth::id(),
            ]));
        }

        if (! $notificar) {
            return back()->with('success', __('grooming.flash.estado_updated'));
        }

        $groomingTurno->load([
            'paciente.propietario:id,nombres,apellidos,razon_social,telefono',
            'groomingServicio:id,nombre',
        ]);

        $propietario = $groomingTurno->paciente?->propietario;
        $phone = trim((string) ($data['telefono'] ?? '')) !== ''
            ? (string) $data['telefono']
            : $propietario?->telefono;

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', __('grooming.flash.estado_updated_sin_whatsapp'));
        }

        $tenantId = tenant_id();
        $tenantModel = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenantModel === null) {
            return back()->with('warning', __('grooming.flash.estado_updated_sin_whatsapp'));
        }

        $ownerName = $propietario !== null
            ? (trim($propietario->displayName()) !== '' ? $propietario->displayName() : 'cliente')
            : 'cliente';

        try {
            $sender->notifyEstado(
                $groomingTurno,
                $tenantModel,
                $chatId,
                $ownerName,
                $setting,
                $nuevoEstado,
                $fotosCreadas->isNotEmpty() ? $fotosCreadas : null,
            );

            return back()->with('success', __('grooming.flash.estado_updated_whatsapp'));
        } catch (Throwable $e) {
            Log::warning('Grooming: estado actualizado pero WhatsApp falló', [
                'turno_id' => $groomingTurno->id,
                'estado' => $nuevoEstado,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', __('grooming.flash.estado_updated_sin_whatsapp').' '.$e->getMessage());
        }
    }

    public function storeFoto(
        StoreGroomingTurnoFotoRequest $request,
        GroomingTurno $groomingTurno,
        TenantManager $tenants,
    ): RedirectResponse {
        $count = $groomingTurno->fotos()->count();
        if ($count >= self::MAX_FOTOS_POR_TURNO) {
            return back()->with('warning', __('grooming.flash.fotos_max'));
        }

        $file = $request->file('foto');
        $tenant = $tenants->current();
        $slug = $tenant?->slug ?? 'shared';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $dir = "tenants/{$slug}/grooming/{$groomingTurno->id}";

        Storage::disk('public')->putFileAs($dir, $file, $filename, 'public');

        GroomingTurnoFoto::query()->create([
            'grooming_turno_id' => $groomingTurno->id,
            'tipo' => $request->validated('tipo'),
            'path' => $dir.'/'.$filename,
            'caption' => $request->validated('caption'),
            'created_by_id' => Auth::id(),
        ]);

        $groomingTurno->updated_by_id = Auth::id();
        $groomingTurno->save();

        return back()->with('success', __('grooming.flash.foto_uploaded'));
    }

    public function destroyFoto(
        Request $request,
        GroomingTurno $groomingTurno,
        GroomingTurnoFoto $foto,
    ): RedirectResponse {
        abort_unless($request->user()?->can('grooming.update') ?? false, 403);
        abort_unless($foto->grooming_turno_id === $groomingTurno->id, 404);

        $disk = Storage::disk('public');
        if ($foto->path !== '' && $disk->exists($foto->path)) {
            $disk->delete($foto->path);
        }

        $foto->delete();

        $groomingTurno->updated_by_id = Auth::id();
        $groomingTurno->save();

        return back()->with('success', __('grooming.flash.foto_deleted'));
    }

    public function enviarWhatsApp(
        Request $request,
        GroomingTurno $groomingTurno,
        GroomingProcesoWhatsAppSender $sender,
    ): RedirectResponse {
        abort_unless($request->user()?->can('grooming.update') ?? false, 403);

        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20'],
            'solo_pendientes' => ['nullable', 'boolean'],
        ]);

        $groomingTurno->load([
            'paciente.propietario:id,nombres,apellidos,razon_social,telefono',
            'fotos',
            'groomingServicio:id,nombre',
        ]);

        $fotos = $groomingTurno->fotos;
        if (($data['solo_pendientes'] ?? true) === true) {
            $fotos = $fotos->whereNull('enviado_whatsapp_at')->values();
        }

        if ($fotos->isEmpty()) {
            return back()->with('warning', __('grooming.flash.whatsapp_sin_fotos'));
        }

        $propietario = $groomingTurno->paciente?->propietario;
        $phone = trim((string) ($data['telefono'] ?? '')) !== ''
            ? (string) $data['telefono']
            : $propietario?->telefono;

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', __('grooming.flash.whatsapp_no_phone'));
        }

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return back()->with('warning', __('grooming.flash.whatsapp_fallo'));
        }

        $ownerName = $propietario !== null
            ? (trim($propietario->displayName()) !== '' ? $propietario->displayName() : 'cliente')
            : 'cliente';

        try {
            $result = $sender->send(
                $groomingTurno,
                $tenant,
                $chatId,
                $ownerName,
                ClinicSetting::current(),
                $fotos,
            );

            return back()->with('success', __('grooming.flash.whatsapp_enviado', [
                'count' => $result['sent'],
            ]));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar fotos grooming por WhatsApp', [
                'turno_id' => $groomingTurno->id,
                'error' => $e->getMessage(),
            ]);

            $msg = __('grooming.flash.whatsapp_fallo');
            $detail = trim($e->getMessage());
            if ($detail !== '') {
                $msg .= ' '.$detail;
            }

            return back()->with('warning', $msg);
        }
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Best-effort: no bloquea el CRUD si WhatsApp falla o no hay teléfono.
     *
     * @param  'programado'|'reprogramado'  $tipo
     * @return 'ok'|'skip'|'fail'
     */
    private function tryNotifyAgenda(
        GroomingTurno $turno,
        GroomingProcesoWhatsAppSender $sender,
        string $tipo,
    ): string {
        $setting = ClinicSetting::current();
        if (! $setting->notificarGroomingWhatsAppActivo($tipo)) {
            return 'skip';
        }

        $turno->loadMissing([
            'paciente.propietario:id,nombres,apellidos,razon_social,telefono',
            'groomingServicio:id,nombre',
        ]);

        $propietario = $turno->paciente?->propietario;
        $chatId = WhatsAppChatId::fromPhone($propietario?->telefono);
        if ($chatId === null) {
            return 'skip';
        }

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return 'skip';
        }

        $ownerName = $propietario !== null
            ? (trim($propietario->displayName()) !== '' ? $propietario->displayName() : 'cliente')
            : 'cliente';

        try {
            $sender->notifyAgenda(
                $turno,
                $tenant,
                $chatId,
                $ownerName,
                $setting,
                $tipo,
            );

            return 'ok';
        } catch (Throwable $e) {
            Log::warning('Grooming: no se pudo notificar agenda por WhatsApp', [
                'turno_id' => $turno->id,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);

            return 'fail';
        }
    }
}
