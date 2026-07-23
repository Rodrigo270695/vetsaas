<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use Illuminate\Support\Str;

it('permite acceso con suscripción activa vigente', function (): void {
    $tenant = new Tenant(['estado' => 'active']);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'active',
            'current_period_end' => now()->addMonth(),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->allowsAccess($tenant))->toBeTrue();
});

it('bloquea tenant suspendido', function (): void {
    $tenant = new Tenant(['estado' => 'suspended']);

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_SUSPENDED);
});

it('identifica como vencido el tenant suspendido automáticamente por impago', function (): void {
    $tenant = new Tenant(['estado' => 'suspended']);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'suspended',
            'current_period_end' => now()->subDay(),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_EXPIRED);
});

it('permite acceso en gracia mientras grace_ends_at es futuro', function (): void {
    config(['billing.grace_days' => 3]);

    $tenant = new Tenant(['estado' => 'active']);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'grace',
            'grace_ends_at' => now()->addDays(2),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->allowsAccess($tenant))->toBeTrue();
});

it('bloquea gracia cuando grace_ends_at ya pasó', function (): void {
    $tenant = new Tenant(['estado' => 'active']);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'grace',
            'grace_ends_at' => now()->subHour(),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_EXPIRED);
});

it('permite active recién vencido dentro de la ventana de gracia', function (): void {
    config(['billing.grace_days' => 3]);

    $tenant = new Tenant(['estado' => 'active', 'id' => (string) Str::uuid()]);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'active',
            'current_period_end' => now()->subDay(),
            'proximo_cobro_at' => now()->subDay(),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->allowsAccess($tenant))->toBeTrue();
});

it('bloquea active vencido fuera de la ventana de gracia', function (): void {
    config(['billing.grace_days' => 3]);

    $tenant = new Tenant(['estado' => 'active', 'id' => (string) Str::uuid()]);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'active',
            'current_period_end' => now()->subDays(5),
            'proximo_cobro_at' => now()->subDays(5),
            'precio_pactado' => '59.90',
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_EXPIRED);
});
