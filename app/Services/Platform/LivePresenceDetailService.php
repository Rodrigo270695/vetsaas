<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPageViewLog;
use App\Support\Platform\PresencePathResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Presencia en vivo con vista/módulo + agregados de flujo.
 */
final class LivePresenceDetailService
{
    /**
     * @return array{
     *     online_window_minutes: int,
     *     online: list<array{
     *         user_id: string,
     *         user_name: string,
     *         user_email: string,
     *         tenant_id: string|null,
     *         tenant_slug: string|null,
     *         tenant_label: string|null,
     *         plan_codigo: string|null,
     *         is_free: bool|null,
     *         last_path: string|null,
     *         last_module: string|null,
     *         last_seen_at: string|null,
     *         last_path_at: string|null
     *     }>,
     *     modules_now: list<array{module: string, users: int}>,
     *     modules_range: list<array{module: string, hits: int}>,
     *     tenants_range: list<array{
     *         tenant_id: string,
     *         tenant_slug: string,
     *         tenant_label: string,
     *         hits: int,
     *         users: int
     *     }>
     * }
     */
    public function build(?string $fechaDesde, ?string $fechaHasta, ?string $planGrupo = null): array
    {
        $onlineSince = Carbon::now()->subMinutes(PresenceSnapshotService::ONLINE_MINUTES);

        $onlineUsers = User::query()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social',
            ])
            ->whereNotNull('tenant_id')
            ->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get();

        $tenantIds = $onlineUsers->pluck('tenant_id')->filter()->unique()->values()->all();
        $planByTenant = $this->planCodigoByTenantIds($tenantIds);

        $online = [];
        $moduleCounts = [];

        foreach ($onlineUsers as $user) {
            $tenant = $user->tenant;
            $tenantId = $user->tenant_id !== null ? (string) $user->tenant_id : null;
            $planCodigo = $tenantId !== null ? ($planByTenant[$tenantId] ?? 'unknown') : null;
            $isFree = $planCodigo === Plan::CODIGO_FREE;

            if ($planGrupo === 'free' && ! $isFree) {
                continue;
            }

            if ($planGrupo === 'paid' && ($planCodigo === null || $isFree)) {
                continue;
            }

            $module = $user->last_module ?: PresencePathResolver::moduleFromPath(
                PresencePathResolver::normalizePath($user->last_path),
            );

            $moduleCounts[$module] = ($moduleCounts[$module] ?? 0) + 1;

            $label = $tenant !== null
                ? (trim((string) ($tenant->nombre_comercial ?: '')) ?: (string) $tenant->razon_social)
                : null;

            $online[] = [
                'user_id' => (string) $user->getKey(),
                'user_name' => (string) $user->name,
                'user_email' => (string) $user->email,
                'tenant_id' => $tenantId,
                'tenant_slug' => $tenant?->slug,
                'tenant_label' => $label,
                'plan_codigo' => $planCodigo,
                'is_free' => $planCodigo !== null ? $isFree : null,
                'last_path' => $user->last_path,
                'last_module' => $module,
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'last_path_at' => $user->last_path_at?->toIso8601String(),
            ];
        }

        arsort($moduleCounts);
        $modulesNow = [];
        foreach ($moduleCounts as $module => $users) {
            $modulesNow[] = [
                'module' => (string) $module,
                'users' => (int) $users,
            ];
        }

        $rangeStart = $fechaDesde !== null
            ? Carbon::parse($fechaDesde)->startOfDay()
            : Carbon::now()->startOfMonth();
        $rangeEnd = $fechaHasta !== null
            ? Carbon::parse($fechaHasta)->endOfDay()
            : Carbon::now()->endOfDay();

        $modulesRange = UserPageViewLog::query()
            ->whereNotNull('tenant_id')
            ->whereBetween('seen_at', [$rangeStart, $rangeEnd])
            ->selectRaw('module, COUNT(*) as hits')
            ->groupBy('module')
            ->orderByDesc('hits')
            ->limit(20)
            ->get()
            ->map(static fn ($row): array => [
                'module' => (string) $row->module,
                'hits' => (int) $row->hits,
            ])
            ->all();

        $tenantsRangeRows = UserPageViewLog::query()
            ->whereNotNull('tenant_id')
            ->whereBetween('seen_at', [$rangeStart, $rangeEnd])
            ->selectRaw('tenant_id, COUNT(*) as hits, COUNT(DISTINCT user_id) as users')
            ->groupBy('tenant_id')
            ->orderByDesc('hits')
            ->limit(20)
            ->get();

        $tenants = \App\Models\Tenant::query()
            ->whereIn('id', $tenantsRangeRows->pluck('tenant_id')->all())
            ->get(['id', 'slug', 'nombre_comercial', 'razon_social'])
            ->keyBy('id');

        $tenantsRange = [];
        foreach ($tenantsRangeRows as $row) {
            $tenant = $tenants->get($row->tenant_id);
            if ($tenant === null) {
                continue;
            }

            $label = trim((string) ($tenant->nombre_comercial ?: '')) ?: (string) $tenant->razon_social;
            $tenantsRange[] = [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
                'tenant_label' => $label,
                'hits' => (int) $row->hits,
                'users' => (int) $row->users,
            ];
        }

        return [
            'online_window_minutes' => PresenceSnapshotService::ONLINE_MINUTES,
            'online' => $online,
            'modules_now' => $modulesNow,
            'modules_range' => $modulesRange,
            'tenants_range' => $tenantsRange,
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, string>
     */
    private function planCodigoByTenantIds(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $rows = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.tenant_id', $tenantIds)
            ->whereIn('subscriptions.estado', ['trial', 'active', 'grace'])
            ->orderByDesc('subscriptions.created_at')
            ->get(['subscriptions.tenant_id', 'plans.codigo']);

        $map = [];
        foreach ($rows as $row) {
            $tid = (string) $row->tenant_id;
            if (! isset($map[$tid])) {
                $map[$tid] = (string) $row->codigo;
            }
        }

        return $map;
    }
}
