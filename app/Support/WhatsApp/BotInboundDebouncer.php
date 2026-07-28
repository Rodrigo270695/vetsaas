<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agrupa mensajes WhatsApp rápidos del mismo chat y solo deja procesar
 * el lote cuando el cliente “terminó de escribir” (debounce por generación).
 *
 * Varios webhooks concurrentes empujan al buffer; solo el job/callback con
 * la generación vigente hace claim y responde una sola vez.
 */
final class BotInboundDebouncer
{
    public const SCOPE_SALES = 'sales';

    public const SCOPE_CLINIC = 'clinic';

    public function __construct(
        private readonly string $scope,
    ) {}

    public static function sales(): self
    {
        return new self(self::SCOPE_SALES);
    }

    public static function clinic(): self
    {
        return new self(self::SCOPE_CLINIC);
    }

    /**
     * Empuja un mensaje al buffer y renueva el token de generación.
     *
     * @return array{generation: string, delay_seconds: int, count: int}
     */
    public function push(string $channelKey, string $text, ?string $messageId = null): array
    {
        $trimmed = trim($text);
        $delay = $this->delaySeconds();
        $ttl = max(120, $delay * 20);
        $generation = (string) Str::uuid();

        $count = (int) $this->withBufferLock($channelKey, function () use (
            $channelKey,
            $trimmed,
            $messageId,
            $generation,
            $ttl,
        ): int {
            $bufferKey = $this->bufferKey($channelKey);
            $messages = Cache::get($bufferKey, []);
            if (! is_array($messages)) {
                $messages = [];
            }

            if ($trimmed !== '') {
                $messages[] = [
                    'text' => $trimmed,
                    'message_id' => $messageId,
                    'at' => time(),
                ];
            }

            $max = $this->maxMessages();
            if (count($messages) > $max) {
                $messages = array_slice($messages, -$max);
            }

            Cache::put($bufferKey, $messages, $ttl);
            Cache::put($this->generationKey($channelKey), $generation, $ttl);

            return count($messages);
        });

        return [
            'generation' => $generation,
            'delay_seconds' => $delay,
            'count' => $count,
        ];
    }

    /**
     * Si la generación sigue vigente, vacía el buffer y devuelve el texto unido.
     * Si llegó otro mensaje después, retorna null (este lote quedó obsoleto).
     */
    public function claim(string $channelKey, string $expectedGeneration): ?string
    {
        return $this->withBufferLock($channelKey, function () use ($channelKey, $expectedGeneration): ?string {
            $current = Cache::get($this->generationKey($channelKey));
            if (! is_string($current) || ! hash_equals($current, $expectedGeneration)) {
                return null;
            }

            $messages = Cache::pull($this->bufferKey($channelKey), []);
            Cache::forget($this->generationKey($channelKey));

            if (! is_array($messages) || $messages === []) {
                return null;
            }

            $parts = [];
            foreach ($messages as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $text = trim((string) ($row['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            if ($parts === []) {
                return null;
            }

            return implode("\n", $parts);
        });
    }

    /**
     * Lock exclusivo mientras se genera/envía la respuesta del chat.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null  null si no se pudo adquirir el lock a tiempo
     */
    public function withReplyLock(string $channelKey, callable $callback): mixed
    {
        $lock = Cache::lock($this->replyLockKey($channelKey), 90);

        try {
            return $lock->block(5, $callback);
        } catch (Throwable) {
            return null;
        }
    }

    public function delaySeconds(): int
    {
        $key = $this->scope === self::SCOPE_CLINIC
            ? 'bot-ia.message_debounce_seconds'
            : 'salesbot.message_debounce_seconds';

        return max(1, (int) config($key, 4));
    }

    private function maxMessages(): int
    {
        $key = $this->scope === self::SCOPE_CLINIC
            ? 'bot-ia.message_debounce_max_messages'
            : 'salesbot.message_debounce_max_messages';

        return max(2, (int) config($key, 12));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withBufferLock(string $channelKey, callable $callback): mixed
    {
        $lock = Cache::lock($this->bufferLockKey($channelKey), 10);
        $lock->block(5);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function bufferKey(string $channelKey): string
    {
        return 'bot_inbound_buf_'.$this->scope.'_'.md5($channelKey);
    }

    private function generationKey(string $channelKey): string
    {
        return 'bot_inbound_gen_'.$this->scope.'_'.md5($channelKey);
    }

    private function bufferLockKey(string $channelKey): string
    {
        return 'bot_inbound_buflock_'.$this->scope.'_'.md5($channelKey);
    }

    private function replyLockKey(string $channelKey): string
    {
        return 'bot_inbound_replylock_'.$this->scope.'_'.md5($channelKey);
    }
}
