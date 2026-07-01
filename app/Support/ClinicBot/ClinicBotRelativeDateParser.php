<?php

declare(strict_types=1);

namespace App\Support\ClinicBot;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ClinicBotRelativeDateParser
{
    /**
     * @return array{ok: true, datetime: Carbon, iso: string}|array{ok: false, error: string}
     */
    public function parseDateTime(string $fecha, string $hora, ?Carbon $reference = null): array
    {
        $reference ??= ClinicBotPeruClock::now();

        try {
            $date = $this->parseDate($fecha, $reference);
            $time = $this->parseTime($hora);
            $datetime = $date->copy()->setTime($time['hour'], $time['minute'], 0);

            if ($datetime->lessThanOrEqualTo($reference)) {
                return [
                    'ok' => false,
                    'error' => 'La fecha y hora deben ser posteriores al momento actual en Perú.',
                ];
            }

            return [
                'ok' => true,
                'datetime' => $datetime,
                'iso' => $datetime->toIso8601String(),
            ];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function parseDate(string $input, ?Carbon $reference = null): Carbon
    {
        $reference ??= ClinicBotPeruClock::now();
        $normalized = $this->normalize($input);

        if ($normalized === '') {
            throw new InvalidArgumentException('La fecha está vacía.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
            return Carbon::parse($normalized, ClinicBotPeruClock::TIMEZONE)->startOfDay();
        }

        if (in_array($normalized, ['hoy', 'today'], true)) {
            return $reference->copy()->startOfDay();
        }

        if (preg_match('/pasado\s+manana/', $normalized) === 1) {
            return $reference->copy()->addDays(2)->startOfDay();
        }

        if (in_array($normalized, ['manana', 'tomorrow'], true)) {
            return $reference->copy()->addDay()->startOfDay();
        }

        if (in_array($normalized, ['anteayer'], true)) {
            throw new InvalidArgumentException('No se pueden agendar citas en el pasado.');
        }

        $dayDate = $this->parseWeekdayName($normalized, $reference);
        if ($dayDate !== null) {
            return $dayDate;
        }

        throw new InvalidArgumentException(
            'Fecha no reconocida. Usa YYYY-MM-DD o expresiones como hoy, mañana o pasado mañana.',
        );
    }

    /**
     * @return array{ok: true, date: string, label: string}|array{ok: false, error: string}
     */
    public function resolveExpression(string $expression, ?Carbon $reference = null): array
    {
        $reference ??= ClinicBotPeruClock::now();

        try {
            $date = $this->parseDate($expression, $reference);

            return [
                'ok' => true,
                'date' => $date->toDateString(),
                'label' => $date->format('d/m/Y'),
            ];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalize(string $input): string
    {
        $value = mb_strtolower(trim($input));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array{hour: int, minute: int}
     */
    private function parseTime(string $hora): array
    {
        $hora = trim($hora);

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $matches) !== 1) {
            throw new InvalidArgumentException('Hora inválida. Usa formato 24 h, por ejemplo 15:30.');
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('Hora inválida. Usa formato 24 h, por ejemplo 15:30.');
        }

        return ['hour' => $hour, 'minute' => $minute];
    }

    private function parseWeekdayName(string $normalized, Carbon $reference): ?Carbon
    {
        $map = [
            'domingo' => Carbon::SUNDAY,
            'lunes' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'miercoles' => Carbon::WEDNESDAY,
            'jueves' => Carbon::THURSDAY,
            'viernes' => Carbon::FRIDAY,
            'sabado' => Carbon::SATURDAY,
        ];

        foreach ($map as $name => $dayOfWeek) {
            if (! str_contains($normalized, $name)) {
                continue;
            }

            $diff = ($dayOfWeek - $reference->dayOfWeek + 7) % 7;

            return $reference->copy()->addDays($diff)->startOfDay();
        }

        return null;
    }
}
