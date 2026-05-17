<?php

declare(strict_types=1);

namespace App\Support\Orvae;

use Illuminate\Support\Str;

/**
 * Adapta el JSON anidado de Orvae (Aula Virtual / checkout) al contrato plano de VetSaaS.
 */
final class ProvisionPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        if (! isset($input['tenant']) || ! is_array($input['tenant'])) {
            return $input;
        }

        $customer = is_array($input['customer'] ?? null) ? $input['customer'] : [];
        $tenant = $input['tenant'];
        $subscription = is_array($input['subscription'] ?? null) ? $input['subscription'] : [];

        $firstName = trim((string) ($customer['first_name'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? ''));
        if ($firstName === '' && $lastName === '') {
            $firstName = 'Administrador';
            $lastName = 'Clínica';
        }

        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug === '') {
            $slug = Str::slug((string) ($tenant['name'] ?? 'clinica'));
        }

        $planSlug = strtolower(trim((string) (
            $subscription['plan_slug']
            ?? $input['plan_slug']
            ?? ''
        )));

        $payment = null;
        if (isset($subscription['amount_paid']) || isset($subscription['payment_reference'])) {
            $payment = [
                'monto' => (float) ($subscription['amount_paid'] ?? 0),
                'moneda' => strtoupper((string) ($subscription['currency'] ?? 'PEN')),
                'pasarela' => (string) ($subscription['payment_method'] ?? 'orvae'),
                'transaction_id' => $subscription['payment_reference'] ?? null,
                'pagado_at' => $subscription['started_at'] ?? now()->toIso8601String(),
            ];
        }

        return array_merge($input, [
            'external_order_id' => (string) ($input['external_order_id'] ?? $input['order_number'] ?? ''),
            'plan_slug' => $planSlug,
            'tenant_slug' => $slug,
            'razon_social' => trim((string) ($tenant['name'] ?? 'Clínica veterinaria')),
            'nombre_comercial' => trim((string) ($tenant['name'] ?? '')) ?: null,
            'telefono' => $customer['phone'] ?? null,
            'admin_nombres' => $firstName,
            'admin_apellidos' => $lastName,
            'admin_email' => strtolower(trim((string) ($customer['email'] ?? ''))),
            'admin_password' => $input['admin_password'] ?? Str::password(16),
            'canal_adquisicion' => 'orvae',
            'payment' => $payment,
        ]);
    }
}
