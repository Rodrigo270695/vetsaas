<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\Propietario;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Alta de propietario con mascotas requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('crea el propietario y varias mascotas en el mismo alta', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/propietarios', [
        'nombres' => 'Carla',
        'apellidos' => 'Mendoza',
        'activo' => true,
        'mascotas' => [
            [
                'nombre' => 'Luna',
                'especie' => 'Canino',
                'raza' => 'Mestizo',
                'sexo' => 'H',
                'fecha_nacimiento' => '2022-03-10',
                'peso_kg' => '8.5',
                'color' => 'Blanco',
            ],
            [
                'nombre' => 'Max',
                'especie' => 'Felino',
                'raza' => '',
                'sexo' => 'M',
            ],
            [
                'nombre' => '',
                'especie' => 'Canino',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        $owner = Propietario::query()->where('nombres', 'Carla')->first();
        expect($owner)->not->toBeNull();

        $pets = Paciente::query()->where('propietario_id', $owner->id)->orderBy('nombre')->get();
        expect($pets)->toHaveCount(2)
            ->and($pets[0]->nombre)->toBe('Luna')
            ->and($pets[0]->especie)->toBe('Canino')
            ->and($pets[0]->sexo)->toBe('H')
            ->and($pets[1]->nombre)->toBe('Max')
            ->and($pets[1]->especie)->toBe('Felino');
    });
});

it('crea el propietario sin mascotas si las filas no tienen nombre', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/propietarios', [
        'nombres' => 'Diego',
        'apellidos' => 'Rivas',
        'activo' => true,
        'mascotas' => [
            ['nombre' => '', 'especie' => 'Canino'],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Propietario::query()->where('nombres', 'Diego')->count())->toBe(1)
            ->and(Paciente::query()->count())->toBe(0);
    });
});
