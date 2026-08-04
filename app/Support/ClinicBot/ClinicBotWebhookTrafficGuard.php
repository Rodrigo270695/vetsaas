<?php

declare(strict_types=1);

namespace App\Support\ClinicBot;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Contadores + circuit breaker del webhook clinic-bot.
 *
 * Evita que un flood de OpenWA (presence/typing/ack/reintentos) sature PHP-FPM.
 */
final class ClinicBotWebhookTrafficGuard
{
    private const KEY_CIRCUIT = 'clinicbot:circuit_open';

    private const KEY_CIRCUIT_AT = 'clinicbot:circuit_opened_at';

    public function isCircuitOpen(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return (bool) Cache::get(self::KEY_CIRCUIT, false);
    }

    /**
     * Registra un hit y, si supera el umbral, abre el circuito.
     *
     * @return bool true si la request debe rechazarse de inmediato (sin DB).
     */
    public function shouldRejectAfterHit(): bool
    {
        if (! $this->enabled()) {
            $this->increment('hits');

            return false;
        }

        $this->increment('hits');

        if ($this->isCircuitOpen()) {
            return true;
        }

        $limit = max(1, (int) config('bot-ia.webhook_rate_limit_per_minute', 120));
        $hits = $this->hitsInCurrentMinute();

        if ($hits > $limit) {
            $this->openCircuit($hits, $limit);

            return true;
        }

        return false;
    }

    public function recordSkipped(): void
    {
        $this->increment('skipped');
    }

    public function recordProcessed(): void
    {
        $this->increment('processed');
    }

    /**
     * @return array{
     *     circuit_open: bool,
     *     circuit_opened_at: string|null,
     *     hits_1m: int,
     *     hits_5m: int,
     *     skipped_1m: int,
     *     processed_1m: int,
     *     rate_limit_per_minute: int,
     *     high_traffic: bool
     * }
     */
    public function snapshot(): array
    {
        $limit = max(1, (int) config('bot-ia.webhook_rate_limit_per_minute', 120));
        $hits1 = $this->sumBucket('hits', 1);
        $openedAt = Cache::get(self::KEY_CIRCUIT_AT);
        $circuitOpen = $this->isCircuitOpen();

        return [
            'circuit_open' => $circuitOpen,
            'circuit_opened_at' => is_string($openedAt) ? $openedAt : null,
            'hits_1m' => $hits1,
            'hits_5m' => $this->sumBucket('hits', 5),
            'skipped_1m' => $this->sumBucket('skipped', 1),
            'processed_1m' => $this->sumBucket('processed', 1),
            'rate_limit_per_minute' => $limit,
            'high_traffic' => $circuitOpen || $hits1 >= (int) max(1, floor($limit * 0.7)),
        ];
    }

    private function enabled(): bool
    {
        return (bool) config('bot-ia.webhook_circuit_enabled', true);
    }

    private function openCircuit(int $hits, int $limit): void
    {
        $ttl = max(60, (int) config('bot-ia.webhook_circuit_ttl_seconds', 300));
        $openedAt = Carbon::now()->toIso8601String();

        Cache::put(self::KEY_CIRCUIT, true, $ttl);
        Cache::put(self::KEY_CIRCUIT_AT, $openedAt, $ttl);

        Log::warning('ClinicBot webhook circuit abierto: tráfico excesivo', [
            'hits_1m' => $hits,
            'limit' => $limit,
            'ttl_seconds' => $ttl,
        ]);
    }

    private function hitsInCurrentMinute(): int
    {
        return $this->sumBucket('hits', 1);
    }

    private function increment(string $kind): void
    {
        $key = $this->bucketKey($kind, Carbon::now());
        $ttl = 600;

        // Crear bucket con TTL; increment no renueva expire en todos los drivers.
        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    private function sumBucket(string $kind, int $minutes): int
    {
        $total = 0;
        $now = Carbon::now();

        for ($i = 0; $i < $minutes; $i++) {
            $total += (int) Cache::get($this->bucketKey($kind, $now->copy()->subMinutes($i)), 0);
        }

        return $total;
    }

    private function bucketKey(string $kind, Carbon $at): string
    {
        return 'clinicbot:'.$kind.':'.$at->format('YmdHi');
    }
}
