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
     * @param  (callable(string, int, ?string): void)|null  $trace
     * @return array{chats: int, applied: int, skipped: int}
     */
    public function poll(bool $dryRun = false, ?callable $trace = null): array
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

        foreach ($this->recentRsvpChatIds($sessionId) as $chatId) {
            $stats['chats']++;
            $applied = $this->pollChat($sessionId, $chatId, $dryRun, $trace);
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
    private function recentRsvpChatIds(string $sessionId): array
    {
        $ids = [];
        $wantTails = [];

        $this->activeTenants->each(function () use (&$ids, &$wantTails): void {
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
                $digits = preg_replace('/\D/', '', $chatId) ?? '';
                if (strlen($digits) >= 9) {
                    $wantTails[substr($digits, -9)] = true;
                }
            }
        });

        try {
            foreach ($this->client->listSessionChats($sessionId, 80) as $chat) {
                $id = (string) ($chat['id'] ?? $chat['chatId'] ?? $chat['jid'] ?? '');
                if ($id === '' || str_ends_with(strtolower($id), '@g.us')) {
                    continue;
                }
                $unread = (int) ($chat['unreadCount'] ?? $chat['unread'] ?? 0);
                $digits = preg_replace('/\D/', '', $id) ?? '';
                $tail = strlen($digits) >= 9 ? substr($digits, -9) : '';
                if ($unread > 0 || ($tail !== '' && isset($wantTails[$tail]))) {
                    $ids[$id] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Agenda RSVP poll: no se pudo listar chats OpenWA', [
                'error' => $e->getMessage(),
            ]);
        }

        return array_slice(array_keys($ids), 0, 50);
    }

    /**
     * @param  (callable(string, int, ?string): void)|null  $trace
     */
    private function pollChat(string $sessionId, string $chatId, bool $dryRun, ?callable $trace = null): bool
    {
        try {
            $messages = array_merge(
                $this->client->listChatMessages($sessionId, $chatId, 15),
                $this->client->listChatMessagesLive($sessionId, $chatId, 15),
            );
        } catch (\Throwable $e) {
            Log::warning('Agenda RSVP poll: no se pudo leer el chat', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $trace && $trace($chatId, 0, 'error: '.$e->getMessage());

            return false;
        }

        $sample = null;
        foreach (array_reverse($messages) as $message) {
            $text = OpenWaInboundRsvpPicker::text($message);
            if ($text !== '') {
                $sample = mb_substr($text, 0, 80);
                break;
            }
        }
        $trace && $trace($chatId, count($messages), $sample);

        $hit = OpenWaInboundRsvpPicker::latest($messages, $chatId);
        if ($hit === null) {
            return false;
        }

        if ($dryRun) {
            Log::warning('Agenda RSVP poll: dry-run', $hit);

            return true;
        }

        $fingerprint = $hit['message_id'] !== ''
            ? $hit['message_id']
            : md5($chatId.'|'.$hit['body']);
        $cacheKey = 'agenda-rsvp-poll:'.$fingerprint;
        if (! Cache::add($cacheKey, 1, now()->addDays(7))) {
            return false;
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
