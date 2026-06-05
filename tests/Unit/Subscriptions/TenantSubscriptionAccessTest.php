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

it('bloquea suscripción en gracia', function (): void {
    $tenant = new Tenant(['estado' => 'active']);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'grace',
            'grace_ends_at' => now()->addDays(3),
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_EXPIRED);
});

it('bloquea período activo vencido', function (): void {
    $tenant = new Tenant(['estado' => 'active', 'id' => (string) Str::uuid()]);
    $tenant->setRelation('subscriptions', collect([
        new Subscription([
            'estado' => 'active',
            'current_period_end' => now()->subDay(),
        ]),
    ]));

    $access = new TenantSubscriptionAccess;

    expect($access->resolveDenial($tenant))->toBe(TenantSubscriptionAccess::DENIAL_EXPIRED);
});
