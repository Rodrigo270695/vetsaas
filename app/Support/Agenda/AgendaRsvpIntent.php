<?php

declare(strict_types=1);

namespace App\Support\Agenda;

/**
 * Interpreta SI/NO cortos de WhatsApp. Mensajes largos o ambiguos se ignoran
 * para no pelear con el asistente IA.
 */
final class AgendaRsvpIntent
{
    public const YES = 'yes';

    public const NO = 'no';

    public static function parse(?string $body): ?string
    {
        $raw = trim((string) $body);
        if ($raw === '') {
            return null;
        }

        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return null;
        }

        $words = preg_split('/\s+/', $normalized) ?: [];
        if (count($words) > 4) {
            return null;
        }

        if (self::isYes($normalized)) {
            return self::YES;
        }

        if (self::isNo($normalized)) {
            return self::NO;
        }

        return null;
    }

    private static function normalize(string $body): string
    {
        $body = mb_strtolower($body, 'UTF-8');
        $body = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $body);
        $body = preg_replace('/[*_~`.,;:!?¡¿()"\'«»]/u', ' ', $body) ?? $body;
        $body = preg_replace('/\s+/', ' ', $body) ?? $body;

        return trim($body);
    }

    private static function isYes(string $normalized): bool
    {
        return (bool) preg_match(
            '/^(si|sip|ok|okay|dale|va|yes|confirmo|confirmar|si confirmo|si voy|si asistire)$/',
            $normalized,
        );
    }

    private static function isNo(string $normalized): bool
    {
        return (bool) preg_match(
            '/^(no|nop|nope|cancelo|cancelar|no puedo|no puedo ir|no voy|no asistire|no confirmo)$/',
            $normalized,
        );
    }
}
