<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Subscriptions\SubscriptionRenewalBilling;
use App\Support\Subscriptions\SubscriptionRenewalUrl;
use Carbon\CarbonInterface;

final class SubscriptionRenewalMessageBuilder
{
    public function __construct(
        private readonly SubscriptionRenewalUrl $renewalUrl,
    ) {}

    public function build(
        Tenant $tenant,
        Subscription $subscription,
        CarbonInterface $anchor,
        bool $expired = false,
    ): string {
        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social));
        $ciclo = $subscription->ciclo === 'anual' ? 'anual' : 'mensual';
        $fecha = $anchor->timezone(config('app.timezone', 'America/Lima'))->format('d/m/Y');
        $renewUrl = $this->renewalUrl->for($tenant, $subscription);
        $total = SubscriptionRenewalBilling::totalAmount($subscription, $tenant);
        $planAmount = SubscriptionRenewalBilling::planAmount($subscription);
        $botIaAmount = SubscriptionRenewalBilling::botIaAmount($subscription);
        $comprobantesAmount = SubscriptionRenewalBilling::comprobantesOverageAmount($tenant);

        $amountLines = [];
        if ($total > 0) {
            $amountLines[] = sprintf('Total a renovar: S/ %.2f', $total);
            $parts = [sprintf('plan S/ %.2f', $planAmount)];
            if ($botIaAmount > 0) {
                $parts[] = sprintf('asistente IA S/ %.2f (renovación del plan)', $botIaAmount);
            }
            if ($comprobantesAmount > 0) {
                $parts[] = sprintf('comprobantes extra S/ %.2f', $comprobantesAmount);
            }
            if (count($parts) > 1) {
                $amountLines[] = '('.implode(' + ', $parts).')';
            }
        }

        $statusLine = $expired
            ? "Tu plan VetSaaS ({$ciclo}) venció el {$fecha}."
            : "Tu plan VetSaaS ({$ciclo}) vence el {$fecha}.";

        $ctaLine = $expired
            ? 'Renueva para reactivar el acceso a la plataforma.'
            : 'Renueva para seguir usando la plataforma sin interrupciones.';

        return implode("\n", array_filter([
            "Hola, {$name} 👋",
            '',
            $statusLine,
            $ctaLine,
            ...($amountLines !== [] ? ['', ...$amountLines] : []),
            '',
            "Paga aquí: {$renewUrl}",
            '',
            'Si ya pagaste, tu próximo vencimiento se actualizará automáticamente.',
            '',
            'Soporte Orvae',
        ]));
    }
}
