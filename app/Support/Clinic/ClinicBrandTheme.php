<?php

declare(strict_types=1);

namespace App\Support\Clinic;

/**
 * Genera variables CSS de marca alineadas con {@see resources/js/lib/clinic-theme.ts}.
 */
final class ClinicBrandTheme
{
    /** @var list<string> */
    private const SCALE_KEYS = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

    /**
     * Bloque CSS para `:root` o null si no hay colores configurados.
     */
    public static function rootCssBlock(?string $colorPrimario, ?string $colorSecundario): ?string
    {
        $primary = self::normalizeHex($colorPrimario);
        $secondary = self::normalizeHex($colorSecundario);

        if ($primary === null && $secondary === null) {
            return null;
        }

        $resolvedPrimary = $primary ?? $secondary;
        $resolvedSecondary = $secondary ?? self::mixHex($resolvedPrimary, '#FFFFFF', 0.72);
        $scale = self::buildBrandScale($resolvedPrimary, $resolvedSecondary);

        $declarations = [];

        foreach (self::SCALE_KEYS as $key) {
            $declarations[] = "--brand-{$key}: {$scale[$key]}";
        }

        $declarations[] = '--primary-foreground: '.self::contrastingForeground($resolvedPrimary);

        return ':root { '.implode('; ', $declarations).'; }';
    }

    private static function normalizeHex(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $trimmed) !== 1) {
            return null;
        }

        return strtoupper($trimmed);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function hexToRgb(string $hex): ?array
    {
        $normalized = self::normalizeHex($hex);

        if ($normalized === null) {
            return null;
        }

        $value = substr($normalized, 1);

        return [
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        ];
    }

    private static function rgbToHex(float $r, float $g, float $b): string
    {
        $clamp = static fn (float $channel): int => max(0, min(255, (int) round($channel)));

        return sprintf(
            '#%02X%02X%02X',
            $clamp($r),
            $clamp($g),
            $clamp($b),
        );
    }

    private static function mixHex(string $base, string $tint, float $weight): string
    {
        $from = self::hexToRgb($base);
        $to = self::hexToRgb($tint);

        if ($from === null || $to === null) {
            return $base;
        }

        $ratio = max(0.0, min(1.0, $weight));

        return self::rgbToHex(
            $from[0] + ($to[0] - $from[0]) * $ratio,
            $from[1] + ($to[1] - $from[1]) * $ratio,
            $from[2] + ($to[2] - $from[2]) * $ratio,
        );
    }

    private static function shadeHex(string $hex, float $percent): string
    {
        $rgb = self::hexToRgb($hex);

        if ($rgb === null) {
            return $hex;
        }

        $factor = 1 + ($percent / 100);

        return self::rgbToHex($rgb[0] * $factor, $rgb[1] * $factor, $rgb[2] * $factor);
    }

    private static function contrastingForeground(string $hex): string
    {
        $rgb = self::hexToRgb($hex);

        if ($rgb === null) {
            return '#FFFFFF';
        }

        $luminance = (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;

        return $luminance > 0.58 ? '#0C0A09' : '#FFFFFF';
    }

    /**
     * @return array<string, string>
     */
    private static function buildBrandScale(string $primary, string $secondary): array
    {
        return [
            '50' => self::mixHex($secondary, '#FFFFFF', 0.55),
            '100' => self::mixHex($secondary, '#FFFFFF', 0.3),
            '200' => self::mixHex($secondary, '#FFFFFF', 0.12),
            '300' => self::mixHex($secondary, $primary, 0.35),
            '400' => self::mixHex($secondary, $primary, 0.62),
            '500' => self::mixHex('#FFFFFF', $primary, 0.82),
            '600' => $primary,
            '700' => self::shadeHex($primary, -14),
            '800' => self::shadeHex($primary, -28),
            '900' => self::shadeHex($primary, -42),
            '950' => self::shadeHex($primary, -55),
        ];
    }
}
