<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Services\Notifications\WhatsAppNotificationDispatcher;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía WhatsApp fuera del request HTTP (afterResponse) para no bloquear el CRUD
 * cuando OpenWA tarda o hace timeout (hasta OPENWA_TIMEOUT_SECONDS).
 */
final class DeferredWhatsAppDispatch
{
    /**
     * Despacha un ítem ya encolado en notification_queue tras enviar la respuesta.
     */
    public static function queueItem(NotificationQueue $item, Tenant $tenant): void
    {
        $itemId = (string) $item->id;
        $tenantId = (string) $tenant->id;
        $tenantSlug = (string) ($tenant->slug ?? '');

        if ($tenantSlug === '') {
            return;
        }

        dispatch(static function () use ($itemId, $tenantId, $tenantSlug): void {
            try {
                app(TenantManager::class)->runForSlug($tenantSlug, static function () use ($itemId, $tenantId): void {
                    $item = NotificationQueue::query()->find($itemId);
                    $tenant = Tenant::query()->find($tenantId);
                    if ($item === null || $tenant === null) {
                        return;
                    }

                    if ($item->estado !== NotificationQueue::ESTADO_PENDIENTE) {
                        return;
                    }

                    app(WhatsAppNotificationDispatcher::class)->dispatchOne($item, $tenant);
                });
            } catch (Throwable $e) {
                Log::warning('Deferred WhatsApp dispatch falló; queda en cola', [
                    'notification_queue_id' => $itemId,
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Ejecuta un callback en contexto de tenant tras la respuesta HTTP.
     *
     * @param  callable(): void  $callback
     */
    public static function runForTenantSlug(string $tenantSlug, callable $callback): void
    {
        if ($tenantSlug === '') {
            return;
        }

        dispatch(static function () use ($tenantSlug, $callback): void {
            try {
                app(TenantManager::class)->runForSlug($tenantSlug, static function () use ($callback): void {
                    $callback();
                });
            } catch (Throwable $e) {
                Log::warning('Deferred WhatsApp tenant callback falló', [
                    'tenant_slug' => $tenantSlug,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
