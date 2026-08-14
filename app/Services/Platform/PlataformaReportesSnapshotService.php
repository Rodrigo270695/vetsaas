<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Sede;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Clinic\ClinicBrandingUrls;
use App\Support\Geo\PeruDepartamentoCentroids;
use App\Support\Subscriptions\SubscriptionExpiry;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Snapshot de marketing / geo para Reportes del superadmin.
 *
 * Geo de departamentos: sedes activas (`public.sedes`).
 * Mapa: GPS del tenant si aceptó; si no, centroide del departamento de la sede.
 */
final class PlataformaReportesSnapshotService
{
    private const VIVAS = ['trial', 'active', 'grace', 'suspended'];

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = Carbon::now();

        $tenants = Tenant::query()
            ->get([
                'id',
                'slug',
                'razon_social',
                'nombre_comercial',
                'distrito_id',
                'estado',
                'created_at',
                'canal_adquisicion',
                'geo_lat',
                'geo_lng',
                'geo_consent_at',
                'geo_captured_at',
            ]);

        $subsByTenant = $this->latestVivaSubscriptionByTenant();

        $sedes = Sede::query()
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->with([
                'distritoModel:id,name,provincia_id',
                'distritoModel.provincia:id,name,departamento_id',
                'distritoModel.provincia.departamento:id,name',
            ])
            ->get([
                'id',
                'tenant_id',
                'nombre',
                'distrito_id',
                'distrito',
                'provincia',
                'departamento',
                'activa',
            ]);

        $sedesByTenant = $sedes->groupBy(fn (Sede $s): string => (string) $s->tenant_id);

        $paidRows = [];
        $freeRows = [];
        $cancelled = 0;
        $mapMarkers = [];

        foreach ($tenants as $tenant) {
            if ($tenant->estado === 'cancelled') {
                $cancelled++;

                continue;
            }

            $sub = $subsByTenant->get((string) $tenant->id);
            $isFree = $sub === null || $sub->plan?->isFree() === true;
            $tenantSedes = $sedesByTenant->get((string) $tenant->id, collect());
            $geo = $this->resolveTenantGeo($tenant, $tenantSedes);
            $branding = ClinicBrandingUrls::resolveForTenant($this->tenants, $tenant);

            $row = [
                'tenant_id' => (string) $tenant->id,
                'slug' => (string) $tenant->slug,
                'label' => $this->tenantLabel($tenant),
                'estado' => (string) $tenant->estado,
                'canal' => $tenant->canal_adquisicion,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'departamento_id' => $geo['departamento_id'],
                'departamento' => $geo['departamento'],
                'provincia_id' => $geo['provincia_id'],
                'provincia' => $geo['provincia'],
                'distrito_id' => $geo['distrito_id'],
                'distrito' => $geo['distrito'],
                'plan_codigo' => $sub?->plan?->codigo,
                'plan_nombre' => $sub?->plan?->nombre,
                'sub_estado' => $sub?->estado,
                'segment' => $isFree ? 'free' : 'paid',
            ];

            if ($isFree) {
                $freeRows[] = $row;
            } else {
                $paidRows[] = $row;
            }

            if ($geo['lat'] !== null && $geo['lng'] !== null) {
                $lat = (float) $geo['lat'];
                $lng = (float) $geo['lng'];

                // Solo aproximados: dispersión radial (no diagonal) para no apilar el mismo centroide.
                if ($geo['source'] === 'departamento') {
                    $hash = crc32((string) $tenant->id);
                    $angle = (($hash % 360) / 360.0) * 2 * M_PI;
                    $radius = 0.06 + (($hash % 50) / 500.0); // ~0.06°–0.16°
                    $lat += sin($angle) * $radius;
                    $lng += cos($angle) * $radius;
                }

                $mapMarkers[] = [
                    'tenant_id' => (string) $tenant->id,
                    'slug' => (string) $tenant->slug,
                    'label' => $this->tenantLabel($tenant),
                    'segment' => $isFree ? 'free' : 'paid',
                    'lat' => $lat,
                    'lng' => $lng,
                    'source' => $geo['source'],
                    'departamento' => $geo['departamento'],
                    'logo_url' => $branding['logo_url'],
                    'has_custom_logo' => $branding['has_custom_logo'],
                ];
            }
        }

        $paid = collect($paidRows);
        $free = collect($freeRows);
        $churn = $this->churnMetrics($tenants, $subsByTenant);

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
                'map_markers' => count($mapMarkers),
                'gps_consents' => $tenants->whereNotNull('geo_consent_at')->count(),
                'churned' => (int) ($churn['total'] ?? 0),
                'churned_one_payment' => (int) ($churn['one_payment'] ?? 0),
                'ever_paid' => (int) ($churn['ever_paid'] ?? 0),
                'pct_churned' => (float) ($churn['pct'] ?? 0),
            ],
            'insights' => [
                ...$this->insights($paid, $free),
                'churned' => (int) ($churn['total'] ?? 0),
                'churned_one_payment' => (int) ($churn['one_payment'] ?? 0),
            ],
            'paid' => $this->segmentBlock($paid),
            'free' => $this->segmentBlock($free),
            'churn' => $churn,
            'comparativo_departamentos' => $this->comparativoDepartamentos($paid, $free),
            'flujo_suscripciones' => $this->flujoSuscripciones($subsByTenant),
            'crecimiento_mensual' => $this->crecimientoMensual($tenants, $subsByTenant, $now),
            'ingresos_mensuales' => $this->ingresosMensuales($now),
            'mix_planes' => $this->mixPlanes($subsByTenant),
            'canales' => $this->canales($tenants, $subsByTenant),
            'estados_tenant' => $this->estadosTenant($tenants),
            'map_markers' => $mapMarkers,
        ];
    }

    /**
     * @param  Collection<int, Sede>  $sedes
     * @return array{
     *     departamento_id: int|null,
     *     departamento: string|null,
     *     provincia_id: int|null,
     *     provincia: string|null,
     *     distrito_id: int|null,
     *     distrito: string|null,
     *     lat: float|null,
     *     lng: float|null,
     *     source: 'gps'|'departamento'|null
     * }
     */
    private function resolveTenantGeo(Tenant $tenant, Collection $sedes): array
    {
        $withGeo = $sedes->first(fn (Sede $s): bool => $s->distrito_id !== null)
            ?? $sedes->first();

        $dep = $withGeo?->distritoModel?->provincia?->departamento;
        $prov = $withGeo?->distritoModel?->provincia;
        $dist = $withGeo?->distritoModel;

        $departamentoName = $dep?->name
            ?? (is_string($withGeo?->departamento) && $withGeo->departamento !== '' ? $withGeo->departamento : null);
        // Unifica "Lima" (catálogo) vs "LIMA" (texto legacy en sede).
        $departamentoName = PeruDepartamentoCentroids::canonicalLabel($departamentoName);

        $lat = null;
        $lng = null;
        $source = null;

        if ($tenant->geo_lat !== null && $tenant->geo_lng !== null && $tenant->geo_consent_at !== null) {
            $lat = (float) (string) $tenant->geo_lat;
            $lng = (float) (string) $tenant->geo_lng;
            $source = 'gps';
        } else {
            $centroid = PeruDepartamentoCentroids::forName($departamentoName);
            if ($centroid !== null) {
                $lat = $centroid['lat'];
                $lng = $centroid['lng'];
                $source = 'departamento';
            }
        }

        return [
            'departamento_id' => $dep?->id,
            'departamento' => $departamentoName,
            'provincia_id' => $prov?->id,
            'provincia' => $prov?->name ?? $withGeo?->provincia,
            'distrito_id' => $dist?->id ?? $withGeo?->distrito_id,
            'distrito' => $dist?->name ?? $withGeo?->distrito,
            'lat' => $lat,
            'lng' => $lng,
            'source' => $source,
        ];
    }

    /**
     * Clínicas que algún día pagaron y hoy no tienen plan de pago al día
     * (vencidas, suspendidas, free o canceladas): no renovaron.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @param  Collection<string, Subscription>  $subsByTenant
     * @return array{
     *     total: int,
     *     one_payment: int,
     *     ever_paid: int,
     *     pct: float,
     *     rows: list<array<string, mixed>>
     * }
     */
    private function churnMetrics(Collection $tenants, Collection $subsByTenant): array
    {
        $tenantsById = $tenants->keyBy(static fn (Tenant $t): string => (string) $t->id);

        $paymentRows = SubscriptionPayment::query()
            ->forBillableOrGateway()
            ->where('estado', 'procesado')
            ->selectRaw('tenant_id, COUNT(*) as pagos_count, MAX(COALESCE(pagado_at, created_at)) as last_paid_at')
            ->groupBy('tenant_id')
            ->get();

        if ($paymentRows->isEmpty()) {
            return [
                'total' => 0,
                'one_payment' => 0,
                'ever_paid' => 0,
                'pct' => 0.0,
                'rows' => [],
            ];
        }

        $subsForExpiry = Subscription::query()
            ->with(['plan:id,codigo,nombre'])
            ->whereIn('tenant_id', $paymentRows->pluck('tenant_id'))
            ->whereIn('estado', [...self::VIVAS, 'cancelled'])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'tenant_id',
                'plan_id',
                'estado',
                'proximo_cobro_at',
                'current_period_end',
                'grace_ends_at',
                'trial_ends_at',
                'created_at',
            ]);

        $latestSubByTenant = collect();
        foreach ($subsForExpiry as $sub) {
            $tid = (string) $sub->tenant_id;
            if (! $latestSubByTenant->has($tid)) {
                $latestSubByTenant->put($tid, $sub);
            }
        }

        $churned = [];
        foreach ($paymentRows as $paymentRow) {
            $tenantId = (string) $paymentRow->tenant_id;
            $tenant = $tenantsById->get($tenantId);
            if (! $tenant instanceof Tenant) {
                continue;
            }

            $pagosCount = (int) $paymentRow->pagos_count;
            $sub = $latestSubByTenant->get($tenantId) ?? $subsByTenant->get($tenantId);

            if ($this->isHealthyPaidSubscription($sub, $tenant)) {
                continue;
            }

            $reason = $this->churnReason($sub, $tenant);

            $churned[] = [
                'tenant_id' => $tenantId,
                'slug' => (string) $tenant->slug,
                'label' => $this->tenantLabel($tenant),
                'pagos_count' => $pagosCount,
                'last_paid_at' => $paymentRow->last_paid_at
                    ? Carbon::parse((string) $paymentRow->last_paid_at)->toIso8601String()
                    : null,
                'reason' => $reason,
                'sub_estado' => $sub instanceof Subscription ? (string) $sub->estado : null,
                'plan_nombre' => $sub instanceof Subscription ? ($sub->plan?->nombre) : null,
            ];
        }

        usort($churned, static function (array $a, array $b): int {
            return ($b['pagos_count'] <=> $a['pagos_count'])
                ?: strcmp((string) $a['label'], (string) $b['label']);
        });

        $total = count($churned);
        $onePayment = count(array_filter(
            $churned,
            static fn (array $row): bool => (int) $row['pagos_count'] === 1,
        ));
        $everPaid = $paymentRows->count();

        return [
            'total' => $total,
            'one_payment' => $onePayment,
            'ever_paid' => $everPaid,
            'pct' => $this->pct($total, $everPaid),
            'rows' => array_slice($churned, 0, 40),
        ];
    }

    private function isHealthyPaidSubscription(?Subscription $sub, Tenant $tenant): bool
    {
        if ($tenant->estado === 'cancelled') {
            return false;
        }

        if (! $sub instanceof Subscription) {
            return false;
        }

        if ($sub->plan?->isFree() === true) {
            return false;
        }

        if (in_array($sub->estado, ['suspended', 'cancelled'], true)) {
            return false;
        }

        if (! in_array($sub->estado, ['trial', 'active', 'grace'], true)) {
            return false;
        }

        $daysUntil = SubscriptionExpiry::daysUntil(
            SubscriptionExpiry::anchor($sub, $tenant),
        );

        // Sin fecha de vencimiento pero activa → se considera al día.
        if ($daysUntil === null) {
            return true;
        }

        // Vencido (días negativos) = no renovó.
        return $daysUntil >= 0;
    }

    private function churnReason(?Subscription $sub, Tenant $tenant): string
    {
        if ($tenant->estado === 'cancelled') {
            return 'tenant_cancelled';
        }

        if (! $sub instanceof Subscription) {
            return 'no_subscription';
        }

        if ($sub->plan?->isFree() === true) {
            return 'back_to_free';
        }

        if ($sub->estado === 'suspended') {
            return 'suspended';
        }

        if ($sub->estado === 'cancelled') {
            return 'subscription_cancelled';
        }

        $daysUntil = SubscriptionExpiry::daysUntil(
            SubscriptionExpiry::anchor($sub, $tenant),
        );

        if ($daysUntil !== null && $daysUntil < 0) {
            return 'expired';
        }

        return 'other';
    }

    private function tenantLabel(Tenant $tenant): string
    {
        $label = trim((string) ($tenant->nombre_comercial ?: ''));

        return $label !== '' ? $label : (string) ($tenant->razon_social ?: $tenant->slug);
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
            ]);

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
            ->groupBy(static function (array $row) use ($nameKey, $idKey): string {
                $id = $row[$idKey] ?? null;
                if (is_int($id) || (is_string($id) && $id !== '')) {
                    return 'id:'.$id;
                }

                $name = $row[$nameKey] ?? null;
                if (! is_string($name) || $name === '') {
                    return 'sin';
                }

                return 'name:'.PeruDepartamentoCentroids::normalize($name);
            })
            ->map(static function (Collection $group) use ($nameKey, $idKey): array {
                $first = $group->first();
                $rawName = is_array($first) ? ($first[$nameKey] ?? null) : null;
                $canonical = is_string($rawName)
                    ? PeruDepartamentoCentroids::canonicalLabel($rawName)
                    : null;

                return [
                    'id' => is_array($first) ? ($first[$idKey] ?? null) : null,
                    'name' => $canonical ?? (is_string($rawName) && $rawName !== '' ? $rawName : 'Sin ubicación'),
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
                $raw = is_string($row['departamento'] ?? null) && $row['departamento'] !== ''
                    ? (string) $row['departamento']
                    : null;
                $name = PeruDepartamentoCentroids::canonicalLabel($raw) ?? 'Sin ubicación';
                $bucket = PeruDepartamentoCentroids::normalize($name);

                if (! isset($map[$bucket])) {
                    $map[$bucket] = ['name' => $name, 'paid' => 0, 'free' => 0, 'total' => 0];
                }
                $map[$bucket][$key]++;
                $map[$bucket]['total']++;
            }
        }

        return collect($map)->sortByDesc('total')->values()->take(20)->all();
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
        $vivos = $paid->count() + $free->count();
        $conGeo = $paid->whereNotNull('departamento_id')->count()
            + $free->whereNotNull('departamento_id')->count();

        return [
            'top_paid_departamento' => $topPaid,
            'top_free_departamento' => $topFree,
            'oportunidad_ads' => $topFree ?? $topPaid,
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
            ->groupBy(static function (array $r) use ($key): string {
                return PeruDepartamentoCentroids::normalize((string) $r[$key]);
            })
            ->map->count()
            ->sortDesc();

        $norm = $grouped->keys()->first();
        if (! is_string($norm) || $norm === '') {
            return null;
        }

        return PeruDepartamentoCentroids::canonicalLabel($norm) ?? $norm;
    }

    private function pct(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
