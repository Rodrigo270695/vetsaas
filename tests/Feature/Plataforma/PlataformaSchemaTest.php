<?php

declare(strict_types=1);

use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->superadmin = $this->createTestSuperadmin();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra el modelo ER al superadmin', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/esquema')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/esquema/index')
            ->has('schemas')
            ->has('diagram.tables')
            ->where('diagram.schema', 'public'));
});

it('rechaza el modelo ER a un admin de clínica', function (): void {
    $this->createTestTenantWithSchema();

    $this->actingAs($this->testTenantAdmin)
        ->get('http://127.0.0.1/plataforma/esquema')
        ->assertForbidden();
});
