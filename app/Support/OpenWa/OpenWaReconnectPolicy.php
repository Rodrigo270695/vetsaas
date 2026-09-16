<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

use Carbon\CarbonInterface;

/**
 * Decide si hay que pegarle start a OpenWA.
 *
 * Las sesiones ya vivas (ready / arrancando) no se tocan: un start extra
 * tumba Baileys o dispara 429. Solo se despierta lo caído, o `created` con
 * auth previa (teléfono) / acción explícita del usuario (QR).
 * `initializing`/`authenticating` viejos (motor colgado) se tratan como caídos.
 */
final class OpenWaReconnectPolicy
{
    /** @var list<string> */
    public const LIVE_STATUSES = ['ready', 'initializing', 'authenticating', 'qr_ready'];

    /** @var list<string> */
    public const DOWN_STATUSES = ['disconnected', 'failed'];

    public const STALE_BOOT_MINUTES = 8;

    public static function normalizeStatus(string $status, ?CarbonInterface $touchedAt = null): string
    {
        if (
            in_array($status, ['initializing', 'authenticating'], true)
            && $touchedAt instanceof CarbonInterface
            && $touchedAt->lte(now()->subMinutes(self::STALE_BOOT_MINUTES))
        ) {
            return 'disconnected';
        }

        return $status;
    }

    public static function isLive(string $status, ?CarbonInterface $touchedAt = null): bool
    {
        return in_array(self::normalizeStatus($status, $touchedAt), self::LIVE_STATUSES, true);
    }

    public static function shouldStartEngine(
        string $status,
        bool $autoReconnect,
        bool $wakeForLink,
        bool $hadPhone,
        ?CarbonInterface $touchedAt = null,
    ): bool {
        $status = self::normalizeStatus($status, $touchedAt);

        if (self::isLive($status)) {
            return false;
        }

        if (! $autoReconnect && ! $wakeForLink) {
            return false;
        }

        if (in_array($status, self::DOWN_STATUSES, true)) {
            return true;
        }

        if ($status === 'created') {
            return $wakeForLink || ($autoReconnect && $hadPhone);
        }

        return false;
    }

    public static function shouldEnqueueAutoReconnect(
        string $status,
        bool $autoReconnect,
        bool $hadPhone,
        bool $hasSessionId,
        ?CarbonInterface $touchedAt = null,
    ): bool {
        if (! $autoReconnect || ! $hasSessionId) {
            return false;
        }

        return self::shouldStartEngine($status, true, false, $hadPhone, $touchedAt);
    }
}
