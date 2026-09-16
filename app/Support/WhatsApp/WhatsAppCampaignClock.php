<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Horario de campañas: siempre Perú, aunque el server o el cron estén en UTC.
 */
final class WhatsAppCampaignClock
{
    public const TIMEZONE = 'America/Lima';

    public static function now(?CarbonInterface $now = null): Carbon
    {
        return Carbon::parse($now ?? Carbon::now())->timezone(self::TIMEZONE);
    }

    public static function hm(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->format('H:i');
        }

        $raw = trim((string) $value);
        if (preg_match('/(?:^|\s)(\d{1,2}:\d{2})(?::\d{2})?$/', $raw, $matches) === 1) {
            $hm = $matches[1];
            if (strlen($hm) === 4) {
                return '0'.$hm;
            }

            return $hm;
        }

        if (preg_match('/^(\d{2}:\d{2})/', $raw, $matches) === 1) {
            return $matches[1];
        }

        return '00:00';
    }

    public static function minutes(mixed $value): int
    {
        [$hour, $minute] = array_map('intval', explode(':', self::hm($value)));

        return ($hour * 60) + $minute;
    }
}
