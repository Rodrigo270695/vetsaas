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

it('no lista citas del día que nadie mandó a sala', function (): void {
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
        ->assertJsonPath('count', 0)
        ->assertJsonPath('visible', false);
});

it('lista la cola de consulta solo si se mandó a sala', function (): void {
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
            'sala_espera_enviado_at' => $ahora,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera?tipo=consulta')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('visible', true)
        ->assertJsonPath('espera.0.paciente', 'Luna')
        ->assertJsonPath('espera.0.propietario', 'Ana López');
});

it('el resumen oculta iconos si no hay nadie enviado a sala', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera/resumen')
        ->assertOk()
        ->assertJsonPath('consulta', false)
        ->assertJsonPath('grooming', false);
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

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera/resumen')
        ->assertOk()
        ->assertJsonPath('grooming', true);
});

it('asigna número de turno al enviar a sala', function (): void {
    $pacienteId = app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): string {
        $propietario = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'López',
            'activo' => true,
        ]);

        return (string) Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Kira',
            'activo' => true,
        ])->id;
    });

    $this->actingAs($this->testTenantAdmin)
        ->postJson('http://'.$this->testTenantHost.'/clinica/sala-espera/enviar', [
            'paciente_id' => $pacienteId,
            'tipo' => 'consulta',
        ])
        ->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('item.numero', 1)
        ->assertJsonPath('item.propietario', 'Ana López');
});

it('busca mascotas por nombre del propietario', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Carlos',
            'apellidos' => 'Mendoza',
            'activo' => true,
        ]);
        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Rocky',
            'activo' => true,
        ]);
        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Misha',
            'activo' => true,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->getJson('http://'.$this->testTenantHost.'/clinica/sala-espera/buscar?q=Mendoza')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['nombre' => 'Rocky'])
        ->assertJsonFragment(['nombre' => 'Misha']);
});

it('muestra la vista Inertia de sala de espera al admin de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/clinica/sala-espera')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clinica/sala-espera/index'));
});

it('niega la vista de sala de espera al veterinario', function (): void {
    $previousTeam = getPermissionsTeamId();
    setPermissionsTeamId((string) $this->testTenant->id);

    try {
        $vet = \App\Models\User::factory()->create([
            'email' => 'vet-'.$this->testTenantSlug.'@test.local',
            'tenant_id' => $this->testTenant->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $vet->assignRole('veterinario');
    } finally {
        setPermissionsTeamId($previousTeam);
    }

    $this->actingAs($vet)
        ->get('http://'.$this->testTenantHost.'/clinica/sala-espera')
        ->assertForbidden();
});
