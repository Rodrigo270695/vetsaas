<?php

declare(strict_types=1);

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Ancho de papel térmico para tickets (pre-cuenta / venta interna).
 */
final class TicketAnchoMm
{
    /** @var list<string> */
    public const ALLOWED = ['56', '58', '80'];

    public const DEFAULT = '58';

    public static function normalize(?string $value, ?string $fallback = null): string
    {
        $candidate = $value !== null ? trim($value) : '';
        if (in_array($candidate, self::ALLOWED, true)) {
            return $candidate;
        }

        $fb = $fallback !== null ? trim($fallback) : '';
        if (in_array($fb, self::ALLOWED, true)) {
            return $fb;
        }

        return self::DEFAULT;
    }

    public static function fromRequest(Request $request, ?string $configValue): string
    {
        $override = trim((string) $request->string('ancho', ''));

        return self::normalize($override !== '' ? $override : null, $configValue);
    }

    public static function isNarrow(string $ancho): bool
    {
        return in_array($ancho, ['56', '58'], true);
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
                'fs' => 8,
                'fs_sm' => 7,
                'fs_title' => 10,
                'fs_total' => 10,
                'logo_max' => 11,
                'footer' => 7,
                'pad_x' => '2mm',
            ],
            '58' => [
                'fs' => 9,
                'fs_sm' => 8,
                'fs_title' => 11,
                'fs_total' => 11,
                'logo_max' => 12,
                'footer' => 7,
                'pad_x' => '2.5mm',
            ],
            default => [
                'fs' => 10,
                'fs_sm' => 9,
                'fs_title' => 12,
                'fs_total' => 12,
                'logo_max' => 14,
                'footer' => 8,
                'pad_x' => '2.5mm',
            ],
        };
    }
}
