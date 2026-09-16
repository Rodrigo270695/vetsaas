<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsAppCampanaRequest;
use App\Models\Tenant;
use App\Models\WhatsAppCampana;
use App\Models\WhatsAppCampanaDestinatario;
use App\Services\WhatsApp\WhatsAppCampaignAudience;
use App\Services\WhatsApp\WhatsAppCampaignDispatcher;
use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Support\WhatsApp\WhatsAppCampaignClock;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppCampanaController extends Controller
{
    private const PER_PAGE = [10, 15, 25, 50];

    public function index(
        Request $request,
        TenantManager $tenants,
        TenantWhatsAppPresenter $whatsapp,
        WhatsAppCampaignDispatcher $dispatcher,
    ): Response {
        $this->kickDueCampaigns($tenants, $dispatcher);

        $perPage = $this->perPage($request);
        $search = trim((string) $request->string('search', ''));
        $estado = trim((string) $request->string('estado', ''));
        if ($estado === 'todos') {
            $estado = '';
        }

        $items = WhatsAppCampana::query()
            ->withCount([
                'destinatarios as pendientes_count' => fn ($q) => $q->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE),
                'destinatarios as enviados_count' => fn ($q) => $q->where('estado', WhatsAppCampanaDestinatario::ESTADO_ENVIADO),
                'destinatarios as total_count',
            ])
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', '%'.$search.'%'))
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (WhatsAppCampana $campana) => [
                ...$this->campanaPayload($campana),
                'pendientes_count' => (int) $campana->pendientes_count,
                'enviados_count' => (int) $campana->enviados_count,
                'total_count' => (int) $campana->total_count,
            ]);

        return Inertia::render('comunicaciones/campanas/index', [
            'items' => $items,
            'stats' => [
                'total' => WhatsAppCampana::query()->count(),
                'borrador' => WhatsAppCampana::query()->where('estado', WhatsAppCampana::ESTADO_BORRADOR)->count(),
                'enviando' => WhatsAppCampana::query()->where('estado', WhatsAppCampana::ESTADO_ENVIANDO)->count(),
                'pausada' => WhatsAppCampana::query()->where('estado', WhatsAppCampana::ESTADO_PAUSADA)->count(),
                'terminada' => WhatsAppCampana::query()->where('estado', WhatsAppCampana::ESTADO_TERMINADA)->count(),
            ],
            'filters' => [
                'search' => $search,
                'estado' => $estado !== '' ? $estado : null,
                'per_page' => $perPage,
            ],
            'whatsapp' => $whatsapp->forTenant($tenants->current()?->tenant),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('comunicaciones.campanas.index');
    }

    public function store(WhatsAppCampanaRequest $request, TenantManager $tenants): RedirectResponse
    {
        $data = $this->payload($request);
        $data['estado'] = WhatsAppCampana::ESTADO_BORRADOR;
        $data['created_by_id'] = $request->user()?->id;
        $data['imagen_path'] = $this->storeImagen($request, $tenants, null);

        WhatsAppCampana::query()->create($data);

        return redirect()
            ->route('comunicaciones.campanas.index')
            ->with('success', 'Campaña creada. En Acciones elegí los destinatarios y luego Enviar.');
    }

    public function show(
        Request $request,
        WhatsAppCampana $campana,
        WhatsAppCampaignAudience $audience,
        TenantManager $tenants,
        TenantWhatsAppPresenter $whatsapp,
        WhatsAppCampaignDispatcher $dispatcher,
    ): Response {
        $this->kickDueCampaigns($tenants, $dispatcher);
        $campana->refresh();

        $perPage = $this->perPage($request);
        $search = trim((string) $request->string('search', ''));
        $scope = (string) $request->string('scope', 'lote');
        if (! in_array($scope, ['lote', 'agregar'], true)) {
            $scope = 'lote';
        }
        $estado = trim((string) $request->string('estado', ''));
        if ($estado === 'todos') {
            $estado = '';
        }

        $lote = $campana->destinatarios()
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('nombre_snapshot', 'ilike', '%'.$search.'%')
                        ->orWhere('telefono_normalizado', 'ilike', '%'.$search.'%')
                        ->orWhere('mascota_nombres', 'ilike', '%'.$search.'%')
                        ->orWhere('cuerpo_enviado', 'ilike', '%'.$search.'%');
                });
            })
            ->orderByRaw('enviado_at DESC NULLS LAST')
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page')
            ->withQueryString();

        $elegibles = $scope === 'agregar'
            ? $audience->paginateEligible($campana, $search, $perPage)
            : null;

        $stats = [
            'total' => $campana->destinatarios()->count(),
            'pendiente' => $campana->destinatarios()->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE)->count(),
            'enviado' => $campana->destinatarios()->where('estado', WhatsAppCampanaDestinatario::ESTADO_ENVIADO)->count(),
            'fallido' => $campana->destinatarios()->where('estado', WhatsAppCampanaDestinatario::ESTADO_FALLIDO)->count(),
            'omitido' => $campana->destinatarios()->where('estado', WhatsAppCampanaDestinatario::ESTADO_OMITIDO)->count(),
            'enviados_hoy' => $campana->enviadosHoy(),
            'elegibles' => $audience->eligibleQuery()->whereNotIn(
                'id',
                $campana->destinatarios()->pluck('propietario_id'),
            )->count(),
        ];

        return Inertia::render('comunicaciones/campanas/show', [
            'campana' => $this->campanaPayload($campana),
            'lote' => $lote->through(fn (WhatsAppCampanaDestinatario $row) => [
                'id' => $row->id,
                'nombre_snapshot' => $row->nombre_snapshot,
                'telefono_normalizado' => $row->telefono_normalizado,
                'mascota_nombres' => $row->mascota_nombres,
                'estado' => $row->estado,
                'cuerpo_enviado' => $row->cuerpo_enviado,
                'error' => $row->error,
                'enviado_at' => $row->enviado_at?->toIso8601String(),
            ]),
            'elegibles' => $elegibles?->through(fn ($owner) => [
                'id' => $owner->id,
                'nombre' => $owner->displayName(),
                'telefono' => $owner->telefono,
                'telefono_alt' => $owner->telefono_alt,
            ]),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'estado' => $estado !== '' ? $estado : null,
                'per_page' => $perPage,
            ],
            'whatsapp' => $whatsapp->forTenant($tenants->current()?->tenant),
        ]);
    }

    public function edit(WhatsAppCampana $campana): RedirectResponse
    {
        return redirect()->route('comunicaciones.campanas.index');
    }

    public function update(
        WhatsAppCampanaRequest $request,
        WhatsAppCampana $campana,
        TenantManager $tenants,
        WhatsAppCampaignDispatcher $dispatcher,
    ): RedirectResponse {
        $data = $this->payload($request);
        $path = $this->storeImagen($request, $tenants, $campana->imagen_path);
        if ($path !== null || $request->boolean('clear_imagen')) {
            $data['imagen_path'] = $request->boolean('clear_imagen') ? null : $path;
        }

        $campana->fill($data)->save();

        if ($campana->estado === WhatsAppCampana::ESTADO_ENVIANDO
            && $campana->destinatarios()
                ->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE)
                ->exists()
        ) {
            $tenant = $tenants->current()?->tenant;
            if ($tenant instanceof Tenant) {
                $dispatcher->tick($tenant);
            }
        }

        return redirect()
            ->route('comunicaciones.campanas.index')
            ->with('success', 'Campaña actualizada.');
    }

    public function destroy(WhatsAppCampana $campana): RedirectResponse
    {
        abort_unless($campana->estado === WhatsAppCampana::ESTADO_BORRADOR, 422);

        if ($campana->imagen_path) {
            Storage::disk('public')->delete($campana->imagen_path);
        }
        $campana->delete();

        return redirect()
            ->route('comunicaciones.campanas.index')
            ->with('success', 'Campaña eliminada.');
    }

    public function start(
        WhatsAppCampana $campana,
        TenantManager $tenants,
        TenantWhatsAppPresenter $whatsapp,
        WhatsAppCampaignDispatcher $dispatcher,
    ): RedirectResponse {
        abort_unless(in_array($campana->estado, [
            WhatsAppCampana::ESTADO_BORRADOR,
            WhatsAppCampana::ESTADO_PAUSADA,
            WhatsAppCampana::ESTADO_TERMINADA,
        ], true), 422);

        $session = $whatsapp->forTenant($tenants->current()?->tenant)['session'] ?? null;
        if (! is_array($session) || empty($session['is_ready'])) {
            return back()->with('error', 'WhatsApp no está conectado. Vincúlalo en Cola saliente para lanzar la campaña.');
        }

        if (count($campana->variantesLimpias()) < 1) {
            return back()->with('error', 'La campaña necesita un mensaje.');
        }

        $pendientes = $campana->destinatarios()
            ->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE)
            ->count();
        if ($pendientes === 0) {
            return back()->with('error', 'Agregá al menos un destinatario con celular válido.');
        }

        $campana->forceFill([
            'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
            'started_at' => $campana->started_at ?? now(),
            'paused_at' => null,
        ])->save();

        $sent = 0;
        $tenant = $tenants->current()?->tenant;
        if ($tenant instanceof Tenant) {
            $sent = $dispatcher->tick($tenant)['sent'];
        }

        $campana->refresh();
        $hint = $campana->pacingHint()['label'];

        if ($sent > 0) {
            return back()->with('success', 'Campaña en marcha. Ya salió el primer mensaje.');
        }

        return back()->with('success', 'Campaña en marcha. '.$hint.'.');
    }

    public function pause(WhatsAppCampana $campana): RedirectResponse
    {
        abort_unless($campana->estado === WhatsAppCampana::ESTADO_ENVIANDO, 422);

        $campana->forceFill([
            'estado' => WhatsAppCampana::ESTADO_PAUSADA,
            'paused_at' => now(),
        ])->save();

        return back()->with('success', 'Campaña pausada. No se enviará nada hasta que la reanudes.');
    }

    public function elegibles(
        Request $request,
        WhatsAppCampana $campana,
        WhatsAppCampaignAudience $audience,
    ): JsonResponse {
        $search = trim((string) $request->string('search', ''));
        $perPage = $this->perPage($request);
        $page = $audience->paginateEligible($campana, $search, $perPage);

        return response()->json([
            'data' => $page->getCollection()
                ->map(static fn ($owner) => [
                    'id' => $owner->id,
                    'nombre' => $owner->displayName(),
                    'telefono' => $owner->telefono,
                    'telefono_alt' => $owner->telefono_alt,
                ])
                ->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'in_lote' => $campana->destinatarios()->count(),
        ]);
    }

    public function attach(
        Request $request,
        WhatsAppCampana $campana,
        WhatsAppCampaignAudience $audience,
        TenantManager $tenants,
        WhatsAppCampaignDispatcher $dispatcher,
    ): RedirectResponse {
        $ids = $request->input('propietario_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $result = $audience->attachIds($campana, array_map('strval', $ids));

        return $this->afterAttach($campana, $result, $tenants, $dispatcher);
    }

    public function attachMatching(
        Request $request,
        WhatsAppCampana $campana,
        WhatsAppCampaignAudience $audience,
        TenantManager $tenants,
        WhatsAppCampaignDispatcher $dispatcher,
    ): RedirectResponse {
        $result = $audience->attachMatching($campana, trim((string) $request->string('search', '')));

        return $this->afterAttach($campana, $result, $tenants, $dispatcher);
    }

    public function detach(WhatsAppCampana $campana, WhatsAppCampanaDestinatario $destinatario): RedirectResponse
    {
        abort_unless($destinatario->campana_id === $campana->id, 404);
        abort_unless($destinatario->estado === WhatsAppCampanaDestinatario::ESTADO_PENDIENTE, 422);

        $destinatario->delete();

        return back()->with('success', 'Destinatario quitado del lote.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WhatsAppCampanaRequest $request): array
    {
        return [
            'nombre' => trim((string) $request->validated('nombre')),
            'variantes' => [trim((string) $request->validated('cuerpo'))],
            'tope_diario' => (int) $request->validated('tope_diario'),
            'intervalo_minutos' => (int) $request->validated('intervalo_minutos'),
            'hora_inicio' => $request->validated('hora_inicio'),
            'hora_fin' => $request->validated('hora_fin'),
        ];
    }

    private function storeImagen(
        WhatsAppCampanaRequest $request,
        TenantManager $tenants,
        ?string $current,
    ): ?string {
        if ($request->boolean('clear_imagen')) {
            if ($current) {
                Storage::disk('public')->delete($current);
            }

            return null;
        }

        if (! $request->hasFile('imagen')) {
            return $current;
        }

        $slug = $tenants->current()?->slug ?? 'shared';
        $file = $request->file('imagen');
        if ($file === null) {
            return $current;
        }

        $stored = $file->store('tenants/'.$slug.'/campanas', 'public');
        if (! is_string($stored) || $stored === '') {
            return $current;
        }

        if ($current && $current !== $stored) {
            Storage::disk('public')->delete($current);
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function campanaPayload(WhatsAppCampana $campana): array
    {
        $hint = $campana->pacingHint();

        return [
            'id' => $campana->id,
            'nombre' => $campana->nombre,
            'imagen_url' => $campana->imagenUrl(),
            'variantes' => $campana->variantesLimpias(),
            'cuerpo' => $campana->variantesLimpias()[0] ?? '',
            'tope_diario' => $campana->tope_diario,
            'intervalo_minutos' => $campana->intervalo_minutos,
            'hora_inicio' => WhatsAppCampaignClock::hm($campana->hora_inicio),
            'hora_fin' => WhatsAppCampaignClock::hm($campana->hora_fin),
            'estado' => $campana->estado,
            'last_sent_at' => $campana->last_sent_at?->toIso8601String(),
            'pacing_hint' => $hint,
        ];
    }

    private function kickDueCampaigns(TenantManager $tenants, WhatsAppCampaignDispatcher $dispatcher): void
    {
        $tenant = $tenants->current()?->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        if (! WhatsAppCampana::query()->where('estado', WhatsAppCampana::ESTADO_ENVIANDO)->exists()) {
            return;
        }

        $dispatcher->tick($tenant);
    }

    private function afterAttach(
        WhatsAppCampana $campana,
        array $result,
        TenantManager $tenants,
        WhatsAppCampaignDispatcher $dispatcher,
    ): RedirectResponse {
        $added = (int) ($result['added'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);

        if ($added > 0 && $campana->estado === WhatsAppCampana::ESTADO_TERMINADA) {
            $campana->forceFill([
                'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
                'paused_at' => null,
                'started_at' => $campana->started_at ?? now(),
            ])->save();

            $tenant = $tenants->current()?->tenant;
            if ($tenant instanceof Tenant) {
                $dispatcher->tick($tenant);
            }
        }

        $message = sprintf(
            'Agregados: %d. Omitidos: %d (ya estaban en el lote o el celular se usó). Solo salen los nuevos; los ya enviados no se repiten.',
            $added,
            $skipped,
        );

        return back()->with('success', $message);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 15);

        return in_array($perPage, self::PER_PAGE, true) ? $perPage : 15;
    }
}
