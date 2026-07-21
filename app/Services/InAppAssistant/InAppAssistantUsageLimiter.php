<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Tope diario de mensajes del asistente in-app (por usuario + tenant).
 * Superadmin (portal central) no tiene límite.
 */
final class InAppAssistantUsageLimiter
{
    public function isUnlimited(User $user): bool
    {
        return $user->isPlatformSuperadmin();
    }

    public function limit(): int
    {
        try {
            return PlatformSetting::current()->assistantDailyLimit();
        } catch (Throwable) {
            return max(1, min(1000, (int) config('in-app-assistant.daily_message_limit', 40)));
        }
    }

    public function keyFor(User $user): string
    {
        $tenant = tenant_id() ?? 'central';

        return 'in-app-assistant:'.$tenant.':'.$user->getAuthIdentifier();
    }

    /**
     * @return array{limit: int|null, used: int, remaining: int|null, resets_in: int, unlimited: bool}
     */
    public function snapshot(User $user): array
    {
        if ($this->isUnlimited($user)) {
            return [
                'limit' => null,
                'used' => 0,
                'remaining' => null,
                'resets_in' => 0,
                'unlimited' => true,
            ];
        }

        $limit = $this->limit();
        $key = $this->keyFor($user);
        $used = min($limit, RateLimiter::attempts($key));

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_in' => RateLimiter::availableIn($key),
            'unlimited' => false,
        ];
    }

    /**
     * Reserva un mensaje antes de ejecutar trabajo costoso.
     *
     * El incremento del backend de caché es atómico: solo las primeras
     * `limit()` reservas se aceptan, incluso con requests concurrentes.
     */
    public function reserve(User $user): bool
    {
        if ($this->isUnlimited($user)) {
            return true;
        }

        $attempt = RateLimiter::increment($this->keyFor($user), $this->decaySeconds());

        return $attempt <= $this->limit();
    }

    private function decaySeconds(): int
    {
        $tz = (string) config('app.timezone', 'America/Lima');
        $seconds = (int) now($tz)->diffInSeconds(now($tz)->endOfDay());

        return max(60, $seconds + 1);
    }
}
