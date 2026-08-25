<?php

declare(strict_types=1);

use App\Support\Orvae\ProvisionPayloadNormalizer;

it('normaliza payload anidado de Orvae al contrato plano de VetSaaS', function (): void {
    $normalized = ProvisionPayloadNormalizer::normalize([
        'external_order_id' => 'ord-99',
        'order_number' => 'ORV-99',
        'customer' => [
            'email' => 'vet@clinica.test',
            'first_name' => 'María',
            'last_name' => 'López',
            'phone' => '999888777',
        ],
        'tenant' => [
            'name' => 'Clínica San Patricio',
            'slug' => 'san-patricio',
        ],
        'subscription' => [
            'plan_slug' => 'pro',
            'amount_paid' => '149.00',
            'currency' => 'PEN',
            'payment_method' => 'culqi',
            'payment_reference' => 'chg_123',
        ],
    ]);

    expect($normalized['plan_slug'])->toBe('pro')
        ->and($normalized['tenant_slug'])->toBe('san-patricio')
        ->and($normalized['razon_social'])->toBe('Clínica San Patricio')
        ->and($normalized['admin_email'])->toBe('vet@clinica.test')
        ->and($normalized['admin_nombres'])->toBe('María')
        ->and($normalized['admin_apellidos'])->toBe('López')
        ->and((string) $normalized['admin_password'])->not->toBe('')
        ->and($normalized['payment']['monto'])->toBe(149.0)
        ->and($normalized['canal_adquisicion'])->toBe('orvae')
        ->and($normalized['referral_code'])->toBeNull();
});

it('propaga referral_code / promo_code y marca canal referido', function (): void {
    $normalized = ProvisionPayloadNormalizer::normalize([
        'external_order_id' => 'ord-ref',
        'referral_code' => 'MEDIVET',
        'customer' => [
            'email' => 'otra@clinica.test',
            'first_name' => 'Ana',
            'last_name' => 'Ruiz',
        ],
        'tenant' => [
            'name' => 'Otra Clínica',
            'slug' => 'otra-clinica',
        ],
        'subscription' => [
            'plan_slug' => 'pro',
        ],
    ]);

    expect($normalized['referral_code'])->toBe('MEDIVET')
        ->and($normalized['canal_adquisicion'])->toBe('referido');
});
