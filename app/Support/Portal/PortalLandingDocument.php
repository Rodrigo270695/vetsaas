<?php

declare(strict_types=1);

namespace App\Support\Portal;

final class PortalLandingDocument
{
    public static function normalize(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    public static function isPlausible(string $normalized): bool
    {
        $len = strlen($normalized);

        return $len >= 6 && $len <= 15;
    }
}