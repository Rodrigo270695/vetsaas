<?php

declare(strict_types=1);

use App\Models\Cita;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('La sala de espera usa schemas tenant; requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('lista citas y grooming de hoy en sala de espera', function (): void {
    $tz = (string) config('app.timezone');
    $hoy = now($tz)->setTime(11, 30);

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($hoy): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'López',
            'activo' => true,
        ]);
        $pacienteCita = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Luna',
            'activo' => true,
        ]);
        $pacienteGrooming = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Max',
            'activo' => true,
        ]);

        Cita::query()->create([
            'paciente_id' => $pacienteCita->id,
            'inicio_at' => $hoy,
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);

        GroomingTurno::query()->create([
            'paciente_id' => $pacienteGrooming->id,
            'inicio_at' => $hoy->copy()->addHour(),
            'duracion_minutos' => 45,
            'estado' => GroomingTurno::ESTADO_CONFIRMADA,
            'servicio' => 'baño',
        ]);

        Cita::query()->create([
            'paciente_id' => $pacienteCita->id,
            'inicio_at' => $hoy->copy()->addDay(),
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera')
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('espera.0.paciente', 'Luna')
        ->assertJsonPath('espera.1.paciente', 'Max');
});
