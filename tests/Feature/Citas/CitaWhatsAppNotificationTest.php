<?php

declare(strict_types=1);

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Las citas usan schemas tenant; requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('envía al crear y editar solo cuando la preferencia WhatsApp está activa', function (): void {
    $pacienteId = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function (): string {
            $propietario = Propietario::query()->create([
                'nombres' => 'María',
                'apellidos' => 'Pérez',
                'telefono' => '+51 999 888 777',
                'activo' => true,
            ]);

            return (string) Paciente::query()->create([
                'propietario_id' => $propietario->id,
                'nombre' => 'Firulais',
                'activo' => true,
            ])->id;
        },
    );

    $inicioAt = now()->addDay()->setTime(10, 0)->toIso8601String();
    $payload = [
        'paciente_id' => $pacienteId,
        'veterinario_id' => null,
        'sede_id' => null,
        'inicio_at' => $inicioAt,
        'duracion_minutos' => 30,
        'motivo' => 'Control general',
        'notas' => null,
    ];

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/clinica/citas', $payload)
        ->assertSessionHasNoErrors();

    $citaId = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function (): string {
            expect(NotificationQueue::query()->where('tipo', 'cita_creada')->count())->toBe(1);

            ClinicSetting::current()->update([
                'notificar_cita_whatsapp_activo' => false,
            ]);

            return (string) Cita::query()->sole()->id;
        },
    );

    $updatePayload = array_merge($payload, [
        'estado' => Cita::ESTADO_PROGRAMADA,
        'motivo' => 'Control sin WhatsApp',
    ]);

    $this->put(
        'http://'.$this->testTenantHost.'/clinica/citas/'.$citaId,
        $updatePayload,
    )->assertSessionHasNoErrors();

    app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function (): void {
            expect(NotificationQueue::query()->count())->toBe(1);

            ClinicSetting::current()->update([
                'notificar_cita_whatsapp_activo' => true,
            ]);
        },
    );

    $this->put(
        'http://'.$this->testTenantHost.'/clinica/citas/'.$citaId,
        array_merge($updatePayload, ['motivo' => 'Control actualizado']),
    )->assertSessionHasNoErrors();

    app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function (): void {
            expect(NotificationQueue::query()->where('tipo', 'cita_actualizada')->count())->toBe(1);
        },
    );
});
