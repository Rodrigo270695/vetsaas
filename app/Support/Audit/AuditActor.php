<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Actor alternativo para auditoría cuando no hay usuario autenticado (p. ej. bot IA).
 */
final class AuditActor
{
    public const BOT_IA_NOMBRE = 'Asistente IA';

    private static ?string $nombreOverride = null;

    private static ?string $emailOverride = null;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runAsBotIa(?string $whatsappPhone, callable $callback): mixed
    {
        $prevNombre = self::$nombreOverride;
        $prevEmail = self::$emailOverride;

        self::$nombreOverride = self::BOT_IA_NOMBRE;
        self::$emailOverride = self::buildBotContextLabel($whatsappPhone);

        try {
            return $callback();
        } finally {
            self::$nombreOverride = $prevNombre;
            self::$emailOverride = $prevEmail;
        }
    }

    public static function nombre(): ?string
    {
        return self::$nombreOverride;
    }

    public static function email(): ?string
    {
        return self::$emailOverride;
    }

    public static function isBotIa(?string $nombre): bool
    {
        return $nombre === self::BOT_IA_NOMBRE;
    }

    private static function buildBotContextLabel(?string $phone): string
    {
        if ($phone === null || $phone === '' || str_starts_with($phone, 'lid:')) {
            return 'WhatsApp · chatbot IA';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return 'WhatsApp · chatbot IA';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '51')) {
            return sprintf(
                'WhatsApp · +51 %s %s %s %s',
                substr($digits, 2, 1),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
                substr($digits, 9),
            );
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return 'WhatsApp · +51 '.$digits;
        }

        return 'WhatsApp · '.$digits;
    }
}
