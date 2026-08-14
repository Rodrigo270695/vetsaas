<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Snapshot de marketing / geo para el dashboard de Reportes del superadmin.
 *
 * Segmenta clínicas en pago vs free según la suscripción viva y el
 * `plans.codigo`. La ubicación sale de `tenants.distrito_id` (catálogo público).
 */
final class PlataformaReportesSnapshotService
{
    private const VIVAS = ['trial', 'active', 'grace', 'suspended'];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = Carbon::now();

        $tenants = Tenant::query()
            ->with([
                'distritoModel:id,name,provincia_id',
                'distritoModel.provincia:id,name,departamento_id',
                'distritoModel.provincia.departamento:id,name',
            ])
            ->get([
                'id',
                'slug',
                'razon_social',
                'nombre_comercial',
                'distrito_id',
                'estado',
                'created_at',
                'canal_adquisicion',
                'trial_ends_at',
            ]);

        $subsByTenant = $this->latestVivaSubscriptionByTenant();

        $paidRows = [];
        $freeRows = [];
        $cancelled = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->estado === 'cancelled') {
                $cancelled++;

                continue;
            }

            $sub = $subsByTenant->get((string) $tenant->id);
            $isFree = $sub === null || $sub->plan?->isFree() === true;
            $row = $this->tenantRow($tenant, $sub);

            if ($isFree) {
                $freeRows[] = $row;
            } else {
                $paidRows[] = $row;
            }
        }

        $paid = collect($paidRows);
        $free = collect($freeRows);

        return [
            'generated_at' => $now->toIso8601String(),
            'kpis' => [
                'total_vivos' => $paid->count() + $free->count(),
                'paid' => $paid->count(),
                'free' => $free->count(),
                'cancelled' => $cancelled,
                'paid_sin_ubicacion' => $paid->where('departamento_id', null)->count(),
                'free_sin_ubicacion' => $free->where('departamento_id', null)->count(),
                'pct_paid' => $this->pct($paid->count(), $paid->count() + $free->count()),
                'pct_free' => $this->pct($free->count(), $paid->count() + $free->count()),
            ],
            'insights' => $this->insights($paid, $free),
            'paid' => $this->segmentBlock($paid),
            'free' => $this->segmentBlock($free),
            'comparativo_departamentos' => $this->comparativoDepartamentos($paid, $free),
            'flujo_suscripciones' => $this->flujoSuscripciones($subsByTenant),
            'crecimiento_mensual' => $this->crecimientoMensual($tenants, $subsByTenant, $now),
            'ingresos_mensuales' => $this->ingresosMensuales($now),
            'mix_planes' => $this->mixPlanes($subsByTenant),
            'canales' => $this->canales($tenants, $subsByTenant),
            'estados_tenant' => $this->estadosTenant($tenants),
        ];
    }

    /**
     * @return Collection<string, Subscription>
     */
    private function latestVivaSubscriptionByTenant(): Collection
    {
        $subs = Subscription::query()
            ->with(['plan:id,codigo,nombre'])
            ->whereIn('estado', self::VIVAS)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'tenant_id',
                'plan_id',
                'estado',
                'ciclo',
                'precio_pactado',
                'created_at',
                'proximo_cobro_at',
                'current_period_end',
            ]);

        /** @var Collection<string, Subscription> $byTenant */
        $byTenant = collect();

        foreach ($subs as $sub) {
            $tid = (string) $sub->tenant_id;
            if (! $byTenant->has($tid)) {
                $byTenant->put($tid, $sub);
            }
        }

        return $byTenant;
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     slug: string,
     *     label: string,
     *     estado: string,
     *     canal: string|null,
     *     created_at: string|null,
     *     departamento_id: int|null,
     *     departamento: string|null,
     *     provincia_id: int|null,
     *     provincia: string|null,
     *     distrito_id: int|null,
     *     distrito: string|null,
     *     plan_codigo: string|null,
     *     plan_nombre: string|null,
     *     sub_estado: string|null
     * }
     */
    private function tenantRow(Tenant $tenant, ?Subscription $sub): array
    {
        $dep = $tenant->distritoModel?->provincia?->departamento;
        $prov = $tenant->distritoModel?->provincia;
        $dist = $tenant->distritoModel;
        $label = trim((string) ($tenant->nombre_comercial ?: '')) !== ''
            ? (string) $tenant->nombre_comercial
            : (string) $tenant->razon_social;

        return [
            'tenant_id' => (string) $tenant->id,
            'slug' => (string) $tenant->slug,
            'label' => $label !== '' ? $label : (string) $tenant->slug,
            'estado' => (string) $tenant->estado,
            'canal' => $tenant->canal_adquisicion,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'departamento_id' => $dep?->id,
            'departamento' => $dep?->name,
            'provincia_id' => $prov?->id,
            'provincia' => $prov?->name,
            'distrito_id' => $dist?->id,
            'distrito' => $dist?->name,
            'plan_codigo' => $sub?->plan?->codigo,
            'plan_nombre' => $sub?->plan?->nombre,
            'sub_estado' => $sub?->estado,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function segmentBlock(Collection $rows): array
    {
        $byDep = $this->groupCounts($rows, 'departamento', 'departamento_id');
        $byProv = $this->groupCounts($rows, 'provincia', 'provincia_id');
        $maxDep = max(1, (int) collect($byDep)->max('count'));

        $heatmap = collect($byDep)
            ->map(static function (array $row) use ($maxDep): array {
                $row['intensity'] = round($row['count'] / $maxDep, 4);

                return $row;
            })
            ->values()
            ->all();

        return [
            'total' => $rows->count(),
            'con_ubicacion' => $rows->whereNotNull('departamento_id')->count(),
            'sin_ubicacion' => $rows->whereNull('departamento_id')->count(),
            'por_departamento' => $byDep,
            'por_provincia' => array_slice($byProv, 0, 15),
            'heatmap_departamentos' => $heatmap,
            'top_departamentos' => array_slice($byDep, 0, 8),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{id: int|null, name: string, count: int}>
     */
    private function groupCounts(Collection $rows, string $nameKey, string $idKey): array
    {
        return $rows
            ->groupBy(static function (array $row) use ($nameKey): string {
                $name = $row[$nameKey] ?? null;

                return is_string($name) && $name !== '' ? $name : 'Sin ubicación';
            })
            ->map(static function (Collection $group, string $name) use ($idKey): array {
                $first = $group->first();

                return [
                    'id' => is_array($first) ? ($first[$idKey] ?? null) : null,
                    'name' => $name,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paid
     * @param  Collection<int, array<string, mixed>>  $free
     * @return list<array{name: string, paid: int, free: int, total: int}>
     */
    private function comparativoDepartamentos(Collection $paid, Collection $free): array
    {
        $map = [];

        foreach ([['paid', $paid], ['free', $free]] as [$key, $rows]) {
            foreach ($rows as $row) {
                $name = is_string($row['departamento'] ?? null) && $row['departamento'] !== ''
                    ? (string) $row['departamento']
                    : 'Sin ubicación';
                if (! isset($map[$name])) {
                    $map[$name] = ['name' => $name, 'paid' => 0, 'free' => 0, 'total' => 0];
                }
                $map[$name][$key]++;
                $map[$name]['total']++;
            }
        }

        return collect($map)
            ->sortByDesc('total')
            ->values()
            ->take(20)
            ->all();
    }

    /**
     * @param  Collection<string, Subscription>  $subsByTenant
     * @return array{labels: list<string>, values: list<int>, total: int}
     */
    private function flujoSuscripciones(Collection $subsByTenant): array
    {
        $order = ['trial', 'active', 'grace', 'suspended'];
        $counts = array_fill_keys($order, 0);

        foreach ($subsByTenant as $sub) {
            if ($sub->plan?->isFree()) {
                continue;
            }
            $estado = (string) $sub->estado;
            if (isset($counts[$estado])) {
                $counts[$estado]++;
            }
        }

        return [
            'labels' => $order,
            'values' => array_map(static fn (string $k): int => $counts[$k], $order),
            'total' => array_sum($counts),
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @param  Collection<string, Subscription>  $subsByTenant
     * @return list<array{month: string, label: string, paid: int, free: int}>
     */
    private function crecimientoMensual(Collection $tenants, Collection $subsByTenant, Carbon $now): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i);
            $key = $m->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $m->translatedFormat('M Y'),
                'paid' => 0,
                'free' => 0,
            ];
        }

        foreach ($tenants as $tenant) {
            if ($tenant->estado === 'cancelled' || $tenant->created_at === null) {
                continue;
            }
            $key = $tenant->created_at->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $sub = $subsByTenant->get((string) $tenant->id);
            $isFree = $sub === null || $sub->plan?->isFree() === true;
            $months[$key][$isFree ? 'free' : 'paid']++;
        }

        return array_values($months);
    }

    /**
     * @return list<array{month: string, label: string, total: float, count: int}>
     */
    private function ingresosMensuales(Carbon $now): array
    {
        $from = $now->copy()->startOfMonth()->subMonths(11);
        $rows = SubscriptionPayment::query()
            ->where('estado', 'procesado')
            ->whereNotNull('pagado_at')
            ->where('pagado_at', '>=', $from)
            ->whereHas('plan', static fn ($q) => $q->excludingFree())
            ->get(['total', 'pagado_at']);

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i);
            $key = $m->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $m->translatedFormat('M Y'),
                'total' => 0.0,
                'count' => 0,
            ];
        }

        foreach ($rows as $pay) {
            $key = $pay->pagado_at?->format('Y-m');
            if ($key === null || ! isset($months[$key])) {
                continue;
            }
            $months[$key]['total'] = round($months[$key]['total'] + (float) (string) $pay->total, 2);
            $months[$key]['count']++;
        }

        return array_values($months);
    }

    /**
     * @param  Collection<string, Subscription>  $subsByTenant
     * @return list<array{codigo: string, nombre: string, count: int}>
     */
    private function mixPlanes(Collection $subsByTenant): array
    {
        $map = [];

        foreach ($subsByTenant as $sub) {
            if ($sub->plan === null || $sub->plan->isFree()) {
                continue;
            }
            $codigo = (string) $sub->plan->codigo;
            if (! isset($map[$codigo])) {
                $map[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => (string) $sub->plan->nombre,
                    'count' => 0,
                ];
            }
            $map[$codigo]['count']++;
        }

        return collect($map)->sortByDesc('count')->values()->all();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @param  Collection<string, Subscription>  $subsByTenant
     * @return list<array{canal: string, paid: int, free: int, total: int}>
     */
    private function canales(Collection $tenants, Collection $subsByTenant): array
    {
        $map = [];

        foreach ($tenants as $tenant) {
            if ($tenant->estado === 'cancelled') {
                continue;
            }
            $canal = trim((string) ($tenant->canal_adquisicion ?? ''));
            $canal = $canal !== '' ? $canal : 'sin_canal';
            if (! isset($map[$canal])) {
                $map[$canal] = ['canal' => $canal, 'paid' => 0, 'free' => 0, 'total' => 0];
            }
            $sub = $subsByTenant->get((string) $tenant->id);
            $isFree = $sub === null || $sub->plan?->isFree() === true;
            $map[$canal][$isFree ? 'free' : 'paid']++;
            $map[$canal]['total']++;
        }

        return collect($map)->sortByDesc('total')->values()->all();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{estado: string, count: int}>
     */
    private function estadosTenant(Collection $tenants): array
    {
        return $tenants
            ->groupBy('estado')
            ->map(static fn (Collection $g, string $estado): array => [
                'estado' => $estado,
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paid
     * @param  Collection<int, array<string, mixed>>  $free
     * @return array{
     *     top_paid_departamento: string|null,
     *     top_free_departamento: string|null,
     *     oportunidad_ads: string|null,
     *     cobertura_geo_pct: float
     * }
     */
    private function insights(Collection $paid, Collection $free): array
    {
        $topPaid = $this->topName($paid, 'departamento');
        $topFree = $this->topName($free, 'departamento');

        $oportunidad = null;
        if ($topFree !== null && $topFree !== $topPaid) {
            $oportunidad = $topFree;
        } elseif ($topFree !== null) {
            $oportunidad = $topFree;
        }

        $vivos = $paid->count() + $free->count();
        $conGeo = $paid->whereNotNull('departamento_id')->count()
            + $free->whereNotNull('departamento_id')->count();

        return [
            'top_paid_departamento' => $topPaid,
            'top_free_departamento' => $topFree,
            'oportunidad_ads' => $oportunidad,
            'cobertura_geo_pct' => $this->pct($conGeo, $vivos),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function topName(Collection $rows, string $key): ?string
    {
        $grouped = $rows
            ->filter(static fn (array $r): bool => is_string($r[$key] ?? null) && $r[$key] !== '')
            ->groupBy($key)
            ->map->count()
            ->sortDesc();

        $name = $grouped->keys()->first();

        return is_string($name) ? $name : null;
    }

    private function pct(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
