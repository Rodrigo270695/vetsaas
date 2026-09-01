<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\ClosingQueueWhatsAppSend;
use App\Models\SalesConversation;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    public function connectedPhone(): ?string
    {
        return $this->messenger->connectedPhone();
    }

    /**
     * @return array{ok: true, name: string, phone: string, from_phone: ?string, chat_id: string}
     */
    public function sendById(string $rowId, bool $force = false): array
    {
        $row = $this->queue->rowsByIds([$rowId])->first();
        if (! is_array($row)) {
            throw new RuntimeException('Esa fila ya no está en la cola.');
        }

        $lastSent = trim((string) ($row['last_sent_at'] ?? ''));
        if ($lastSent !== '' && ! $force) {
            throw new ClosingQueueAlreadySentException($lastSent);
        }

        return $this->sendRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{ok: true, name: string, phone: string, from_phone: ?string, chat_id: string}
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

        $conversation = $this->conversationFromRow($row);
        $chatId = $this->resolveChatId($row, $conversation);
        $fromPhone = $this->messenger->connectedPhone();

        $result = $this->messenger->sendTextStrict($chatId, $script);

        $phoneForCache = $this->phoneForEcho($row, $conversation);
        $this->bot->rememberOutgoingBotMessage($phoneForCache, $script);

        $name = trim((string) ($row['name'] ?? '')) ?: $phoneForCache;
        $kind = (string) ($row['kind'] ?? '');

        if ($conversation === null && $kind === 'lead') {
            $conversation = $this->bot->createConversation(
                phone: $phoneForCache,
                waChatId: $chatId,
                prospectName: $name,
                trigger: 'manual:cola-cierre',
            );
        }

        if ($conversation !== null) {
            $conversation->pushMessage('assistant', '[cola-cierre] '.$script);
            $conversation->save();
        }

        $this->rememberSend($row, $phoneForCache, $fromPhone);

        Log::info('Cola de cierre: WhatsApp confirmado por OpenWA', [
            'row_id' => $row['id'] ?? null,
            'chat_id' => $chatId,
            'from_phone' => $fromPhone,
            'openwa' => [
                'messageId' => $result['messageId'] ?? $result['id'] ?? null,
            ],
        ]);

        return [
            'ok' => true,
            'name' => $name,
            'phone' => $phoneForCache,
            'from_phone' => $fromPhone,
            'chat_id' => $chatId,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function conversationFromRow(array $row): ?SalesConversation
    {
        $id = (string) ($row['id'] ?? '');
        if (str_starts_with($id, 'lead:')) {
            return SalesConversation::query()->find(substr($id, 5));
        }

        $phone = $this->bot->normalizeLeadPhone((string) ($row['phone'] ?? ''));
        if ($phone === '') {
            return null;
        }

        return $this->bot->findExistingConversation($phone, $phone.'@c.us');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveChatId(array $row, ?SalesConversation $conversation): string
    {
        $stored = trim((string) ($conversation?->wa_chat_id ?? $row['wa_chat_id'] ?? ''));
        if ($stored !== '' && (str_contains($stored, '@c.us') || str_contains($stored, '@lid'))) {
            return $stored;
        }

        $phone = $this->bot->normalizeLeadPhone((string) ($row['phone'] ?? ''));
        if (str_starts_with((string) ($row['phone'] ?? ''), 'lid:')) {
            throw new RuntimeException('Este lead aún no tiene chat WhatsApp resuelto (@lid). Abrí la conversación del bot y respondé desde ahí.');
        }

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            throw new RuntimeException('Esa fila no tiene un celular válido.');
        }

        return $chatId;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function phoneForEcho(array $row, ?SalesConversation $conversation): string
    {
        $raw = (string) ($conversation?->phone ?? '');
        if ($raw !== '' && ! str_starts_with($raw, 'lid:')) {
            $fromConv = $this->bot->normalizeLeadPhone($raw);
            if ($fromConv !== '') {
                return $fromConv;
            }
        }

        return $this->bot->normalizeLeadPhone((string) ($row['phone'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rememberSend(array $row, string $phone, ?string $fromPhone): void
    {
        $rowKey = trim((string) ($row['id'] ?? ''));
        if ($rowKey === '' || ! Schema::hasTable('closing_queue_whatsapp_sends')) {
            return;
        }

        ClosingQueueWhatsAppSend::query()->updateOrCreate(
            ['row_key' => $rowKey],
            [
                'kind' => (string) ($row['kind'] ?? ''),
                'phone' => $phone !== '' ? $phone : null,
                'from_phone' => $fromPhone,
                'sent_at' => now(),
            ],
        );
    }
}
