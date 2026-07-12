<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Radar de presencia: ventana abierta (last_seen_at) vs sesión Laravel abierta.
 */
final class PresenceSnapshotService
{
    public const ONLINE_MINUTES = 2;

    /**
     * @return array{
     *     online_users: int,
     *     online_tenants: int,
     *     open_sessions: int,
     *     session_users: int,
     *     superadmins_online: int,
     *     online_window_minutes: int,
     *     session_lifetime_minutes: int,
     *     by_tenant: list<array{
     *         tenant_id: string,
     *         tenant_slug: string,
     *         tenant_label: string,
     *         online_users: int,
     *         open_sessions: int,
     *         session_users: int
     *     }>
     * }
     */
    public function build(): array
    {
        $now = Carbon::now();
        $onlineSince = $now->copy()->subMinutes(self::ONLINE_MINUTES);
        $sessionLifetime = (int) config('session.lifetime', 120);
        $sessionSinceTs = $now->copy()->subMinutes($sessionLifetime)->getTimestamp();

        $onlineUsers = User::query()
            ->whereNotNull('tenant_id')
            ->where('last_seen_at', '>=', $onlineSince)
            ->count();

        $superadminsOnline = User::query()
            ->whereNull('tenant_id')
            ->where('last_seen_at', '>=', $onlineSince)
            ->count();

        $onlineByTenant = User::query()
            ->whereNotNull('tenant_id')
            ->where('last_seen_at', '>=', $onlineSince)
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $sessionRows = collect();
        if (config('session.driver') === 'database') {
            $sessionRows = DB::table('sessions')
                ->join('users', 'sessions.user_id', '=', 'users.id')
                ->whereNotNull('users.tenant_id')
                ->where('sessions.last_activity', '>=', $sessionSinceTs)
                ->groupBy('users.tenant_id')
                ->selectRaw('users.tenant_id as tenant_id, COUNT(*) as sessions_count, COUNT(DISTINCT sessions.user_id) as users_count')
                ->get()
                ->keyBy('tenant_id');
        }

        $openSessions = (int) $sessionRows->sum(fn ($r) => (int) $r->sessions_count);
        $sessionUsers = (int) $sessionRows->sum(fn ($r) => (int) $r->users_count);

        $tenantIds = $onlineByTenant->keys()
            ->merge($sessionRows->keys())
            ->unique()
            ->values()
            ->all();

        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get(['id', 'slug', 'nombre_comercial', 'razon_social'])
            ->keyBy('id');

        $byTenant = [];
        foreach ($tenantIds as $tenantId) {
            $tenant = $tenants->get($tenantId);
            if ($tenant === null) {
                continue;
            }

            $label = trim((string) ($tenant->nombre_comercial ?: '')) ?: (string) $tenant->razon_social;
            $session = $sessionRows->get($tenantId);

            $byTenant[] = [
                'tenant_id' => (string) $tenantId,
                'tenant_slug' => (string) $tenant->slug,
                'tenant_label' => $label,
                'online_users' => (int) ($onlineByTenant[$tenantId] ?? 0),
                'open_sessions' => (int) ($session->sessions_count ?? 0),
                'session_users' => (int) ($session->users_count ?? 0),
            ];
        }

        usort($byTenant, static function (array $a, array $b): int {
            return ($b['online_users'] <=> $a['online_users'])
                ?: ($b['open_sessions'] <=> $a['open_sessions'])
                ?: strcmp($a['tenant_slug'], $b['tenant_slug']);
        });

        return [
            'online_users' => $onlineUsers,
            'online_tenants' => $onlineByTenant->count(),
            'open_sessions' => $openSessions,
            'session_users' => $sessionUsers,
            'superadmins_online' => $superadminsOnline,
            'online_window_minutes' => self::ONLINE_MINUTES,
            'session_lifetime_minutes' => $sessionLifetime,
            'by_tenant' => array_slice($byTenant, 0, 40),
        ];
    }
}
