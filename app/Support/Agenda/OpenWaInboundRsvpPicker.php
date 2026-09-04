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

            $body = trim((string) ($message['body'] ?? $message['content'] ?? $message['text'] ?? ''));
            if (AgendaRsvpIntent::parse($body) === null) {
                continue;
            }

            $ts = self::timestamp($message);
            if ($ts < $bestTs) {
                continue;
            }

            $from = (string) ($message['from'] ?? $message['chatId'] ?? $message['author'] ?? $fallbackChatId);
            $bestTs = $ts;
            $best = [
                'body' => $body,
                'phone' => preg_replace('/\D/', '', $from) ?: $from,
                'wa_chat_id' => str_contains($from, '@') ? $from : $fallbackChatId,
                'message_id' => (string) ($message['id'] ?? $message['key']['id'] ?? ''),
            ];
        }

        return $best;
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
