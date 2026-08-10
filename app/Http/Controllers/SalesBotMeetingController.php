<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SalesConversation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reuniones / tours Meet agendados por el SalesBot (panel central).
 */
final class SalesBotMeetingController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $estado = (string) $request->input('estado', 'confirmadas');
        $perPage = (int) $request->input('per_page', 15);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $query = SalesConversation::query()
            ->where(function ($q): void {
                $q->whereNotNull('meet_link')
                    ->orWhereNotNull('meet_proposed_at')
                    ->orWhere('meet_status', 'proposed')
                    ->orWhere('meet_status', 'confirmed');
            });

        if ($estado === 'confirmadas') {
            $query->whereNotNull('meet_link')
                ->where(function ($q): void {
                    $q->where('meet_status', 'confirmed')
                        ->orWhereNull('meet_status');
                });
        } elseif ($estado === 'propuestas') {
            $query->where('meet_status', 'proposed')->whereNull('meet_link');
        } elseif ($estado === 'proximas') {
            $query->whereNotNull('meet_link')
                ->where('meet_at', '>=', now()->subHour());
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('phone', 'ilike', "%{$search}%")
                    ->orWhere('prospect_name', 'ilike', "%{$search}%")
                    ->orWhere('meet_link', 'ilike', "%{$search}%");
            });
        }

        $meetings = $query
            ->orderByRaw('COALESCE(meet_at, meet_proposed_at) DESC NULLS LAST')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesConversation $c): array => [
                'id' => $c->id,
                'phone' => $c->phone,
                'prospect_name' => $c->prospect_name,
                'meet_status' => $c->meet_status,
                'meet_at' => $c->meet_at?->toIso8601String(),
                'meet_proposed_at' => $c->meet_proposed_at?->toIso8601String(),
                'meet_link' => $c->meet_link,
                'google_event_id' => $c->google_event_id,
                'meet_notified_at' => $c->meet_notified_at?->toIso8601String(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        $stats = [
            'confirmadas' => SalesConversation::query()
                ->whereNotNull('meet_link')
                ->where(function ($q): void {
                    $q->where('meet_status', 'confirmed')
                        ->orWhereNull('meet_status');
                })
                ->count(),
            'propuestas' => SalesConversation::query()
                ->where('meet_status', 'proposed')
                ->whereNull('meet_link')
                ->count(),
            'proximas' => SalesConversation::query()
                ->whereNotNull('meet_link')
                ->where('meet_at', '>=', now()->subHour())
                ->count(),
            'coincidencias' => $meetings->total(),
        ];

        return Inertia::render('plataforma/salesbot-meetings/index', [
            'meetings' => $meetings,
            'filters' => [
                'search' => $search,
                'estado' => $estado,
                'per_page' => $perPage,
                'sort' => null,
                'direction' => null,
            ],
            'stats' => $stats,
        ]);
    }
}
