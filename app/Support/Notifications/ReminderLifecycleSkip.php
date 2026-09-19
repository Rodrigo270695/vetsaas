<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Services\Notifications\AppointmentReminderScanner;
use Carbon\CarbonInterface;

/**
 * Evita duplicar un recordatorio por días si el WhatsApp de alta/cambio
 * ya cubrió esa misma ventana. Los avisos de 2 h no se saltan: son otro mensaje.
 */
final class ReminderLifecycleSkip
{
    public static function shouldSkip(
        CarbonInterface $eventAt,
        string $tipo,
        ?CarbonInterface $lifecycleNoticeAt,
    ): bool {
        if ($lifecycleNoticeAt === null) {
            return false;
        }

        $days = self::daysLead($tipo);
        if ($days === null) {
            return false;
        }

        return AppointmentReminderScanner::inWindow(
            $eventAt,
            $lifecycleNoticeAt->copy()->addDays($days),
        );
    }

    public static function daysLead(string $tipo): ?int
    {
        if (str_ends_with($tipo, '_2h')) {
            return null;
        }

        if (str_ends_with($tipo, '_48h')) {
            return 2;
        }

        if (preg_match('/_(\d+)d$/', $tipo, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
