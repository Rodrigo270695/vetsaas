<?php

declare(strict_types=1);

use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('RSVP de agenda usa schemas tenant (PostgreSQL).');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('confirma la cita más próxima con SI', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Pérez',
            'telefono' => '999999999',
            'activo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Firulais',
            'activo' => true,
        ]);
        $cita = Cita::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => now()->addDay()->setTime(10, 0),
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
            'motivo' => 'Control',
        ]);

        $result = app(AgendaOwnerRsvpService::class)->tryHandle('51999999999', 'SI');

        expect($result)->not->toBeNull()
            ->and($result['kind'])->toBe('cita')
            ->and($result['intent'])->toBe('yes')
            ->and($result['reply'])->toContain('Confirmamos');

        $cita->refresh();
        expect($cita->estado)->toBe(Cita::ESTADO_CONFIRMADA)
            ->and($cita->confirmed_via)->toBe(AgendaOwnerRsvpService::VIA_PROPIETARIO);
    });
});

it('cancela con NO', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Pérez',
            'telefono' => '999999999',
            'activo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Firulais',
            'activo' => true,
        ]);
        $cita = Cita::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => now()->addHours(5),
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);

        $result = app(AgendaOwnerRsvpService::class)->tryHandle('51999999999', 'no puedo');

        expect($result['intent'])->toBe('no');
        expect($cita->fresh()->estado)->toBe(Cita::ESTADO_CANCELADA);
    });
});

it('con dos citas en espera confirma la del último WhatsApp', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Pérez',
            'telefono' => '999999999',
            'activo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Pelotito',
            'activo' => true,
        ]);
        $temprana = Cita::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => now()->addDay()->setTime(22, 17),
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);
        $tarde = Cita::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => now()->addDay()->setTime(22, 34),
            'duracion_minutos' => 30,
            'estado' => Cita::ESTADO_PROGRAMADA,
        ]);

        $chatId = '51999999999@c.us';
        app(\App\Services\Notifications\NotificationQueueService::class)->enqueue(
            tipo: 'cita_48h',
            destinatario: $chatId,
            cuerpo: 'recordatorio temprana',
            enviarAt: now(),
            referenciaTipo: 'cita',
            referenciaId: $temprana->id,
            dedupeKey: 'cita_48h:'.$temprana->id,
        );
        app(\App\Services\Notifications\NotificationQueueService::class)->enqueue(
            tipo: 'cita_creada',
            destinatario: $chatId,
            cuerpo: 'creada tarde',
            enviarAt: now(),
            referenciaTipo: 'cita',
            referenciaId: $tarde->id,
            dedupeKey: 'cita_creada:'.$tarde->id,
        );
        \App\Models\NotificationQueue::query()
            ->where('referencia_id', $tarde->id)
            ->update(['created_at' => now()->addMinute()]);

        $result = app(AgendaOwnerRsvpService::class)->tryHandle('51999999999', 'Si', $chatId);

        expect($result['id'])->toBe((string) $tarde->id);
        expect($tarde->fresh()->estado)->toBe(Cita::ESTADO_CONFIRMADA);
        expect($temprana->fresh()->estado)->toBe(Cita::ESTADO_PROGRAMADA);

        $second = app(AgendaOwnerRsvpService::class)->tryHandle('51999999999', 'Si', $chatId);
        expect($second['id'])->toBe((string) $temprana->id);
        expect($temprana->fresh()->estado)->toBe(Cita::ESTADO_CONFIRMADA);
    });
});
