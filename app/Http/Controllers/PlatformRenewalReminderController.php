<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionRenewalReminder;
use App\Support\OpenWa\PlatformWhatsAppPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformRenewalReminderController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function index(Request $request, PlatformWhatsAppPresenter $whatsapp): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 15;

        $query = SubscriptionRenewalReminder::query()
            ->with(['subscription.tenant', 'subscription.plan'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('destinatario', 'ilike', '%'.$search.'%')
                        ->orWhereHas('subscription.tenant', function ($tenant) use ($search): void {
                            $tenant->where('razon_social', 'ilike', '%'.$search.'%')
                                ->orWhere('nombre_comercial', 'ilike', '%'.$search.'%')
                                ->orWhere('slug', 'ilike', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('sent_at');

        $items = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => SubscriptionRenewalReminder::query()->count(),
            'last_7_days' => SubscriptionRenewalReminder::query()
                ->where('sent_at', '>=', now()->subDays(7))
                ->count(),
            'kind_7d' => SubscriptionRenewalReminder::query()
                ->where('reminder_kind', SubscriptionRenewalReminder::KIND_7D)
                ->count(),
            'kind_3d' => SubscriptionRenewalReminder::query()
                ->where('reminder_kind', SubscriptionRenewalReminder::KIND_3D)
                ->count(),
            'kind_1d' => SubscriptionRenewalReminder::query()
                ->where('reminder_kind', SubscriptionRenewalReminder::KIND_1D)
                ->count(),
            'kind_manual' => SubscriptionRenewalReminder::query()
                ->where('reminder_kind', SubscriptionRenewalReminder::KIND_MANUAL)
                ->count(),
        ];

        return Inertia::render('plataforma/avisos-renovacion/index', [
            'items' => $items->through(fn (SubscriptionRenewalReminder $row): array => [
                'id' => $row->id,
                'reminder_kind' => $row->reminder_kind,
                'anchor_at' => $row->anchor_at?->toIso8601String(),
                'channel' => $row->channel,
                'destinatario' => $row->destinatario,
                'sent_at' => $row->sent_at?->toIso8601String(),
                'tenant' => [
                    'slug' => $row->subscription?->tenant?->slug,
                    'nombre' => $row->subscription?->tenant?->nombre_comercial
                        ?: $row->subscription?->tenant?->razon_social,
                ],
                'subscription' => [
                    'ciclo' => $row->subscription?->ciclo,
                    'estado' => $row->subscription?->estado,
                ],
            ]),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
            'whatsapp' => $whatsapp->present(),
        ]);
    }

}
