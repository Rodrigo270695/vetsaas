<?php

declare(strict_types=1);

use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update([
        'estado' => 'active',
        'modulos_deshabilitados' => ['grooming', 'hotel'],
    ]);
    $this->superadmin = $this->createTestSuperadmin();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra la matriz de módulos al superadmin', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/modulos-clinicas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/modulos-clinicas/index')
            ->has('items.data', 1)
            ->where('items.data.0.flags.grooming', false)
            ->where('items.data.0.flags.hotel', false)
            ->where('stats.con_apagados', 1));
});

it('filtra clínicas sin grooming', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/modulos-clinicas?scope=sin_grooming')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('filters.scope', 'sin_grooming'));
});

it('rechaza la matriz a un admin de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://127.0.0.1/plataforma/modulos-clinicas')
        ->assertForbidden();
});
