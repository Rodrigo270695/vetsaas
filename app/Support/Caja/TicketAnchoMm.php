<?php

declare(strict_types=1);

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Ancho de papel térmico para tickets (pre-cuenta / venta interna).
 */
final class TicketAnchoMm
{
    /** Rollos que se compran. 72 mm es el cabezal de un rollo de 80, no una opción. */
    public const ALLOWED = ['56', '57', '58', '80'];

    public const DEFAULT = '58';

    public static function normalize(?string $value, ?string $fallback = null): string
    {
        $candidate = self::canonicalize($value);
        if ($candidate !== null) {
            return $candidate;
        }

        $fb = self::canonicalize($fallback);
        if ($fb !== null) {
            return $fb;
        }

        return self::DEFAULT;
    }

    /**
     * 72 mm guardado antes era el cabezal del rollo de 80. Se lee como 80.
     */
    private static function canonicalize(?string $value): ?string
    {
        $candidate = $value !== null ? trim($value) : '';
        if ($candidate === '72') {
            return '80';
        }

        if (in_array($candidate, self::ALLOWED, true)) {
            return $candidate;
        }

        return null;
    }

    public static function fromRequest(Request $request, ?string $configValue): string
    {
        $override = trim((string) $request->string('ancho', ''));

        return self::normalize($override !== '' ? $override : null, $configValue);
    }

    public static function isNarrow(string $ancho): bool
    {
        return in_array(self::normalize($ancho), ['56', '57', '58'], true);
    }

    /**
     * Ancho útil del cabezal. El rollo de 80 mm imprime unos 72 mm (576 puntos a 203 dpi);
     * 56, 57 y 58 mm imprimen unos 48 mm. Maquetar al ancho del rollo recorta ambos bordes.
     */
    public static function printableMm(string $ancho): string
    {
        return match (self::normalize($ancho)) {
            '56', '57', '58' => '48',
            default => '72',
        };
    }

    /**
     * Tamaños tipográficos según ancho de rollo.
     *
     * @return array{fs: int, fs_sm: int, fs_title: int, fs_total: int, logo_max: int, footer: int, pad_x: string}
     */
    public static function typography(string $ancho): array
    {
        return match (self::normalize($ancho, null)) {
            '56' => [
                'fs' => 10,
                'fs_sm' => 9,
                'fs_title' => 12,
                'fs_total' => 12,
                'logo_max' => 11,
                'footer' => 8,
                'pad_x' => '1.5mm',
            ],
            '57', '58' => [
                'fs' => 10,
                'fs_sm' => 9,
                'fs_title' => 12,
                'fs_total' => 12,
                'logo_max' => 12,
                'footer' => 8,
                'pad_x' => '2mm',
            ],
            default => [
                'fs' => 12,
                'fs_sm' => 11,
                'fs_title' => 14,
                'fs_total' => 14,
                'logo_max' => 14,
                'footer' => 9,
                'pad_x' => '1.5mm',
            ],
        };
    }
}
