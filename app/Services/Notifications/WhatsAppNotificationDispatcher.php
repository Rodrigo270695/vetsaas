<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Carbon\CarbonInterface;

final class WhatsAppNotificationDispatcher
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
    ) {}

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function dispatchPending(Tenant $tenant, int $limit = 50, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        if (! $this->client->isConfigured()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $items = NotificationQueue::query()
            ->where('estado', NotificationQueue::ESTADO_PENDIENTE)
            ->where('canal', NotificationQueue::CANAL_WHATSAPP)
            ->where('enviar_at', '<=', $now)
            ->whereColumn('intentos', '<', 'max_intentos')
            ->orderBy('prioridad')
            ->orderBy('enviar_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if ($this->deliverItem($item, $session)) {
                $sent++;
            } elseif ($item->fresh()?->estado === NotificationQueue::ESTADO_FALLIDO) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function dispatchOne(NotificationQueue $item, Tenant $tenant): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        $session = $this->resolveReadySession($tenant);

        return $session instanceof TenantWhatsAppSession
            && $this->deliverItem($item, $session);
    }

    private function deliverItem(NotificationQueue $item, TenantWhatsAppSession $session): bool
    {
        $item->forceFill([
            'estado' => NotificationQueue::ESTADO_PROCESANDO,
            'intentos' => $item->intentos + 1,
            'ultimo_intento_at' => now(),
        ])->save();

        try {
            $result = $this->client->sendText(
                $session->openwa_session_id,
                $item->destinatario,
                $item->cuerpo,
            );

            $item->forceFill([
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'proveedor_msg_id' => isset($result['messageId']) ? (string) $result['messageId'] : null,
                'error_mensaje' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            $estado = $item->intentos >= $item->max_intentos
                ? NotificationQueue::ESTADO_FALLIDO
                : NotificationQueue::ESTADO_PENDIENTE;

            $item->forceFill([
                'estado' => $estado,
                'error_mensaje' => $e->getMessage(),
            ])->save();

            return false;
        }
    }

    private function resolveReadySession(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $session = $this->sessionSync->ensureForTenant($tenant);
        } elseif (! $session->isReady()) {
            $session = $this->sessionSync->refresh($session);
        }

        return $session instanceof TenantWhatsAppSession && $session->isReady()
            ? $session
            : null;
    }
}
