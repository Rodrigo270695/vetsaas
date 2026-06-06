<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;

/**
 * URL de checkout Orvae para renovar la suscripción de un tenant.
 */
final class SubscriptionRenewalUrl
{
    public function for(Tenant $tenant, Subscription $subscription): string
    {
        $template = rtrim((string) config('billing.renewal_url', 'https://orvae.pe'), '/');
        $slug = (string) $tenant->slug;
        $plan = (string) ($subscription->plan?->codigo ?? '');
        $ciclo = (string) $subscription->ciclo;

        if (str_contains($template, '{tenant}')) {
            return str_replace(
                ['{tenant}', '{plan}', '{ciclo}'],
                [$slug, $plan, $ciclo],
                $template,
            );
        }

        $query = array_filter([
            'tenant' => $slug,
            'plan' => $plan !== '' ? $plan : null,
            'ciclo' => $ciclo,
        ]);

        return $query === []
            ? $template
            : $template.'?'.http_build_query($query);
    }
}
