<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSalesBotMeetingStatusRequest;
use App\Models\SalesConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reuniones / tours Meet agendados por el SalesBot (panel central).
 */
final class SalesBotMeetingController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /** Minutos tras meet_at para considerar que ya debería cerrarse. */
    private const CLOSE_GRACE_MINUTES = 60;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $estado = (string) $request->input('estado', 'confirmadas');
        $perPage = (int) $request->input('per_page', 15);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $graceBefore = now()->subMinutes(self::CLOSE_GRACE_MINUTES);

        $query = SalesConversation::query()
            ->where(function ($q): void {
                $q->whereNotNull('meet_link')
                    ->orWhereNotNull('meet_proposed_at')
                    ->orWhere('meet_status', SalesConversation::MEET_STATUS_PROPOSED)
                    ->orWhere('meet_status', SalesConversation::MEET_STATUS_CONFIRMED)
                    ->orWhereIn('meet_status', SalesConversation::MEET_CLOSED_STATUSES);
            });

        $this->applyEstadoFilter($query, $estado, $graceBefore);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('phone', 'ilike', "%{$search}%")
                    ->orWhere('prospect_name', 'ilike', "%{$search}%")
                    ->orWhere('meet_link', 'ilike', "%{$search}%")
                    ->orWhere('meet_outcome_note', 'ilike', "%{$search}%");
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
                'meet_at' => $c->meet_at
                    ? $c->meet_at->timezone('America/Lima')->toIso8601String()
                    : null,
                'meet_proposed_at' => $c->meet_proposed_at
                    ? $c->meet_proposed_at->timezone('America/Lima')->toIso8601String()
                    : null,
                'meet_link' => $c->meet_link,
                'google_event_id' => $c->google_event_id,
                'meet_notified_at' => $c->meet_notified_at?->toIso8601String(),
                'meet_completed_at' => $c->meet_completed_at
                    ? $c->meet_completed_at->timezone('America/Lima')->toIso8601String()
                    : null,
                'meet_outcome_note' => $c->meet_outcome_note,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'needs_close' => $this->needsClose($c, $graceBefore),
            ]);

        $stats = [
            'confirmadas' => $this->baseOpenConfirmedQuery()->count(),
            'propuestas' => SalesConversation::query()
                ->where('meet_status', SalesConversation::MEET_STATUS_PROPOSED)
                ->whereNull('meet_link')
                ->count(),
            'proximas' => $this->baseOpenConfirmedQuery()
                ->where('meet_at', '>=', $graceBefore)
                ->count(),
            'por_cerrar' => $this->baseOpenConfirmedQuery()
                ->whereNotNull('meet_at')
                ->where('meet_at', '<', $graceBefore)
                ->count(),
            'realizadas' => SalesConversation::query()
                ->whereIn('meet_status', SalesConversation::MEET_CLOSED_STATUSES)
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

    public function updateStatus(
        UpdateSalesBotMeetingStatusRequest $request,
        SalesConversation $conversation,
    ): RedirectResponse {
        $status = (string) $request->validated('status');
        $note = $request->validated('note');
        $note = is_string($note) ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }

        if ($conversation->meet_link === null && $status !== SalesConversation::MEET_STATUS_CANCELLED) {
            return back()->withErrors([
                'status' => 'Solo se pueden cerrar reuniones con Meet confirmado.',
            ]);
        }

        if ($status === SalesConversation::MEET_STATUS_CONFIRMED) {
            $conversation->meet_status = SalesConversation::MEET_STATUS_CONFIRMED;
            $conversation->meet_completed_at = null;
            $conversation->meet_outcome_note = $note;
            $conversation->save();

            return back()->with('success', 'Reunión reabierta como confirmada.');
        }

        $conversation->meet_status = $status;
        $conversation->meet_completed_at = now();
        $conversation->meet_outcome_note = $note;
        $conversation->save();

        $label = match ($status) {
            SalesConversation::MEET_STATUS_COMPLETED => 'marcada como realizada',
            SalesConversation::MEET_STATUS_NO_SHOW => 'marcada como no asistió',
            default => 'marcada como cancelada',
        };

        return back()->with('success', "Reunión {$label}.");
    }

    /**
     * @param  Builder<SalesConversation>  $query
     */
    private function applyEstadoFilter(Builder $query, string $estado, CarbonInterface $graceBefore): void
    {
        if ($estado === 'confirmadas') {
            $this->constrainOpenConfirmed($query);

            return;
        }

        if ($estado === 'propuestas') {
            $query->where('meet_status', SalesConversation::MEET_STATUS_PROPOSED)
                ->whereNull('meet_link');

            return;
        }

        if ($estado === 'proximas') {
            $this->constrainOpenConfirmed($query)
                ->where('meet_at', '>=', $graceBefore);

            return;
        }

        if ($estado === 'por_cerrar') {
            $this->constrainOpenConfirmed($query)
                ->whereNotNull('meet_at')
                ->where('meet_at', '<', $graceBefore);

            return;
        }

        if ($estado === 'realizadas') {
            $query->whereIn('meet_status', SalesConversation::MEET_CLOSED_STATUSES);

            return;
        }

        // todas: sin filtro extra de estado
    }

    /**
     * @return Builder<SalesConversation>
     */
    private function baseOpenConfirmedQuery(): Builder
    {
        return $this->constrainOpenConfirmed(SalesConversation::query());
    }

    /**
     * @param  Builder<SalesConversation>  $query
     * @return Builder<SalesConversation>
     */
    private function constrainOpenConfirmed(Builder $query): Builder
    {
        return $query
            ->whereNotNull('meet_link')
            ->where(function ($q): void {
                $q->where('meet_status', SalesConversation::MEET_STATUS_CONFIRMED)
                    ->orWhereNull('meet_status');
            })
            ->where(function ($q): void {
                $q->whereNull('meet_status')
                    ->orWhereNotIn('meet_status', SalesConversation::MEET_CLOSED_STATUSES);
            });
    }

    private function needsClose(SalesConversation $conversation, CarbonInterface $graceBefore): bool
    {
        if ($conversation->meet_link === null || $conversation->meet_at === null) {
            return false;
        }

        if ($conversation->isMeetClosed()) {
            return false;
        }

        $status = $conversation->meet_status;

        if ($status !== null
            && $status !== SalesConversation::MEET_STATUS_CONFIRMED
        ) {
            return false;
        }

        return $conversation->meet_at->lt($graceBefore);
    }
}
