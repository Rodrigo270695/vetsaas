<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use RuntimeException;

/**
 * Operaciones de soporte sobre sesiones WhatsApp (OpenWA) de un tenant.
 */
final class TenantWhatsAppSessionAdmin
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sync,
    ) {}

    /**
     * @return array{session: TenantWhatsAppSession, has_qr: bool, qr_status: string|null, warnings: list<string>}
     */
    public function restart(Tenant $tenant): array
    {
        $this->assertConfigured();

        $session = $this->sync->ensureForTenant($tenant);
        if (! $session instanceof TenantWhatsAppSession) {
            throw new RuntimeException('No se pudo obtener la sesión WhatsApp del tenant.');
        }

        $sessionId = $session->openwa_session_id;
        $warnings = [];

        try {
            $this->client->stopSession($sessionId);
        } catch (\Throwable $e) {
            $warnings[] = 'stop: '.$e->getMessage();
        }

        try {
            $this->client->startSession($sessionId);
        } catch (\Throwable $e) {
            $session->forceFill([
                'last_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ])->save();

            throw new RuntimeException('OpenWA no pudo reiniciar la sesión: '.$e->getMessage(), 0, $e);
        }

        $session = $this->sync->refresh($session);

        return $this->withQrProbe($session, $warnings);
    }

    /**
     * @return array{session: TenantWhatsAppSession, has_qr: bool, qr_status: string|null, warnings: list<string>}
     */
    public function stop(Tenant $tenant): array
    {
        $this->assertConfigured();

        $session = $this->sync->ensureForTenant($tenant);
        if (! $session instanceof TenantWhatsAppSession) {
            throw new RuntimeException('No se pudo obtener la sesión WhatsApp del tenant.');
        }

        try {
            $this->client->stopSession($session->openwa_session_id);
        } catch (\Throwable $e) {
            $session->forceFill([
                'last_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ])->save();

            throw new RuntimeException('OpenWA no pudo detener la sesión: '.$e->getMessage(), 0, $e);
        }

        $session = $this->sync->refresh($session);

        return $this->withQrProbe($session, []);
    }

    private function assertConfigured(): void
    {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('OpenWA no está configurado en el servidor (OPENWA_ENABLED / OPENWA_API_KEY).');
        }
    }

    /**
     * @param  list<string>  $warnings
     * @return array{session: TenantWhatsAppSession, has_qr: bool, qr_status: string|null, warnings: list<string>}
     */
    private function withQrProbe(TenantWhatsAppSession $session, array $warnings): array
    {
        $hasQr = false;
        $qrStatus = null;

        if (! $session->isReady()) {
            try {
                $qr = $this->client->getQrCode($session->openwa_session_id);
                $qrStatus = is_string($qr['status'] ?? null) ? $qr['status'] : null;
                $hasQr = filled($qr['qrCode'] ?? null);
            } catch (\Throwable $e) {
                $warnings[] = 'qr: '.$e->getMessage();
            }
        }

        return [
            'session' => $session,
            'has_qr' => $hasQr,
            'qr_status' => $qrStatus,
            'warnings' => $warnings,
        ];
    }
}
