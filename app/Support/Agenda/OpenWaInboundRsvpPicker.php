<?php

declare(strict_types=1);

namespace App\Support\Agenda;

/**
 * Elige el SI/NO inbound más reciente en un historial OpenWA.
 */
final class OpenWaInboundRsvpPicker
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{body: string, phone: string, wa_chat_id: string, message_id: string}|null
     */
    public static function latest(array $messages, string $fallbackChatId): ?array
    {
        $best = null;
        $bestTs = -1;

        foreach ($messages as $message) {
            if (self::isFromMe($message)) {
                continue;
            }

            $body = self::text($message);
            if (AgendaRsvpIntent::parse($body) === null) {
                continue;
            }

            $ts = self::timestamp($message);
            if ($ts < $bestTs) {
                continue;
            }

            $from = self::chatId($message, $fallbackChatId);
            $bestTs = $ts;
            $best = [
                'body' => $body,
                'phone' => preg_replace('/\D/', '', $from) ?: $from,
                'wa_chat_id' => str_contains($from, '@') ? $from : $fallbackChatId,
                'message_id' => self::messageId($message),
            ];
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public static function text(array $message): string
    {
        foreach (['body', 'content', 'text', 'caption'] as $key) {
            $value = $message[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $nested = $message['message'] ?? $message['data'] ?? null;
        if (is_array($nested)) {
            $fromNested = self::text($nested);
            if ($fromNested !== '') {
                return $fromNested;
            }

            $conversation = $nested['conversation'] ?? null;
            if (is_string($conversation) && trim($conversation) !== '') {
                return trim($conversation);
            }

            $extMsg = $nested['extendedTextMessage'] ?? null;
            $extended = is_array($extMsg) ? ($extMsg['text'] ?? null) : null;
            if (is_string($extended) && trim($extended) !== '') {
                return trim($extended);
            }

            $ephemeral = $nested['ephemeralMessage']['message'] ?? null;
            if (is_array($ephemeral)) {
                return self::text(['message' => $ephemeral]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function chatId(array $message, string $fallback): string
    {
        $key = is_array($message['key'] ?? null) ? $message['key'] : [];
        foreach (['from', 'chatId', 'author', 'remoteJid'] as $field) {
            $value = $message[$field] ?? $key[$field] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $remote = $key['remoteJid'] ?? null;

        return is_string($remote) && $remote !== '' ? $remote : $fallback;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function messageId(array $message): string
    {
        $key = is_array($message['key'] ?? null) ? $message['key'] : [];

        return (string) ($message['id'] ?? $key['id'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function isFromMe(array $message): bool
    {
        foreach (['fromMe', 'from_me', 'isFromMe'] as $key) {
            if (filter_var($message[$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        $key = $message['key'] ?? null;
        if (is_array($key) && filter_var($key['fromMe'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $key = $message['key'] ?? null;
        if (is_array($key) && array_key_exists('fromMe', $key)) {
            return filter_var($key['fromMe'], FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function timestamp(array $message): int
    {
        $raw = $message['timestamp'] ?? $message['t'] ?? $message['messageTimestamp'] ?? 0;
        if (is_numeric($raw)) {
            $n = (int) $raw;

            return $n > 10_000_000_000 ? (int) floor($n / 1000) : $n;
        }

        return 0;
    }
}
