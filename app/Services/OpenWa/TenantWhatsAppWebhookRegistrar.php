<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\TenantWhatsAppSession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alinea un único webhook clinic-bot por sesión OpenWA.
 * OpenWA limita a 16 webhooks/sesión: no se debe hacer POST en cada sync.
 */
final class TenantWhatsAppWebhookRegistrar
{
    public function __construct(
        private readonly OpenWaClient $client,
    ) {}

    public function ensureForSession(TenantWhatsAppSession $session): void
    {
        if (! $this->client->isConfigured() || ! $session->isReady()) {
            return;
        }

        $url = $this->clinicBotUrl();
        if ($url === '') {
            return;
        }

        $sessionId = (string) $session->openwa_session_id;
        if ($sessionId === '') {
            return;
        }

        $secret = (string) config('bot-ia.webhook_secret', '');

        try {
            $this->align($session, $sessionId, $url, $secret);
        } catch (Throwable $e) {
            Log::warning('ClinicBot: no se pudo registrar webhook OpenWA', [
                'session_id' => $sessionId,
                'tenant_id' => $session->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function align(TenantWhatsAppSession $session, string $sessionId, string $url, string $secret): void
    {
        $hooks = $this->webhooksForSession($sessionId);
        $clinic = [];
        $others = [];

        foreach ($hooks as $hook) {
            if ($this->isClinicBotUrl((string) ($hook['url'] ?? ''))) {
                $clinic[] = $hook;
            } else {
                $others[] = $hook;
            }
        }

        $deleted = 0;
        $clinicKept = $clinic !== [] ? array_shift($clinic) : null;
        foreach ($clinic as $dup) {
            $deleted += $this->tryDelete($sessionId, $dup);
        }

        $deleted += $this->deleteDuplicateUrls($sessionId, $others);

        if ($clinicKept !== null) {
            $webhookId = (string) ($clinicKept['id'] ?? '');
            if ($webhookId === '') {
                throw new \RuntimeException('Webhook clinic-bot sin id.');
            }

            $this->client->updateWebhook($sessionId, $webhookId, $this->webhookPayload($url, $secret));
            Log::info('ClinicBot: webhook OpenWA actualizado', [
                'session_id' => $sessionId,
                'tenant_id' => $session->tenant_id,
                'webhook_id' => $webhookId,
                'deleted_duplicates' => $deleted,
            ]);

            return;
        }

        $hooks = $this->webhooksForSession($sessionId);
        if (count($hooks) >= 16) {
            Log::warning('ClinicBot: sesión OpenWA llena (16 webhooks) y no hay clinic-bot; no se crea otro', [
                'session_id' => $sessionId,
                'tenant_id' => $session->tenant_id,
                'urls' => array_values(array_filter(array_map(
                    static fn (array $h): string => (string) ($h['url'] ?? ''),
                    $hooks,
                ))),
            ]);

            return;
        }

        $created = $this->client->registerWebhook(
            $sessionId,
            $url,
            $secret !== '' ? $secret : null,
        );
        Log::info('ClinicBot: webhook OpenWA registrado', [
            'session_id' => $sessionId,
            'tenant_id' => $session->tenant_id,
            'webhook_id' => $created['id'] ?? null,
            'url' => $url,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function webhooksForSession(string $sessionId): array
    {
        $listed = $this->client->listWebhooks($sessionId);
        $out = [];
        foreach ($listed as $hook) {
            if (is_array($hook) && (string) ($hook['id'] ?? '') !== '') {
                $out[] = $hook;
            }
        }

        if ($out !== []) {
            return $out;
        }

        foreach ($this->client->listAllWebhooks() as $hook) {
            if (! is_array($hook)) {
                continue;
            }
            $hookSession = (string) ($hook['sessionId'] ?? $hook['session_id'] ?? '');
            if ($hookSession === $sessionId && (string) ($hook['id'] ?? '') !== '') {
                $out[] = $hook;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $hooks
     */
    private function deleteDuplicateUrls(string $sessionId, array $hooks): int
    {
        $byUrl = [];
        foreach ($hooks as $hook) {
            $key = strtolower(trim((string) ($hook['url'] ?? '')));
            if ($key === '') {
                $key = '_empty_'.(string) ($hook['id'] ?? uniqid());
            }
            $byUrl[$key][] = $hook;
        }

        $deleted = 0;
        foreach ($byUrl as $list) {
            array_shift($list);
            foreach ($list as $dup) {
                $deleted += $this->tryDelete($sessionId, $dup);
            }
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $hook
     */
    private function tryDelete(string $sessionId, array $hook): int
    {
        $id = (string) ($hook['id'] ?? '');
        if ($id === '') {
            return 0;
        }

        try {
            $this->client->deleteWebhook($sessionId, $id);

            return 1;
        } catch (Throwable $e) {
            Log::warning('ClinicBot: no se pudo borrar webhook duplicado', [
                'session_id' => $sessionId,
                'webhook_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(string $url, string $secret): array
    {
        $payload = [
            'url' => $url,
            'events' => ['message.received'],
            'active' => true,
        ];
        if ($secret !== '') {
            $payload['secret'] = $secret;
            $payload['headers'] = [
                'X-Webhook-Secret' => $secret,
            ];
        }

        return $payload;
    }

    private function clinicBotUrl(): string
    {
        $url = trim((string) config('bot-ia.webhook_url', ''));
        if ($url !== '') {
            return $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' ? $appUrl.'/api/webhooks/clinic-bot' : '';
    }

    private function isClinicBotUrl(string $url): bool
    {
        return str_contains($url, '/api/webhooks/clinic-bot');
    }
}
