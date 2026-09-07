<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Recordatorios por fecha (vacuna, cumpleaños) no deben salir de madrugada.
 */
final class ReminderSendWindow
{
    public const START_HOUR = 9;

    public static function enqueueAt(?CarbonInterface $now = null): CarbonInterface
    {
        $tz = (string) config('app.timezone', 'America/Lima');
        $now = Carbon::parse($now ?? now())->timezone($tz);
        $opens = $now->copy()->setTime(self::START_HOUR, 0, 0);

        return $now->lt($opens) ? $opens : $now;
    }
}
