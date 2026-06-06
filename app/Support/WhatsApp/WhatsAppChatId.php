<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

/**
 * Normaliza teléfonos peruanos/latam al formato chatId de WhatsApp (OpenWA).
 */
final class WhatsAppChatId
{
    public static function fromPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '51'.$digits;
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits.'@c.us';
    }
}
