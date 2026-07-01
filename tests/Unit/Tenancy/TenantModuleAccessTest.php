<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use Tests\TestCase;

uses(TestCase::class);

it('habilita todos los módulos por defecto', function (): void {
    $tenant = new Tenant;

    expect(TenantModuleAccess::isEnabled($tenant, 'hotel'))->toBeTrue()
        ->and(TenantModuleAccess::snapshot($tenant)['enabled']['hotel'])->toBeTrue();
});

it('oculta hotel cuando está en modulos_deshabilitados', function (): void {
    $tenant = new Tenant([
        'modulos_deshabilitados' => ['hotel'],
    ]);

    expect(TenantModuleAccess::isEnabled($tenant, 'hotel'))->toBeFalse()
        ->and(TenantModuleAccess::filterCapabilities($tenant, ['hotel' => true])['hotel'])->toBeFalse();
});

it('bloquea tab hotel en tarifas si el módulo está apagado', function (): void {
    $tenant = new Tenant([
        'modulos_deshabilitados' => ['hotel'],
    ]);

    expect(TenantModuleAccess::isTarifasTabEnabled($tenant, 'hotel'))->toBeFalse()
        ->and(TenantModuleAccess::isTarifasTabEnabled($tenant, 'grooming'))->toBeTrue();
});
