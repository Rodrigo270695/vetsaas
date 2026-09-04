<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\NotificationQueue;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use App\Support\Agenda\AgendaRsvpFromInbound;
use App\Support\Agenda\OpenWaInboundRsvpPicker;
use App\Support\Notifications\RecordatorioTemplateCatalog;
use App\Support\Tenancy\ActiveTenantIterator;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * OpenWA a veces entrega el recordatorio y no dispara message.received
 * cuando el dueño responde SI. Leemos el historial del chat y aplicamos RSVP.
 */
final class AgendaRsvpInboxPoller
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sessions,
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly AgendaRsvpFromInbound $rsvp,
        private readonly ActiveTenantIterator $activeTenants,
    ) {}

    /**
     * @return array{chats: int, applied: int, skipped: int}
     */
    public function poll(bool $dryRun = false): array
    {
        $stats = ['chats' => 0, 'applied' => 0, 'skipped' => 0];

        if (! $this->client->isConfigured()) {
            return $stats;
        }

        $session = $this->sessions->ensure();
        $sessionId = trim((string) ($session?->openwa_session_id ?? ''));
        if ($sessionId === '' || $session?->isReady() !== true) {
            Log::warning('Agenda RSVP poll: sesión de plataforma no ready');

            return $stats;
        }

        foreach ($this->recentRsvpChatIds() as $chatId) {
            $stats['chats']++;
            $applied = $this->pollChat($sessionId, $chatId, $dryRun);
            if ($applied) {
                $stats['applied']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * @return list<string>
     */
    private function recentRsvpChatIds(): array
    {
        $ids = [];

        $this->activeTenants->each(function () use (&$ids): void {
            $rows = NotificationQueue::query()
                ->where('canal', NotificationQueue::CANAL_WHATSAPP)
                ->where('estado', NotificationQueue::ESTADO_ENVIADO)
                ->whereIn('tipo', RecordatorioTemplateCatalog::RSVP_TIPOS)
                ->where('enviar_at', '>=', now()->subDays(3))
                ->orderByDesc('enviar_at')
                ->limit(30)
                ->pluck('destinatario');

            foreach ($rows as $destinatario) {
                $raw = trim((string) $destinatario);
                if ($raw === '') {
                    continue;
                }
                $chatId = str_contains($raw, '@')
                    ? $raw
                    : (WhatsAppChatId::fromPhone($raw) ?? $raw);
                $ids[$chatId] = true;
            }
        });

        return array_slice(array_keys($ids), 0, 40);
    }

    private function pollChat(string $sessionId, string $chatId, bool $dryRun): bool
    {
        try {
            $messages = $this->client->listChatMessagesLive($sessionId, $chatId, 15);
        } catch (\Throwable $e) {
            Log::warning('Agenda RSVP poll: no se pudo leer el chat', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $hit = OpenWaInboundRsvpPicker::latest($messages, $chatId);
        if ($hit === null) {
            return false;
        }

        $fingerprint = $hit['message_id'] !== ''
            ? $hit['message_id']
            : md5($chatId.'|'.$hit['body']);
        $cacheKey = 'agenda-rsvp-poll:'.$fingerprint;
        if (! Cache::add($cacheKey, 1, now()->addDays(7))) {
            return false;
        }

        if ($dryRun) {
            Log::warning('Agenda RSVP poll: dry-run', $hit);

            return true;
        }

        $result = $this->rsvp->tryHandle($sessionId, $hit['phone'], $hit['wa_chat_id'], $hit['body']);
        if ($result === null) {
            Cache::put($cacheKey, 1, now()->addMinutes(15));
            Log::warning('Agenda RSVP poll: SI/NO leído pero no aplicó', $hit);

            return false;
        }

        Log::warning('Agenda RSVP poll: confirmó/canceló', [
            'kind' => $result['kind'],
            'intent' => $result['intent'],
            'id' => $result['id'],
            'chat_id' => $chatId,
        ]);

        if ($this->messenger->isReady()) {
            $this->messenger->sendText($hit['wa_chat_id'], $result['reply']);
        }

        return true;
    }
}
