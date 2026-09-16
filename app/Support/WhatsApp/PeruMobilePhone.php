<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

/**
 * Celular Perú para WhatsApp: 9 dígitos empezando en 9, con o sin 51.
 */
final class PeruMobilePhone
{
    public static function digits(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalized($raw) !== null;
    }

    /**
     * 519XXXXXXXX o null.
     */
    public static function normalized(?string $raw): ?string
    {
        $digits = self::digits($raw);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '519')) {
            return $digits;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return null;
    }

    public static function pickFromOwner(?string $telefono, ?string $telefonoAlt): ?string
    {
        return self::normalized($telefono) ?? self::normalized($telefonoAlt);
    }

    public static function sqlCelularExpr(string $column): string
    {
        $safe = preg_replace('/[^a-z_]/', '', $column) ?? '';
        if ($safe === '') {
            return 'false';
        }

        return "regexp_replace(coalesce({$safe}, ''), '[^0-9]', '', 'g') ~ '^(51)?9[0-9]{8}$'";
    }
}
