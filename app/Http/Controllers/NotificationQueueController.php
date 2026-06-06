<?php

namespace App\Http\Controllers;

use App\Models\NotificationQueue;
use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationQueueController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const COLA_ESTADOS = [
        NotificationQueue::ESTADO_PENDIENTE,
        NotificationQueue::ESTADO_PROCESANDO,
        NotificationQueue::ESTADO_FALLIDO,
    ];

    public function cola(Request $request, TenantManager $tenants, TenantWhatsAppPresenter $whatsapp): Response
    {
        return $this->renderIndex(
            $request,
            $tenants,
            $whatsapp,
            'comunicaciones/cola/index',
            self::COLA_ESTADOS,
            NotificationQueue::ESTADO_PENDIENTE,
            includeWhatsapp: true,
        );
    }

    public function historico(Request $request, TenantManager $tenants, TenantWhatsAppPresenter $whatsapp): Response
    {
        return $this->renderIndex(
            $request,
            $tenants,
            $whatsapp,
            'comunicaciones/historico/index',
            [NotificationQueue::ESTADO_ENVIADO],
            NotificationQueue::ESTADO_ENVIADO,
            includeWhatsapp: false,
        );
    }

    public function cancel(NotificationQueue $notification): RedirectResponse
    {
        abort_unless(
            in_array($notification->estado, [NotificationQueue::ESTADO_PENDIENTE, NotificationQueue::ESTADO_FALLIDO], true),
            422,
        );

        $notification->forceFill(['estado' => NotificationQueue::ESTADO_CANCELADO])->save();

        return back()->with('success', 'Mensaje cancelado.');
    }

    public function retry(NotificationQueue $notification): RedirectResponse
    {
        abort_unless($notification->estado === NotificationQueue::ESTADO_FALLIDO, 422);

        $notification->forceFill([
            'estado' => NotificationQueue::ESTADO_PENDIENTE,
            'intentos' => 0,
            'error_mensaje' => null,
            'enviar_at' => now(),
        ])->save();

        return back()->with('success', 'Mensaje reencolado.');
    }

    private function renderIndex(
        Request $request,
        TenantManager $tenants,
        TenantWhatsAppPresenter $whatsapp,
        string $page,
        array $allowedEstados,
        string $defaultEstado,
        bool $includeWhatsapp,
    ): Response {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 15;

        $estado = (string) $request->string('estado', $defaultEstado);
        if (! in_array($estado, $allowedEstados, true)) {
            $estado = $defaultEstado;
        }

        $tipo = trim((string) $request->string('tipo', ''));
        $tipoFilter = $tipo !== '' ? $tipo : null;

        $query = NotificationQueue::query()
            ->where('estado', $estado)
            ->when($tipoFilter !== null, fn ($q) => $q->where('tipo', $tipoFilter))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('destinatario', 'ilike', '%'.$search.'%')
                        ->orWhere('destinatario_nombre', 'ilike', '%'.$search.'%')
                        ->orWhere('cuerpo', 'ilike', '%'.$search.'%');
                });
            })
            ->orderByDesc('enviar_at');

        $items = $query->paginate($perPage)->withQueryString();

        $stats = NotificationQueue::query()
            ->selectRaw('estado, count(*) as total')
            ->whereIn('estado', $allowedEstados)
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $payload = [
            'items' => $items->through(fn (NotificationQueue $row): array => $this->presentRow($row)),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'estado' => $estado,
                'tipo' => $tipoFilter,
            ],
            'stats' => collect($allowedEstados)
                ->mapWithKeys(fn (string $key): array => [$key => (int) ($stats[$key] ?? 0)])
                ->all(),
            'estado_options' => $allowedEstados,
            'tipo_options' => NotificationQueue::query()
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo')
                ->values()
                ->all(),
        ];

        if ($includeWhatsapp) {
            $payload['whatsapp'] = $whatsapp->forTenant($tenants->current()?->tenant);
        }

        return Inertia::render($page, $payload);
    }

    private function presentRow(NotificationQueue $row): array
    {
        return [
            'id' => $row->id,
            'tipo' => $row->tipo,
            'canal' => $row->canal,
            'destinatario' => $row->destinatario,
            'destinatario_nombre' => $row->destinatario_nombre,
            'cuerpo' => $row->cuerpo,
            'estado' => $row->estado,
            'enviar_at' => $row->enviar_at?->toIso8601String(),
            'intentos' => $row->intentos,
            'max_intentos' => $row->max_intentos,
            'error_mensaje' => $row->error_mensaje,
            'proveedor_msg_id' => $row->proveedor_msg_id,
            'ultimo_intento_at' => $row->ultimo_intento_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
