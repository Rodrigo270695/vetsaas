<?php

declare(strict_types=1);

namespace App\Support\ClinicBot;

use Illuminate\Support\Carbon;

final class ClinicBotPeruClock
{
    public const TIMEZONE = 'America/Lima';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function promptReference(): string
    {
        $now = self::now();
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $dia = $dias[$now->dayOfWeek] ?? $now->format('l');

        return sprintf(
            '%s, %s %s de %s de %s, %s (hora Perú, America/Lima)',
            ucfirst($dia),
            $now->format('d'),
            self::mesEnEspanol((int) $now->month),
            $now->format('Y'),
            $now->format('H:i'),
            $now->format('Y-m-d'),
        );
    }

    private static function mesEnEspanol(int $month): string
    {
        return match ($month) {
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
            default => '',
        };
    }
}
