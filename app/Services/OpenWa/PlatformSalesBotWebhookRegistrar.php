<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Alinea el webhook de SalesBot en la sesión OpenWA de plataforma
 * con SALESBOT_WEBHOOK_SECRET (firma HMAC + header legacy).
 *
 * También desactiva/borra webhooks sales-bot huérfanos en otras sesiones
 * (p. ej. UUID viejo con secret distinto).
 */
final class PlatformSalesBotWebhookRegistrar
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sync,
    ) {}

    /**
     * @return array{
     *     session_id: string,
     *     session_name: string,
     *     webhook_url: string,
     *     action: 'created'|'updated',
     *     webhook_id: string,
     *     deleted_duplicates: int,
     *     test?: array{success?: bool, statusCode?: int, error?: string}
     * }
     */
    public function ensure(bool $runTest = true): array
    {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('OpenWA no está configurado (OPENWA_ENABLED / OPENWA_API_KEY).');
        }

        $secret = (string) config('salesbot.webhook_secret', '');
        if ($secret === '') {
            throw new RuntimeException('SALESBOT_WEBHOOK_SECRET está vacío.');
        }

        $url = $this->webhookUrl();
        if ($url === '') {
            throw new RuntimeException('No se pudo resolver la URL del webhook (APP_URL).');
        }

        $session = $this->sync->ensure();
        if ($session === null) {
            throw new RuntimeException('No hay sesión OpenWA de plataforma.');
        }

        $sessionId = $session->openwa_session_id;
        $sessionName = $session->openwa_session_name;
        $all = $this->client->listAllWebhooks();
        $onTarget = [];
        $orphans = [];

        foreach ($all as $webhook) {
            if (! is_array($webhook)) {
                continue;
            }

            if (! $this->isSalesBotUrl((string) ($webhook['url'] ?? ''))) {
                continue;
            }

            $webhookSessionId = (string) ($webhook['sessionId'] ?? '');
            if ($webhookSessionId === $sessionId) {
                $onTarget[] = $webhook;
            } else {
                $orphans[] = $webhook;
            }
        }

        $deletedDuplicates = 0;

        foreach ($orphans as $orphan) {
            $orphanSessionId = (string) ($orphan['sessionId'] ?? '');
            $orphanId = (string) ($orphan['id'] ?? '');
            if ($orphanSessionId === '' || $orphanId === '') {
                continue;
            }

            try {
                $this->client->deleteWebhook($orphanSessionId, $orphanId);
                $deletedDuplicates++;
                Log::warning('SalesBot: webhook OpenWA huérfano eliminado', [
                    'webhook_id' => $orphanId,
                    'session_id' => $orphanSessionId,
                    'url' => $orphan['url'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('SalesBot: no se pudo borrar webhook huérfano', [
                    'webhook_id' => $orphanId,
                    'session_id' => $orphanSessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($onTarget === []) {
            $created = $this->client->registerWebhook($sessionId, $url, $secret);
            $webhookId = (string) ($created['id'] ?? '');
            $action = 'created';
        } else {
            $primary = array_shift($onTarget);
            $webhookId = (string) ($primary['id'] ?? '');

            if ($webhookId === '') {
                throw new RuntimeException('OpenWA devolvió un webhook sin id.');
            }

            $this->client->updateWebhook($sessionId, $webhookId, [
                'url' => $url,
                'events' => \App\Support\OpenWa\OpenWaWebhookEvents::inboundMessageSubscriptions(),
                'secret' => $secret,
                'headers' => [
                    'X-Webhook-Secret' => $secret,
                ],
                'active' => true,
            ]);
            $action = 'updated';

            foreach ($onTarget as $duplicate) {
                $dupId = (string) ($duplicate['id'] ?? '');
                if ($dupId === '') {
                    continue;
                }
                $this->client->deleteWebhook($sessionId, $dupId);
                $deletedDuplicates++;
            }
        }

        Log::info('SalesBot: webhook OpenWA alineado', [
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'webhook_id' => $webhookId,
            'url' => $url,
            'action' => $action,
            'deleted_duplicates' => $deletedDuplicates,
        ]);

        $result = [
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'webhook_url' => $url,
            'action' => $action,
            'webhook_id' => $webhookId,
            'deleted_duplicates' => $deletedDuplicates,
        ];

        if ($runTest && $webhookId !== '') {
            $result['test'] = $this->client->testWebhook($sessionId, $webhookId);
        }

        return $result;
    }

    private function webhookUrl(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl.'/api/webhooks/sales-bot';
    }

    private function isSalesBotUrl(string $url): bool
    {
        return str_contains($url, '/api/webhooks/sales-bot');
    }
}
