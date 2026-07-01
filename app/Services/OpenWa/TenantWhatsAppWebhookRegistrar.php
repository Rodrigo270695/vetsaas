<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\TenantWhatsAppSession;
use Illuminate\Support\Facades\Log;

/**
 * Registra el webhook del asistente IA en la sesión OpenWA del tenant.
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

        $url = trim((string) config('bot-ia.webhook_url', ''));
        if ($url === '') {
            $appUrl = rtrim((string) config('app.url'), '/');
            if ($appUrl !== '') {
                $url = $appUrl.'/api/webhooks/clinic-bot';
            }
        }

        if ($url === '') {
            return;
        }

        $secret = (string) config('bot-ia.webhook_secret', '');

        try {
            $this->client->registerWebhook(
                $session->openwa_session_id,
                $url,
                $secret !== '' ? $secret : null,
            );
            Log::info('ClinicBot: webhook OpenWA registrado', [
                'session_id' => $session->openwa_session_id,
                'tenant_id' => $session->tenant_id,
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ClinicBot: no se pudo registrar webhook OpenWA', [
                'session_id' => $session->openwa_session_id,
                'tenant_id' => $session->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
