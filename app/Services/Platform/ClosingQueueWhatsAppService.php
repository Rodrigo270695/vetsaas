<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use RuntimeException;

/**
 * Envía el guion de la cola de cierre por el WhatsApp de plataforma (OpenWA).
 */
final class ClosingQueueWhatsAppService
{
    public const MAX_BULK = 15;

    /** Pausa mínima entre mensajes masivos (anti-baneo). */
    public const DELAY_MIN_SECONDS = 45;

    /** Pausa máxima entre mensajes masivos. */
    public const DELAY_MAX_SECONDS = 75;

    public function __construct(
        private readonly ClosingQueueService $queue,
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly SalesBotService $bot,
    ) {}

    public function isReady(): bool
    {
        return $this->messenger->isReady();
    }

    /**
     * @return array{ok: true, name: string, phone: string}
     */
    public function sendById(string $rowId): array
    {
        $row = $this->queue->rowsByIds([$rowId])->first();
        if (! is_array($row)) {
            throw new RuntimeException('Esa fila ya no está en la cola.');
        }

        return $this->sendRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{ok: true, name: string, phone: string}
     */
    public function sendRow(array $row): array
    {
        if (! $this->messenger->isReady()) {
            throw new RuntimeException('WhatsApp de plataforma no está conectado.');
        }

        $script = trim((string) ($row['script'] ?? ''));
        if ($script === '') {
            throw new RuntimeException('No hay guion para enviar.');
        }

        $phone = $this->bot->normalizeLeadPhone((string) ($row['phone'] ?? ''));
        if ($phone === '' || strlen($phone) < 11) {
            throw new RuntimeException('Esa fila no tiene un celular válido.');
        }

        $chatId = $phone.'@c.us';
        $this->messenger->sendText($chatId, $script);
        $this->bot->rememberOutgoingBotMessage($phone, $script);

        $kind = (string) ($row['kind'] ?? '');
        $name = trim((string) ($row['name'] ?? '')) ?: $phone;
        $conversation = $this->bot->findExistingConversation($phone, $chatId);

        if ($conversation === null && $kind === 'lead') {
            $conversation = $this->bot->createConversation(
                phone: $phone,
                waChatId: $chatId,
                prospectName: $name,
                trigger: 'manual:cola-cierre',
            );
        }

        if ($conversation !== null) {
            $conversation->pushMessage('assistant', '[cola-cierre] '.$script);
            $conversation->save();
        }

        return [
            'ok' => true,
            'name' => $name,
            'phone' => $phone,
        ];
    }
}
