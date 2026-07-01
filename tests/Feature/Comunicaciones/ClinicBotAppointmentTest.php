<?php

declare(strict_types=1);

use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Services\ClinicBot\ClinicBotAppointmentService;
use App\Support\ClinicBot\ClinicBotPeruClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('ClinicBot citas requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('registra una cita para mascota del propietario por telefono', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-25 09:00:00', ClinicBotPeruClock::TIMEZONE));

    app(\App\Tenancy\TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Pérez',
            'telefono' => '+51 999 888 777',
            'activo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Firulais',
            'activo' => true,
        ]);

        $service = app(ClinicBotAppointmentService::class);
        $result = $service->registerCita(
            phone: '51999888777',
            pacienteId: $paciente->id,
            fecha: 'mañana',
            hora: '11:00',
            motivo: 'Control general',
        );

        expect($result['ok'])->toBeTrue()
            ->and(Cita::query()->count())->toBe(1);

        $cita = Cita::query()->first();
        expect($cita?->paciente_id)->toBe($paciente->id)
            ->and($cita?->motivo)->toBe('Control general');
    });
});

it('no registra cita si la mascota no pertenece al telefono', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-25 09:00:00', ClinicBotPeruClock::TIMEZONE));

    app(\App\Tenancy\TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Juan',
            'apellidos' => 'López',
            'telefono' => '987654321',
            'activo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Michi',
            'activo' => true,
        ]);

        $service = app(ClinicBotAppointmentService::class);
        $result = $service->registerCita(
            phone: '51999999999',
            pacienteId: $paciente->id,
            fecha: 'mañana',
            hora: '11:00',
        );

        expect($result['ok'])->toBeFalse()
            ->and(Cita::query()->count())->toBe(0);
    });
});
