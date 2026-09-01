<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Support\Subscriptions\SubscriptionCiclo;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Embudo comercial del SaaS: trials, conversión, riesgo y MRR.
 * Solo lee tablas public (subscriptions / payments); no toca schemas de clínica.
 */
final class SaaSFunnelService
{
    /** @var list<string> */
    public const SCOPES = [
        'atencion',
        'trials',
        'vence_7d',
        'activos',
        'grace',
        'suspended',
        'cancelados_30d',
        'cobro_7d',
    ];

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, scope: string, per_page: int},
     *     stats: array<string, mixed>
     * }
     */
    public function paginate(string $search, string $scope, int $perPage): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'atencion';
        $search = trim($search);
        $now = now();

        $query = Subscription::query()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social,estado',
                'plan:id,nombre,codigo',
            ]);

        $this->applySearch($query, $search);
        $this->applyScope($query, $scope, $now);

        $query
            ->orderByRaw($this->prioritySql())
            ->orderBy('trial_ends_at')
            ->orderBy('proximo_cobro_at');

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Subscription $sub): array => $this->serialize($sub));

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'stats' => $this->stats($now),
        ];
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->whereHas('tenant', function (Builder $tenant) use ($like): void {
                $tenant->where('slug', 'ilike', $like)
                    ->orWhere('nombre_comercial', 'ilike', $like)
                    ->orWhere('razon_social', 'ilike', $like);
            })->orWhereHas('plan', function (Builder $plan) use ($like): void {
                $plan->where('nombre', 'ilike', $like)
                    ->orWhere('codigo', 'ilike', $like);
            });
        });
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applyScope(Builder $query, string $scope, DateTimeInterface $now): void
    {
        $in7 = Carbon::parse($now)->addDays(7);
        $ago30 = Carbon::parse($now)->subDays(30);

        match ($scope) {
            'trials' => $query->where('estado', 'trial'),
            'vence_7d' => $query->where('estado', 'trial')
                ->whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [$now, $in7]),
            'activos' => $query->where('estado', 'active')->billable(),
            'grace' => $query->where('estado', 'grace'),
            'suspended' => $query->where('estado', 'suspended'),
            'cancelados_30d' => $query->where('estado', 'cancelled')
                ->where('cancelled_at', '>=', $ago30),
            'cobro_7d' => $query->where('estado', 'active')
                ->whereNotNull('proximo_cobro_at')
                ->whereBetween('proximo_cobro_at', [$now, $in7]),
            default => $query->where(function (Builder $q) use ($now, $in7): void {
                $q->where(function (Builder $trial) use ($now, $in7): void {
                    $trial->where('estado', 'trial')
                        ->whereNotNull('trial_ends_at')
                        ->whereBetween('trial_ends_at', [$now, $in7]);
                })->orWhereIn('estado', ['grace', 'suspended'])
                    ->orWhere(function (Builder $cobro) use ($now, $in7): void {
                        $cobro->where('estado', 'active')
                            ->whereNotNull('proximo_cobro_at')
                            ->whereBetween('proximo_cobro_at', [$now, $in7]);
                    });
            }),
        };
    }

    private function prioritySql(): string
    {
        return <<<'SQL'
CASE subscriptions.estado
    WHEN 'suspended' THEN 0
    WHEN 'grace' THEN 1
    WHEN 'trial' THEN 2
    WHEN 'active' THEN 3
    ELSE 4
END
SQL;
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(DateTimeInterface $now): array
    {
        $carbon = Carbon::parse($now);
        $in7 = $carbon->copy()->addDays(7);
        $ago30 = $carbon->copy()->subDays(30);
        $monthStart = $carbon->copy()->startOfMonth();
        $monthEnd = $carbon->copy()->endOfMonth();
        $endedUntil = $carbon->lt($monthEnd) ? $carbon : $monthEnd;

        $trials = Subscription::query()->where('estado', 'trial')->count();
        $vence7d = Subscription::query()
            ->where('estado', 'trial')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now, $in7])
            ->count();
        $activos = Subscription::query()->where('estado', 'active')->billable()->count();
        $grace = Subscription::query()->where('estado', 'grace')->count();
        $suspended = Subscription::query()->where('estado', 'suspended')->count();
        $cancelados30d = Subscription::query()
            ->where('estado', 'cancelled')
            ->where('cancelled_at', '>=', $ago30)
            ->count();
        $cobro7d = Subscription::query()
            ->where('estado', 'active')
            ->whereNotNull('proximo_cobro_at')
            ->whereBetween('proximo_cobro_at', [$now, $in7])
            ->count();

        $mrrRows = Subscription::query()
            ->billable()
            ->whereIn('estado', ['active', 'grace'])
            ->get(['precio_pactado', 'ciclo', 'bot_ia_activo', 'bot_ia_precio_mensual']);

        $mrr = 0.0;
        foreach ($mrrRows as $row) {
            $mrr += $this->monthlyAmount($row);
        }

        $cohort = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$monthStart, $endedUntil])
            ->count();
        $converted = Subscription::query()
            ->where('estado', 'active')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$monthStart, $endedUntil])
            ->count();

        $cashMonth = (float) SubscriptionPayment::query()
            ->forBillablePlans()
            ->where('estado', 'procesado')
            ->whereNotNull('pagado_at')
            ->whereBetween('pagado_at', [$monthStart, $monthEnd])
            ->sum('total');

        $fallidos7d = SubscriptionPayment::query()
            ->where('estado', 'fallido')
            ->where('created_at', '>=', $carbon->copy()->subDays(7))
            ->count();

        $pendientes = SubscriptionPayment::query()->where('estado', 'pendiente')->count();

        return [
            'trials' => $trials,
            'vence_7d' => $vence7d,
            'activos' => $activos,
            'grace' => $grace,
            'suspended' => $suspended,
            'cancelados_30d' => $cancelados30d,
            'cobro_7d' => $cobro7d,
            'mrr' => round($mrr, 2),
            'cash_month' => round($cashMonth, 2),
            'conversion_cohort' => $cohort,
            'conversion_converted' => $converted,
            'conversion_pct' => $cohort > 0 ? round(100 * $converted / $cohort, 1) : null,
            'fallidos_7d' => $fallidos7d,
            'pendientes' => $pendientes,
            'currency' => 'PEN',
        ];
    }

    private function monthlyAmount(Subscription $row): float
    {
        $months = max(1, SubscriptionCiclo::months((string) $row->ciclo));
        $base = (float) $row->precio_pactado / $months;
        if ($row->bot_ia_activo) {
            $base += (float) $row->bot_ia_precio_mensual;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Subscription $sub): array
    {
        $tenant = $sub->tenant;
        $nombre = $tenant !== null
            ? (trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug)))
            : '—';

        return [
            'id' => (string) $sub->id,
            'tenant' => [
                'id' => (string) $sub->tenant_id,
                'slug' => $tenant?->slug ?? '—',
                'nombre' => $nombre,
                'estado' => $tenant?->estado,
            ],
            'plan' => $sub->plan?->nombre ?? '—',
            'plan_codigo' => $sub->plan?->codigo,
            'estado' => $sub->estado,
            'ciclo' => $sub->ciclo,
            'precio_pactado' => (float) $sub->precio_pactado,
            'mrr' => round($this->monthlyAmount($sub), 2),
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'grace_ends_at' => $sub->grace_ends_at?->toIso8601String(),
            'proximo_cobro_at' => $sub->proximo_cobro_at?->toIso8601String(),
            'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
        ];
    }
}
