<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

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

    /**
     * Corrige ítems que ya salieron por WhatsApp pero quedaron pendiente/fallido
     * por un 5xx/timeout ambiguo de OpenWA (sin reenviar).
     */
    public function healAmbiguousStuck(int $limit = 100): int
    {
        $items = NotificationQueue::query()
            ->whereIn('estado', [
                NotificationQueue::ESTADO_PENDIENTE,
                NotificationQueue::ESTADO_FALLIDO,
            ])
            ->where('canal', NotificationQueue::CANAL_WHATSAPP)
            ->where('intentos', '>', 0)
            ->whereNotNull('error_mensaje')
            ->orderBy('enviar_at')
            ->limit($limit)
            ->get();

        $healed = 0;

        foreach ($items as $item) {
            if (! $this->client->isAmbiguousDeliveryErrorMessage((string) $item->error_mensaje)) {
                continue;
            }

            $item->forceFill([
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'error_mensaje' => null,
            ])->save();
            $healed++;
        }

        return $healed;
    }

    private function deliverItem(NotificationQueue $item, TenantWhatsAppSession $session): bool
    {
        // Reintento tras 5xx: el mensaje casi seguro ya salió; no reenviar (evita duplicados).
        if ($item->intentos > 0
            && $this->client->isAmbiguousDeliveryErrorMessage((string) $item->error_mensaje)) {
            Log::warning('Cola WhatsApp: intento previo con error ambiguo; se marca enviado sin reenviar', [
                'notification_id' => $item->id,
                'tipo' => $item->tipo,
                'error' => $item->error_mensaje,
            ]);

            $item->forceFill([
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'error_mensaje' => null,
            ])->save();

            return true;
        }

        $item->forceFill([
            'estado' => NotificationQueue::ESTADO_PROCESANDO,
            'intentos' => $item->intentos + 1,
            'ultimo_intento_at' => now(),
        ])->save();

        try {
            // OpenWA a veces responde 5xx/timeout aunque el mensaje ya salió.
            $result = $this->client->sendTextWithDeliveryFallback(
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
            // Cinturón: si el fallback no atrapó el 5xx, igual no dejar pendiente/falso fallo.
            if ($this->client->isAmbiguousDeliveryError($e)) {
                Log::warning('Cola WhatsApp: error ambiguo en dispatch; se asume enviado', [
                    'notification_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);

                $item->forceFill([
                    'estado' => NotificationQueue::ESTADO_ENVIADO,
                    'error_mensaje' => null,
                    'proveedor_msg_id' => null,
                ])->save();

                return true;
            }

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
