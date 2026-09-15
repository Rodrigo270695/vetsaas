<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

/**
 * Decide si hay que pegarle start a OpenWA.
 *
 * Las sesiones ya vivas (ready / arrancando) no se tocan: un start extra
 * tumba Baileys o dispara 429. Solo se despierta lo caído, o `created` con
 * auth previa (teléfono) / acción explícita del usuario (QR).
 */
final class OpenWaReconnectPolicy
{
    /** @var list<string> */
    public const LIVE_STATUSES = ['ready', 'initializing', 'authenticating', 'qr_ready'];

    /** @var list<string> */
    public const DOWN_STATUSES = ['disconnected', 'failed'];

    public static function isLive(string $status): bool
    {
        return in_array($status, self::LIVE_STATUSES, true);
    }

    public static function shouldStartEngine(
        string $status,
        bool $autoReconnect,
        bool $wakeForLink,
        bool $hadPhone,
    ): bool {
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
    ): bool {
        if (! $autoReconnect || ! $hasSessionId) {
            return false;
        }

        return self::shouldStartEngine($status, true, false, $hadPhone);
    }
}
