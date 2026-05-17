<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Dashboard tenant requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra el panel del tenant con KPIs en el subdominio de la clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/index')
        ->has('kpis')
        ->has('capabilities')
        ->has('ventas_por_dia')
        ->where('clinic_label', 'Test Clinic')
    );
});

it('muestra panel central en el host sin tenant resuelto', function (): void {
    $this->superadmin = $this->createTestSuperadmin();
    $this->actingAs($this->superadmin);

    $response = $this->get('http://127.0.0.1/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard/central'));
});
