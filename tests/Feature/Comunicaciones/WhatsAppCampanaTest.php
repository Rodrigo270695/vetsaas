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

function campaignVariantes(): array
{
    return [
        'Hola {nombre}, campaña de desparasitación de {mascota} en {clinica}. Texto uno para rotar.',
        'Hola {nombre_completo}, te escribe {clinica} por {mascota}. Texto dos distinto al anterior.',
        '{nombre}, recordatorio de desparasitación para {mascota} en {clinica}. Texto tres diferente.',
    ];
}

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
            'variantes' => campaignVariantes(),
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
            'variantes' => campaignVariantes(),
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
