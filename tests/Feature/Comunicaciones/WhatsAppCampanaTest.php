<?php

declare(strict_types=1);

use App\Models\Propietario;
use App\Models\TenantWhatsAppSession;
use App\Models\WhatsAppCampana;
use App\Models\WhatsAppCampanaDestinatario;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Services\WhatsApp\WhatsAppCampaignDispatcher;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Campañas WhatsApp requieren PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('lista campañas al admin de la clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/campanas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('comunicaciones/campanas/index'));
});

it('crea una campaña y agrega solo celulares Perú válidos', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'Movil',
            'telefono' => '987654321',
            'activo' => true,
        ]);
        Propietario::query()->create([
            'nombres' => 'Luis',
            'apellidos' => 'Fijo',
            'telefono' => '014445555',
            'activo' => true,
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/campanas', [
            'nombre' => 'Desparasitación',
            'cuerpo' => 'Hola {nombre}, te escribimos de {clinica} por la campaña de {mascota}. ¿Agendamos?',
            'tope_diario' => 50,
            'intervalo_minutos' => 12,
            'hora_inicio' => '09:00',
            'hora_fin' => '18:00',
        ])
        ->assertRedirect();

    $campana = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        fn (): WhatsAppCampana => WhatsAppCampana::query()->firstOrFail(),
    );

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/campanas/'.$campana->id.'/destinatarios/todos')
        ->assertRedirect();

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($campana): void {
        expect(WhatsAppCampanaDestinatario::query()->where('campana_id', $campana->id)->count())->toBe(1)
            ->and(WhatsAppCampanaDestinatario::query()->value('telefono_normalizado'))->toBe('51987654321');
    });
});

it('envía un destinatario por tick y no toca a los ya enviados', function (): void {
    $session = TenantWhatsAppSession::query()->create([
        'tenant_id' => $this->testTenant->id,
        'openwa_session_id' => 'sess-camp-1',
        'openwa_session_name' => $this->testTenant->slug,
        'status' => TenantWhatsAppSession::STATUS_READY,
        'last_synced_at' => now(),
        'auto_reconnect' => true,
    ]);

    $this->mock(OpenWaClient::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('isRateLimited')->andReturn(false);
        $mock->shouldReceive('isAmbiguousDeliveryError')->andReturn(false);
        $mock->shouldReceive('isNoResponseTimeout')->andReturn(false);
    });

    $this->mock(TenantWhatsAppSessionSync::class, function ($mock) use ($session): void {
        $mock->shouldReceive('ensureReadyForSend')->andReturn($session);
    });

    $this->mock(TenantWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('sendText')->once()->andReturn(['messageId' => 'm1']);
        $mock->shouldReceive('sendImage')->never();
    });

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $owner = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Pérez',
            'telefono' => '999888777',
            'activo' => true,
        ]);

        $campana = WhatsAppCampana::query()->create([
            'nombre' => 'Campaña test',
            'variantes' => ['Hola {nombre}, campaña de desparasitación de {mascota} en {clinica}.'],
            'tope_diario' => 50,
            'intervalo_minutos' => 8,
            'hora_inicio' => '00:00',
            'hora_fin' => '23:59',
            'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
            'started_at' => now(),
        ]);

        WhatsAppCampanaDestinatario::query()->create([
            'campana_id' => $campana->id,
            'propietario_id' => $owner->id,
            'telefono_normalizado' => '51999888777',
            'nombre_snapshot' => 'María Pérez',
            'mascota_nombres' => 'Max',
            'estado' => WhatsAppCampanaDestinatario::ESTADO_PENDIENTE,
        ]);
    });

    $result = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        fn () => app(WhatsAppCampaignDispatcher::class)->tick($this->testTenant),
    );

    expect($result['sent'])->toBe(1);

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $row = WhatsAppCampanaDestinatario::query()->firstOrFail();
        expect($row->estado)->toBe(WhatsAppCampanaDestinatario::ESTADO_ENVIADO)
            ->and($row->cuerpo_enviado)->toContain('María');
    });
});

it('permite editar intervalo y horario aunque la campaña esté enviando', function (): void {
    $campana = app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): WhatsAppCampana {
        return WhatsAppCampana::query()->create([
            'nombre' => 'Desparasitación',
            'variantes' => ['Hola {nombre}, ven y unete a la campa'],
            'tope_diario' => 50,
            'intervalo_minutos' => 12,
            'hora_inicio' => '09:00',
            'hora_fin' => '18:00',
            'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
            'started_at' => now(),
        ]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/campanas/'.$campana->id, [
            'nombre' => 'Desparasitación',
            'cuerpo' => 'Hola {nombre}, ven y unete a la campa',
            'tope_diario' => 50,
            'intervalo_minutos' => 1,
            'hora_inicio' => '09:00',
            'hora_fin' => '23:05',
        ])
        ->assertRedirect();

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($campana): void {
        $fresh = $campana->fresh();
        expect($fresh?->intervalo_minutos)->toBe(1)
            ->and(substr((string) $fresh?->hora_fin, 0, 5))->toBe('23:05');
    });
});

it('respeta un intervalo de 1 minuto y no envía fuera de horario', function (): void {
    $session = TenantWhatsAppSession::query()->create([
        'tenant_id' => $this->testTenant->id,
        'openwa_session_id' => 'sess-camp-2',
        'openwa_session_name' => $this->testTenant->slug,
        'status' => TenantWhatsAppSession::STATUS_READY,
        'last_synced_at' => now(),
        'auto_reconnect' => true,
    ]);

    $this->mock(OpenWaClient::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('isRateLimited')->andReturn(false);
        $mock->shouldReceive('isAmbiguousDeliveryError')->andReturn(false);
        $mock->shouldReceive('isNoResponseTimeout')->andReturn(false);
    });

    $this->mock(TenantWhatsAppSessionSync::class, function ($mock) use ($session): void {
        $mock->shouldReceive('ensureReadyForSend')->andReturn($session);
    });

    $this->mock(TenantWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('sendText')->once()->andReturn(['messageId' => 'm2']);
        $mock->shouldReceive('sendImage')->never();
    });

    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Lima'));

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $owner = Propietario::query()->create([
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'telefono' => '999111222',
            'activo' => true,
        ]);

        $campana = WhatsAppCampana::query()->create([
            'nombre' => 'Campaña intervalo',
            'variantes' => ['Hola {nombre}, recordatorio de {mascota} en {clinica}.'],
            'tope_diario' => 50,
            'intervalo_minutos' => 1,
            'hora_inicio' => '09:00',
            'hora_fin' => '18:00',
            'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
            'started_at' => now(),
            'last_sent_at' => now(),
        ]);

        WhatsAppCampanaDestinatario::query()->create([
            'campana_id' => $campana->id,
            'propietario_id' => $owner->id,
            'telefono_normalizado' => '51999111222',
            'nombre_snapshot' => 'Luis Rojas',
            'mascota_nombres' => 'Luna',
            'estado' => WhatsAppCampanaDestinatario::ESTADO_PENDIENTE,
        ]);
    });

    $tooSoon = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        fn () => app(WhatsAppCampaignDispatcher::class)->tick($this->testTenant),
    );
    expect($tooSoon['sent'])->toBe(0);

    $this->travel(1)->minutes();

    $due = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        fn () => app(WhatsAppCampaignDispatcher::class)->tick($this->testTenant),
    );
    expect($due['sent'])->toBe(1);

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        WhatsAppCampana::query()->update([
            'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
            'hora_inicio' => '09:00',
            'hora_fin' => '18:00',
            'last_sent_at' => null,
        ]);
        WhatsAppCampanaDestinatario::query()->update([
            'estado' => WhatsAppCampanaDestinatario::ESTADO_PENDIENTE,
            'enviado_at' => null,
            'cuerpo_enviado' => null,
        ]);
    });

    $this->travelTo(Carbon::parse('2026-09-15 22:47:00', 'America/Lima'));

    $outside = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        fn () => app(WhatsAppCampaignDispatcher::class)->tick($this->testTenant),
    );
    expect($outside['sent'])->toBe(0);
});

it('agrega dueños nuevos a una campaña terminada y no reenvía a los ya enviados', function (): void {
    $ids = app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): array {
        $sentOwner = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'Enviada',
            'telefono' => '911111111',
            'activo' => true,
        ]);
        $newOwner = Propietario::query()->create([
            'nombres' => 'Bruno',
            'apellidos' => 'Nuevo',
            'telefono' => '922222222',
            'activo' => true,
        ]);

        $campana = WhatsAppCampana::query()->create([
            'nombre' => 'Desparasitación',
            'variantes' => ['Hola {nombre}, campaña de {mascota} en {clinica}.'],
            'tope_diario' => 50,
            'intervalo_minutos' => 12,
            'hora_inicio' => '09:00',
            'hora_fin' => '23:59',
            'estado' => WhatsAppCampana::ESTADO_TERMINADA,
            'started_at' => now()->subHour(),
        ]);

        WhatsAppCampanaDestinatario::query()->create([
            'campana_id' => $campana->id,
            'propietario_id' => $sentOwner->id,
            'telefono_normalizado' => '51911111111',
            'nombre_snapshot' => 'Ana Enviada',
            'mascota_nombres' => 'Lola',
            'estado' => WhatsAppCampanaDestinatario::ESTADO_ENVIADO,
            'enviado_at' => now()->subMinutes(10),
        ]);

        return [
            'campana' => $campana->id,
            'sent' => $sentOwner->id,
            'nuevo' => $newOwner->id,
        ];
    });

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/campanas/'.$ids['campana'].'/destinatarios', [
            'propietario_ids' => [$ids['sent'], $ids['nuevo']],
        ])
        ->assertRedirect();

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($ids): void {
        $campana = WhatsAppCampana::query()->findOrFail($ids['campana']);
        $rows = WhatsAppCampanaDestinatario::query()->orderBy('nombre_snapshot')->get();

        expect($campana->estado)->toBe(WhatsAppCampana::ESTADO_ENVIANDO)
            ->and($rows)->toHaveCount(2)
            ->and($rows->firstWhere('propietario_id', $ids['sent'])?->estado)
            ->toBe(WhatsAppCampanaDestinatario::ESTADO_ENVIADO)
            ->and($rows->firstWhere('propietario_id', $ids['nuevo'])?->estado)
            ->toBe(WhatsAppCampanaDestinatario::ESTADO_PENDIENTE);
    });
});
