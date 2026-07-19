<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Tope diario de mensajes del asistente in-app (por usuario + tenant).
 */
final class InAppAssistantUsageLimiter
{
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
     * @return array{limit: int, used: int, remaining: int, resets_in: int}
     */
    public function snapshot(User $user): array
    {
        $limit = $this->limit();
        $key = $this->keyFor($user);
        $used = RateLimiter::attempts($key);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_in' => RateLimiter::availableIn($key),
        ];
    }

    public function tooManyAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->keyFor($user), $this->limit());
    }

    public function hit(User $user): void
    {
        RateLimiter::hit($this->keyFor($user), $this->decaySeconds());
    }

    private function decaySeconds(): int
    {
        $tz = (string) config('app.timezone', 'America/Lima');
        $seconds = (int) now($tz)->diffInSeconds(now($tz)->endOfDay());

        return max(60, $seconds + 1);
    }
}
