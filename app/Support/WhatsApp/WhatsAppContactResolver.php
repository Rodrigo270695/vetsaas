<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve teléfono y nombre real desde el payload del webhook de OpenWA.
 *
 * WhatsApp ahora envía algunos chats como @lid (Linked ID) en vez del número.
 * El ID LID NO es un teléfono — hay que resolverlo vía API de contactos.
 */
final class WhatsAppContactResolver
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sessionSync,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Payload `data` del webhook.
     * @return array{wa_chat_id: string, phone: string, prospect_name: ?string}
     */
    public function resolve(array $data, ?string $openWaSessionId = null): array
    {
        $from   = (string) ($data['from'] ?? '');
        $chatId = (string) ($data['chatId'] ?? $data['chat_id'] ?? '');

        // Para responder siempre usamos el ID que WhatsApp espera (puede ser @lid).
        $waChatId = $from !== '' ? $from : $chatId;

        $prospectName = $this->extractNameFromPayload($data);
        $phone        = $this->digitsFromChatId($waChatId);

        // Si el chatId alternativo trae @c.us y from trae @lid, preferir el número real.
        if (str_ends_with($waChatId, '@lid') && str_ends_with($chatId, '@c.us')) {
            $phone = $this->digitsFromChatId($chatId);
        }

        // LID sin número real → consultar API de OpenWA.
        if ($this->isLinkedId($waChatId) && ($phone === '' || $this->looksLikeLidDigits($phone))) {
            $apiContact = $this->fetchContactFromApi($openWaSessionId, $waChatId);

            if ($apiContact !== null) {
                $resolvedId = (string) ($apiContact['id'] ?? $apiContact['jid'] ?? '');
                if ($resolvedId !== '' && str_ends_with($resolvedId, '@c.us')) {
                    $phone = $this->digitsFromChatId($resolvedId);
                }

                $apiName = $this->extractNameFromContact($apiContact);
                if ($apiName !== null) {
                    $prospectName = $apiName;
                }
            }

            // Si no se pudo resolver, guardamos con prefijo lid: para distinguirlo.
            if ($phone === '' || $this->looksLikeLidDigits($phone)) {
                $lidDigits = $this->digitsFromChatId($waChatId);
                $phone     = $lidDigits !== '' ? 'lid:' . $lidDigits : 'lid:desconocido';
            }
        }

        if ($phone === '') {
            $phone = $this->digitsFromChatId($waChatId);
        }

        return [
            'wa_chat_id'     => $waChatId,
            'phone'          => $phone,
            'prospect_name'  => $prospectName,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractNameFromPayload(array $data): ?string
    {
        $candidates = [];

        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $sender  = is_array($data['sender'] ?? null) ? $data['sender'] : [];

        foreach ([
            $contact['name'] ?? null,
            $contact['pushName'] ?? null,
            $contact['pushname'] ?? null,
            $sender['name'] ?? null,
            $sender['pushname'] ?? null,
            $sender['pushName'] ?? null,
            $data['pushName'] ?? null,
            $data['notifyName'] ?? null,
            $data['name'] ?? null,
        ] as $name) {
            if (is_string($name) && trim($name) !== '') {
                $candidates[] = trim($name);
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function extractNameFromContact(array $contact): ?string
    {
        foreach (['name', 'pushName', 'pushname', 'shortName', 'verifiedName'] as $key) {
            $val = $contact[$key] ?? null;
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchContactFromApi(?string $sessionId, string $waChatId): ?array
    {
        $resolvedSessionId = $this->resolveOpenWaSessionId($sessionId);

        if ($resolvedSessionId === null || ! $this->client->isConfigured()) {
            return null;
        }

        try {
            return $this->client->getContact($resolvedSessionId, $waChatId);
        } catch (\Throwable $e) {
            Log::debug('OpenWA getContact failed', [
                'chatId' => $waChatId,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

  private function resolveOpenWaSessionId(?string $fromPayload): ?string
    {
        if ($fromPayload !== null && $fromPayload !== '' && str_contains($fromPayload, '-')) {
            return $fromPayload;
        }

        $session = $this->sessionSync->ensure();

        $id = trim((string) ($session?->openwa_session_id ?? ''));

        return $id !== '' ? $id : null;
    }

    private function isLinkedId(string $chatId): bool
    {
        return str_ends_with($chatId, '@lid');
    }

  /**
     * Los LID suelen ser 14-18 dígitos y no coinciden con formato peruano (51 + 9 dígitos).
     */
    public function looksLikeLidDigits(string $digits): bool
    {
        if (! preg_match('/^\d+$/', $digits)) {
            return false;
        }

        $len = strlen($digits);

        // Perú móvil: 519XXXXXXXX (11 dígitos).
        if ($len === 11 && str_starts_with($digits, '51')) {
            return false;
        }

        // Perú sin código: 9XXXXXXXX (9 dígitos).
        if ($len === 9 && str_starts_with($digits, '9')) {
            return false;
        }

        // LID típico: más de 12 dígitos y no empieza con 51.
        return $len >= 13;
    }

    private function digitsFromChatId(string $chatId): string
    {
        return preg_replace('/\D/', '', preg_replace('/@(c\.us|lid|s\.whatsapp\.net)$/', '', $chatId)) ?? '';
    }
}
