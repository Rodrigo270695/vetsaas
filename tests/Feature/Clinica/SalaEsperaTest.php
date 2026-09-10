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

it('lista la cola de consulta por hora actual', function (): void {
    $tz = (string) config('app.timezone');
    $ahora = now($tz)->subMinutes(5);

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($ahora): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'López',
            'activo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Luna',
            'activo' => true,
        ]);

        Cita::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => $ahora,
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera?tipo=consulta')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('espera.0.paciente', 'Luna');
});

it('pasa un paciente a sala de peluquería', function (): void {
    $pacienteId = app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): string {
        $propietario = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'López',
            'activo' => true,
        ]);

        return (string) Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Max',
            'activo' => true,
        ])->id;
    });

    $this->actingAs($this->testTenantAdmin)
        ->postJson('http://'.$this->testTenantHost.'/clinica/sala-espera/enviar', [
            'paciente_id' => $pacienteId,
            'tipo' => 'grooming',
        ])
        ->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('item.paciente', 'Max');
});
