<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAuthSessionLog;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Abre y cierra filas del historial de sesiones de login (plataforma).
 */
final class UserAuthSessionLogger
{
    public function openFromLogin(User $user, ?Request $request = null): UserAuthSessionLog
    {
        $request ??= request();
        $tenant = $this->resolveTenant($user);
        $sessionId = null;

        try {
            $sessionId = session()->getId();
        } catch (\Throwable) {
            $sessionId = null;
        }

        if (is_string($sessionId) && $sessionId === '') {
            $sessionId = null;
        }

        return UserAuthSessionLog::query()->create([
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant?->getKey(),
            'session_id' => $sessionId,
            'user_name' => (string) $user->name,
            'user_email' => (string) $user->email,
            'tenant_slug' => $tenant?->slug,
            'plan_codigo' => $this->resolvePlanCodigo($tenant),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'logged_in_at' => now(),
        ]);
    }

    public function closeBySessionId(
        ?string $sessionId,
        string $reason = UserAuthSessionLog::REASON_LOGOUT,
        ?CarbonInterface $endedAt = null,
    ): int {
        if ($sessionId === null || $sessionId === '') {
            return 0;
        }

        return UserAuthSessionLog::query()
            ->where('session_id', $sessionId)
            ->whereNull('logged_out_at')
            ->update([
                'logged_out_at' => $endedAt ?? now(),
                'logout_reason' => $reason,
            ]);
    }

    /**
     * Tras Logout, Laravel regenera/destruye la cookie anterior antes del evento.
     * Cierra filas abiertas del usuario cuya session_id ya no existe en `sessions`
     * (la sesión de este dispositivo), sin tocar otras sesiones activas.
     */
    public function closeDestroyedSessionsForUser(
        ?User $user,
        string $reason = UserAuthSessionLog::REASON_LOGOUT,
        ?CarbonInterface $endedAt = null,
    ): int {
        if ($user === null) {
            return 0;
        }

        $openLogs = UserAuthSessionLog::query()
            ->where('user_id', $user->getKey())
            ->whereNull('logged_out_at')
            ->get();

        if ($openLogs->isEmpty()) {
            return 0;
        }

        $sessionIds = $openLogs
            ->pluck('session_id')
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        $aliveIds = $sessionIds === []
            ? []
            : DB::table('sessions')
                ->whereIn('id', $sessionIds)
                ->pluck('id')
                ->all();

        $aliveLookup = array_fill_keys($aliveIds, true);
        $closed = 0;
        $endedAt ??= now();

        foreach ($openLogs as $log) {
            $sessionId = $log->session_id;

            if (is_string($sessionId) && $sessionId !== '' && isset($aliveLookup[$sessionId])) {
                continue;
            }

            $log->forceFill([
                'logged_out_at' => $endedAt,
                'logout_reason' => $reason,
            ])->save();
            $closed++;
        }

        return $closed;
    }

    /**
     * Cierra sesiones abiertas cuya cookie Laravel ya no existe o expiró por idle.
     *
     * @return int Filas cerradas
     */
    public function expireStaleSessions(?int $lifetimeMinutes = null): int
    {
        $lifetimeMinutes ??= max(1, (int) config('session.lifetime', 120));
        $thresholdTs = now()->subMinutes($lifetimeMinutes)->getTimestamp();
        $closed = 0;

        UserAuthSessionLog::query()
            ->open()
            ->whereNotNull('session_id')
            ->orderBy('logged_in_at')
            ->chunkById(100, function ($logs) use ($thresholdTs, &$closed): void {
                /** @var \Illuminate\Support\Collection<int, UserAuthSessionLog> $logs */
                $sessionIds = $logs->pluck('session_id')->filter()->values()->all();
                $sessions = DB::table('sessions')
                    ->whereIn('id', $sessionIds)
                    ->get(['id', 'last_activity'])
                    ->keyBy('id');

                foreach ($logs as $log) {
                    $sessionId = (string) $log->session_id;
                    $session = $sessions->get($sessionId);

                    if ($session === null) {
                        $closed += $this->closeBySessionId(
                            $sessionId,
                            UserAuthSessionLog::REASON_EXPIRED,
                            now(),
                        );

                        continue;
                    }

                    $lastActivity = (int) $session->last_activity;

                    if ($lastActivity < $thresholdTs) {
                        $endedAt = \Illuminate\Support\Carbon::createFromTimestamp($lastActivity);
                        $closed += $this->closeBySessionId(
                            $sessionId,
                            UserAuthSessionLog::REASON_EXPIRED,
                            $endedAt,
                        );
                    }
                }
            });

        return $closed;
    }

    private function resolveTenant(User $user): ?Tenant
    {
        if ($user->tenant_id === null) {
            return null;
        }

        $user->loadMissing('tenant');

        return $user->tenant;
    }

    private function resolvePlanCodigo(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return 'unknown';
        }

        $subscription = $tenant->activeSubscription();

        if ($subscription === null) {
            return 'unknown';
        }

        $subscription->loadMissing('plan');
        $codigo = $subscription->plan?->codigo;

        if (! is_string($codigo) || $codigo === '') {
            return 'unknown';
        }

        return $codigo;
    }
}
