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
                'fs' => 10,
                'fs_sm' => 9,
                'fs_title' => 12,
                'fs_total' => 12,
                'logo_max' => 11,
                'footer' => 8,
                'pad_x' => '1.5mm',
            ],
            '58' => [
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
                'pad_x' => '2.5mm',
            ],
        };
    }
}
