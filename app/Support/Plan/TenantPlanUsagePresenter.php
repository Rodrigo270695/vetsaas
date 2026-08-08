<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Listado plataforma: uso de límites del plan por tenant de pago.
 *
 * Reutiliza {@see PlanLimits::snapshot()} y {@see ComprobantesQuota::forTenant()}
 * (misma fuente que Configuración → Mi suscripción).
 */
final class TenantPlanUsagePresenter
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    /** @var list<string> */
    private const SEMAPHORE_RANK = ['over', 'warning', 'caution', 'ok', 'unlimited'];

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, semaphore: string, per_page: int},
     *     stats: array{total: int, over: int, warning: int, caution: int, ok: int}
     * }
     */
    public function paginate(string $search = '', string $semaphoreFilter = 'todos', int $perPage = 15): array
    {
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15;
        $semaphoreFilter = in_array($semaphoreFilter, ['todos', 'over', 'warning', 'caution', 'ok'], true)
            ? $semaphoreFilter
            : 'todos';

        $rows = $this->payingTenantsQuery($search)
            ->get()
            ->map(fn (Tenant $tenant): ?array => $this->presentTenant($tenant))
            ->filter()
            ->values();

        $stats = [
            'total' => $rows->count(),
            'over' => $rows->where('worst_semaphore', 'over')->count(),
            'warning' => $rows->where('worst_semaphore', 'warning')->count(),
            'caution' => $rows->where('worst_semaphore', 'caution')->count(),
            'ok' => $rows->whereIn('worst_semaphore', ['ok', 'unlimited'])->count(),
        ];

        if ($semaphoreFilter !== 'todos') {
            $rows = $rows
                ->filter(static fn (array $row): bool => $row['worst_semaphore'] === $semaphoreFilter)
                ->values();
        }

        $rows = $rows
            ->sort(function (array $a, array $b): int {
                $rank = $this->rankSemaphore($a['worst_semaphore'])
                    <=> $this->rankSemaphore($b['worst_semaphore']);

                if ($rank !== 0) {
                    return $rank;
                }

                return strcasecmp(
                    (string) ($a['tenant']['nombre'] ?? ''),
                    (string) ($b['tenant']['nombre'] ?? ''),
                );
            })
            ->values();

        $page = max(1, (int) request()->integer('page', 1));
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = $rows->forPage($page, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'semaphore' => $semaphoreFilter,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ];
    }

    /**
     * @return Builder<Tenant>
     */
    private function payingTenantsQuery(string $search): Builder
    {
        $query = Tenant::query()
            ->whereHas('subscriptions', function (Builder $q): void {
                $q->whereIn('estado', ['active', 'grace'])
                    ->whereHas('plan', function (Builder $plan): void {
                        $plan->where('codigo', '!=', Plan::CODIGO_FREE);
                    });
            })
            ->with([
                'subscriptions' => function ($q): void {
                    $q->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
                        ->with('plan.features')
                        ->latest();
                },
            ])
            ->orderBy('razon_social');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('razon_social', 'ilike', $like)
                    ->orWhere('nombre_comercial', 'ilike', $like)
                    ->orWhere('slug', 'ilike', $like)
                    ->orWhere('ruc', 'ilike', $like);
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentTenant(Tenant $tenant): ?array
    {
        $subscription = $this->paidSubscription($tenant);

        if ($subscription === null || $subscription->plan === null || $subscription->plan->isFree()) {
            return null;
        }

        $slug = (string) ($tenant->slug ?? '');
        if ($slug === '') {
            return null;
        }

        try {
            /** @var array{limits: array<string, mixed>|null, comprobantes: array<string, mixed>|null} $usage */
            $usage = $this->tenants->runForTenant(
                $tenant,
                function () use ($tenant): array {
                    return [
                        'limits' => PlanLimits::snapshot($tenant),
                        'comprobantes' => ComprobantesQuota::forTenant($tenant),
                    ];
                },
                enforceAccess: false,
            );
        } catch (Throwable $e) {
            report($e);

            return [
                'tenant' => $this->tenantPayload($tenant),
                'subscription' => $this->subscriptionPayload($subscription),
                'limits' => null,
                'comprobantes' => null,
                'worst_semaphore' => 'warning',
                'features_over' => 0,
                'features_warning' => 0,
                'error' => 'No se pudo leer el uso de este tenant.',
            ];
        }

        $limits = $usage['limits'] ?? null;
        $comprobantes = $usage['comprobantes'] ?? null;
        $semaphores = $this->collectSemaphores($limits, $comprobantes);

        return [
            'tenant' => $this->tenantPayload($tenant),
            'subscription' => $this->subscriptionPayload($subscription),
            'limits' => $limits,
            'comprobantes' => $comprobantes,
            'worst_semaphore' => $this->worstSemaphore($semaphores),
            'features_over' => collect($semaphores)->filter(static fn (string $s): bool => $s === 'over')->count(),
            'features_warning' => collect($semaphores)->filter(static fn (string $s): bool => $s === 'warning')->count(),
            'error' => null,
        ];
    }

    private function paidSubscription(Tenant $tenant): ?Subscription
    {
        /** @var Collection<int, Subscription> $subs */
        $subs = $tenant->subscriptions;

        return $subs
            ->filter(static function (Subscription $sub): bool {
                if (! in_array($sub->estado, ['active', 'grace'], true)) {
                    return false;
                }

                $plan = $sub->plan;

                return $plan !== null && ! $plan->isFree();
            })
            ->sortByDesc(static fn (Subscription $sub): int => $sub->created_at?->getTimestamp() ?? 0)
            ->first();
    }

    /**
     * @return array{id: string, slug: string, nombre: string, ruc: string|null, estado: string|null}
     */
    private function tenantPayload(Tenant $tenant): array
    {
        return [
            'id' => (string) $tenant->id,
            'slug' => (string) ($tenant->slug ?? ''),
            'nombre' => (string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug),
            'ruc' => $tenant->ruc,
            'estado' => $tenant->estado,
        ];
    }

    /**
     * @return array{id: string, estado: string, ciclo: string|null, plan: array{nombre: string, codigo: string, color_hex: string|null}}
     */
    private function subscriptionPayload(Subscription $subscription): array
    {
        $plan = $subscription->plan;

        return [
            'id' => (string) $subscription->id,
            'estado' => (string) $subscription->estado,
            'ciclo' => $subscription->ciclo,
            'plan' => [
                'nombre' => (string) ($plan?->nombre ?? '—'),
                'codigo' => (string) ($plan?->codigo ?? ''),
                'color_hex' => $plan?->color_hex,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $limits
     * @param  array<string, mixed>|null  $comprobantes
     * @return list<string>
     */
    private function collectSemaphores(?array $limits, ?array $comprobantes): array
    {
        $out = [];

        if (is_array($limits)) {
            foreach (PlanLimits::INT_LIMIT_FEATURES as $feature) {
                $entry = $limits[$feature] ?? null;
                if (is_array($entry) && isset($entry['semaphore']) && is_string($entry['semaphore'])) {
                    $out[] = $entry['semaphore'];
                }
            }
        }

        if (
            is_array($comprobantes)
            && ($comprobantes['enabled'] ?? false)
            && isset($comprobantes['semaphore'])
            && is_string($comprobantes['semaphore'])
        ) {
            $out[] = $comprobantes['semaphore'];
        }

        return $out;
    }

    /**
     * @param  list<string>  $semaphores
     */
    private function worstSemaphore(array $semaphores): string
    {
        if ($semaphores === []) {
            return 'ok';
        }

        $bestRank = count(self::SEMAPHORE_RANK);
        $worst = 'ok';

        foreach ($semaphores as $s) {
            $rank = $this->rankSemaphore($s);
            if ($rank < $bestRank) {
                $bestRank = $rank;
                $worst = $s;
            }
        }

        return $worst === 'unlimited' ? 'ok' : $worst;
    }

    private function rankSemaphore(string $semaphore): int
    {
        $idx = array_search($semaphore, self::SEMAPHORE_RANK, true);

        return $idx === false ? count(self::SEMAPHORE_RANK) : $idx;
    }
}
