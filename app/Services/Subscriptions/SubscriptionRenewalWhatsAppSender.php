<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalReminder;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Subscriptions\SubscriptionRenewalBilling;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

/**
 * Envío manual del link de renovación (soporte), sin reglas de día ni pago cubierto.
 */
final class SubscriptionRenewalWhatsAppSender
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly SubscriptionRenewalMessageBuilder $messageBuilder,
    ) {}

    /**
     * @return array{ok: bool, error: string|null, message: string|null, destinatario: string|null}
     */
    public function sendManual(Subscription $subscription, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->estado === 'cancelled' || $subscription->cancelled_at !== null) {
            return $this->fail('La suscripción está cancelada.');
        }

        if (! in_array($subscription->estado, ['active', 'trial', 'grace', 'suspended'], true)) {
            return $this->fail('Solo se puede enviar a suscripciones vigentes, en prueba, en gracia o suspendidas por pago.');
        }

        if (! SubscriptionRenewalBilling::isBillable($subscription)) {
            return $this->fail('Esta suscripción no tiene monto de renovación configurado.');
        }

        if (! $this->messenger->isReady()) {
            return $this->fail('WhatsApp de plataforma no conectado. Conéctalo en Avisos renovación.');
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return $this->fail('No se encontró el tenant asociado.');
        }

        $anchor = $this->expiryAnchor($subscription);
        if ($anchor === null) {
            return $this->fail('Falta fecha de vencimiento (próximo cobro o fin de período).');
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            return $this->fail('El tenant no tiene teléfono válido para WhatsApp.');
        }

        $expired = (int) $now->copy()->startOfDay()->diffInDays($anchor->copy()->startOfDay(), false) < 0;
        $message = $this->messageBuilder->build($tenant, $subscription, $anchor, $expired);

        try {
            $this->messenger->sendText($chatId, $message);
        } catch (\Throwable $e) {
            return $this->fail(
                app()->hasDebugModeEnabled()
                    ? 'No se pudo enviar: '.$e->getMessage()
                    : 'No se pudo enviar el WhatsApp. Revisa la sesión de plataforma.',
            );
        }

        SubscriptionRenewalReminder::query()->create([
            'subscription_id' => $subscription->id,
            'reminder_kind' => SubscriptionRenewalReminder::KIND_MANUAL,
            'anchor_at' => $anchor,
            'channel' => SubscriptionRenewalReminder::CHANNEL_WHATSAPP,
            'destinatario' => $chatId,
            'sent_at' => now(),
        ]);

        return [
            'ok' => true,
            'error' => null,
            'message' => $message,
            'destinatario' => $chatId,
        ];
    }

    /**
     * @return array{ok: false, error: string, message: null, destinatario: null}
     */
    private function fail(string $error): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'message' => null,
            'destinatario' => null,
        ];
    }

    private function expiryAnchor(Subscription $subscription): ?CarbonInterface
    {
        if ($subscription->estado === 'trial') {
            return $subscription->trial_ends_at?->copy();
        }

        return ($subscription->proximo_cobro_at ?? $subscription->current_period_end)?->copy();
    }
}
