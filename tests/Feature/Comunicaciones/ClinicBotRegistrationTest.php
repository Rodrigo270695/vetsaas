<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\Propietario;
use App\Services\ClinicBot\ClinicBotRegistrationService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('ClinicBot registro requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('registra propietario y mascota desde whatsapp', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $service = app(ClinicBotRegistrationService::class);

        $owner = $service->registerPropietario(
            phone: '51999111222',
            nombres: 'Carlos',
            apellidos: 'Ríos',
        );

        expect($owner['ok'])->toBeTrue()
            ->and(Propietario::query()->count())->toBe(1);

        $pet = $service->registerPaciente(
            phone: '51999111222',
            nombre: 'Brown',
            especie: 'perro',
            raza: 'Pastor alemán',
            edadAnios: 5,
        );

        expect($pet['ok'])->toBeTrue()
            ->and(Paciente::query()->count())->toBe(1);

        $paciente = Paciente::query()->first();
        expect($paciente?->nombre)->toBe('Brown')
            ->and($paciente?->especie)->toBe('perro')
            ->and($paciente?->fecha_nacimiento)->not->toBeNull();
    });
});

it('crea propietario al registrar mascota cuando el cliente confirma sus datos', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $service = app(ClinicBotRegistrationService::class);

        $pet = $service->registerPaciente(
            phone: '51988777666',
            nombre: 'Michi',
            especie: 'gato',
            edadAnios: 2,
            propietarioNombres: 'Ana',
            propietarioApellidos: 'Torres',
        );

        expect($pet['ok'])->toBeTrue()
            ->and(Propietario::query()->count())->toBe(1);

        $propietario = Propietario::query()->first();
        expect($propietario?->nombres)->toBe('Ana')
            ->and($propietario?->apellidos)->toBe('Torres');
    });
});

it('no registra mascota sin datos basicos del propietario', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $service = app(ClinicBotRegistrationService::class);

        $pet = $service->registerPaciente(
            phone: '51988777666',
            nombre: 'Michi',
            especie: 'gato',
        );

        expect($pet['ok'])->toBeFalse()
            ->and($pet['requiere_datos_propietario'] ?? false)->toBeTrue()
            ->and(Propietario::query()->count())->toBe(0)
            ->and(Paciente::query()->count())->toBe(0);
    });
});
