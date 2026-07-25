<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Alinea el webhook de SalesBot en la sesión OpenWA de plataforma
 * con SALESBOT_WEBHOOK_SECRET (firma HMAC + header legacy).
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
     *     webhook_url: string,
     *     action: 'created'|'updated'|'unchanged',
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
        $webhooks = $this->client->listWebhooks($sessionId);
        $matches = [];

        foreach ($webhooks as $webhook) {
            if (! is_array($webhook)) {
                continue;
            }

            $webhookUrl = (string) ($webhook['url'] ?? '');
            if ($this->isSalesBotUrl($webhookUrl)) {
                $matches[] = $webhook;
            }
        }

        $deletedDuplicates = 0;
        $action = 'unchanged';
        $webhookId = '';

        if ($matches === []) {
            $created = $this->client->registerWebhook($sessionId, $url, $secret);
            $webhookId = (string) ($created['id'] ?? '');
            $action = 'created';
        } else {
            $primary = array_shift($matches);
            $webhookId = (string) ($primary['id'] ?? '');

            if ($webhookId === '') {
                throw new RuntimeException('OpenWA devolvió un webhook sin id.');
            }

            $this->client->updateWebhook($sessionId, $webhookId, [
                'url' => $url,
                'events' => ['message.received'],
                'secret' => $secret,
                'headers' => [
                    'X-Webhook-Secret' => $secret,
                ],
                'active' => true,
            ]);
            $action = 'updated';

            foreach ($matches as $duplicate) {
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
            'webhook_id' => $webhookId,
            'url' => $url,
            'action' => $action,
            'deleted_duplicates' => $deletedDuplicates,
        ]);

        $result = [
            'session_id' => $sessionId,
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
