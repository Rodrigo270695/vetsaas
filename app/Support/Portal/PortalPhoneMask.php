<?php

declare(strict_types=1);

namespace App\Support\Portal;

final class PortalPhoneMask
{
    public static function mask(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 4) {
            return 'tu celular';
        }

        return '***'.substr($digits, -3);
    }
}
