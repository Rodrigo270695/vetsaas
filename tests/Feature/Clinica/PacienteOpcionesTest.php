<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\Propietario;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Los pacientes viven en el schema del tenant; requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('encuentra un paciente activo por nombre o por titular aunque no esté entre los recientes', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Reveca Daniela',
            'apellidos' => 'Chavez Apolitano',
            'telefono' => '51999888777',
            'activo' => true,
        ]);

        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Bubba Chavez',
            'especie' => 'Perro',
            'activo' => true,
        ]);

        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Inactivo',
            'activo' => false,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/pacientes/opciones?q=bubba')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'Bubba Chavez · Reveca Daniela Chavez Apolitano');

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/pacientes/opciones?q=chavez')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/pacientes/opciones?q=inactivo')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
