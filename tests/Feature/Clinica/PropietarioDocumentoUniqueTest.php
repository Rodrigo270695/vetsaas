<?php

declare(strict_types=1);

use App\Models\Propietario;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Unicidad de documento en propietarios requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('rechaza registrar un propietario duplicado por documento en el mismo tenant', function (): void {
    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '77344506',
            'nombres' => 'Rodrigo',
            'apellidos' => 'Granja Requejo',
            'activo' => true,
        ]);
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->postJson('http://'.$this->testTenantHost.'/caja/ventas/propietarios-rapido', [
        'tipo_documento' => 'DNI',
        'numero_documento' => '77344506',
        'nombres' => 'RODRIGO',
        'apellidos' => 'GRANJA REQUEJO',
        'activo' => true,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['numero_documento']);

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Propietario::query()->count())->toBe(1);
    });
});

it('normaliza el dni y rechaza duplicados con formato distinto', function (): void {
    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '77344506',
            'nombres' => 'Rodrigo',
            'apellidos' => 'Granja',
            'activo' => true,
        ]);
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->postJson('http://'.$this->testTenantHost.'/caja/ventas/propietarios-rapido', [
        'tipo_documento' => 'DNI',
        'numero_documento' => '77.344.506',
        'nombres' => 'Otro',
        'apellidos' => 'Nombre',
        'activo' => true,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['numero_documento']);
});

it('permite actualizar un propietario conservando su propio documento', function (): void {
    $propietarioId = null;

    TenantContext::runForSlug($this->testTenantSlug, function () use (&$propietarioId): void {
        $propietario = Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'activo' => true,
        ]);
        $propietarioId = $propietario->id;
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->put('http://'.$this->testTenantHost.'/clinica/propietarios/'.$propietarioId, [
        'tipo_documento' => 'DNI',
        'numero_documento' => '12345678',
        'nombres' => 'Ana María',
        'apellidos' => 'Pérez',
        'activo' => true,
    ]);

    $response->assertSessionHasNoErrors();
});
