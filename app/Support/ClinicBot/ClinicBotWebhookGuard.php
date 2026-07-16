<?php

declare(strict_types=1);

namespace App\Support\ClinicBot;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Anti-eco y anti-spam para el webhook del asistente IA de clínicas (OpenWA).
 */
final class ClinicBotWebhookGuard
{
    public const ERROR_REPLY = 'Disculpa, tuve un problema al procesar tu mensaje. Un asistente de la clínica te ayudará pronto.';

    public const AUDIO_UNSUPPORTED_REPLY = 'Hola 👋 Por ahora respondo mejor por texto. ¿Puedes escribir tu consulta?';

    public function isOutgoingEvent(string $event): bool
    {
        $normalized = strtolower(trim($event));

        if ($normalized === '') {
            return false;
        }

        foreach (['message.sent', 'message.ack', 'message.delivered', 'message.read', 'onack', 'onsent'] as $outgoing) {
            if ($normalized === $outgoing || str_contains($normalized, $outgoing)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function isLikelyOutgoingMessage(array $data, bool $fromMe): bool
    {
        if ($fromMe) {
            return true;
        }

        foreach (['fromMe', 'from_me', 'isFromMe', 'self'] as $key) {
            if (filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    public function isBotGeneratedIncomingText(string $body): bool
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return false;
        }

        return $trimmed === self::ERROR_REPLY
            || $trimmed === self::AUDIO_UNSUPPORTED_REPLY;
    }

    public function shouldSkipOutboundEcho(string $sessionId, string $chatId, string $body): bool
    {
        $trimmed = trim($body);
        if ($trimmed === '' || $sessionId === '' || $chatId === '') {
            return false;
        }

        $key = $this->outboundKey($sessionId, $chatId);
        $last = Cache::get($key);

        return is_string($last) && hash_equals($last, $this->fingerprint($trimmed));
    }

    public function rememberOutbound(string $sessionId, string $chatId, string $body): void
    {
        $trimmed = trim($body);
        if ($trimmed === '' || $sessionId === '' || $chatId === '') {
            return;
        }

        $ttl = (int) config('bot-ia.outbound_echo_ttl_seconds', 180);
        Cache::put($this->outboundKey($sessionId, $chatId), $this->fingerprint($trimmed), $ttl);
    }

    public function isDuplicateInbound(
        string $sessionId,
        string $messageId,
        string $chatId,
        string $body,
    ): bool {
        if ($messageId !== '') {
            $idKey = 'clinicbot_msg_'.md5($messageId);
            if (Cache::has($idKey)) {
                return true;
            }
        }

        $trimmed = trim($body);
        if ($trimmed === '' || $sessionId === '' || $chatId === '') {
            return false;
        }

        $fingerprintKey = 'clinicbot_in_'.md5($sessionId.'|'.$chatId.'|'.$this->fingerprint($trimmed));
        if (Cache::has($fingerprintKey)) {
            return true;
        }

        return false;
    }

    public function rememberInbound(
        string $sessionId,
        string $messageId,
        string $chatId,
        string $body,
    ): void {
        $ttl = (int) config('bot-ia.dedupe_ttl_seconds', 120);

        if ($messageId !== '') {
            Cache::put('clinicbot_msg_'.md5($messageId), 1, $ttl);
        }

        $trimmed = trim($body);
        if ($trimmed !== '' && $sessionId !== '' && $chatId !== '') {
            Cache::put(
                'clinicbot_in_'.md5($sessionId.'|'.$chatId.'|'.$this->fingerprint($trimmed)),
                1,
                $ttl,
            );
        }
    }

    public function isRateLimited(string $sessionId, string $chatId): bool
    {
        if ($sessionId === '' || $chatId === '') {
            return false;
        }

        return Cache::has($this->rateLimitKey($sessionId, $chatId));
    }

    public function markReplied(string $sessionId, string $chatId): void
    {
        if ($sessionId === '' || $chatId === '') {
            return;
        }

        $seconds = (int) config('bot-ia.reply_cooldown_seconds', 15);
        Cache::put($this->rateLimitKey($sessionId, $chatId), 1, max(5, $seconds));
    }

    public function shouldNotifyUserOfFailure(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());

        if (str_contains($message, '429')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'throttler')) {
            return false;
        }

        return true;
    }

    private function outboundKey(string $sessionId, string $chatId): string
    {
        return 'clinicbot_out_'.md5($sessionId.'|'.$chatId);
    }

    private function rateLimitKey(string $sessionId, string $chatId): string
    {
        return 'clinicbot_rate_'.md5($sessionId.'|'.$chatId);
    }

    private function fingerprint(string $text): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }
}
