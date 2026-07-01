<?php

declare(strict_types=1);

use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('WhatsApp plataforma requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update(['estado' => 'active']);
    $this->superadmin = $this->createTestSuperadmin();

    TenantWhatsAppSession::query()->create([
        'tenant_id' => $this->testTenant->id,
        'openwa_session_id' => 'session-test-001',
        'openwa_session_name' => $this->testTenant->slug,
        'status' => 'failed',
    ]);
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('reinicia la sesión whatsapp de un tenant desde plataforma', function (): void {
    $session = TenantWhatsAppSession::query()->where('tenant_id', $this->testTenant->id)->firstOrFail();

    $this->mock(OpenWaClient::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('stopSession')->once()->with('session-test-001');
        $mock->shouldReceive('startSession')->once()->with('session-test-001');
        $mock->shouldReceive('getQrCode')->once()->with('session-test-001')->andReturn([
            'status' => 'qr_ready',
            'qrCode' => 'data:image/png;base64,abc',
        ]);
    });

    $this->mock(TenantWhatsAppSessionSync::class, function ($mock) use ($session): void {
        $mock->shouldReceive('ensureForTenant')->once()->andReturn($session);
        $mock->shouldReceive('refresh')->once()->andReturn(
            $session->fresh()->forceFill(['status' => 'qr_ready'])->tap->save(),
        );
    });

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/whatsapp/restart')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($session->fresh()->status)->toBe('qr_ready');
});

it('detiene la sesión whatsapp de un tenant desde plataforma', function (): void {
    $session = TenantWhatsAppSession::query()->where('tenant_id', $this->testTenant->id)->firstOrFail();

    $this->mock(OpenWaClient::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('stopSession')->once()->with('session-test-001');
        $mock->shouldReceive('getQrCode')->never();
    });

    $this->mock(TenantWhatsAppSessionSync::class, function ($mock) use ($session): void {
        $mock->shouldReceive('ensureForTenant')->once()->andReturn($session);
        $mock->shouldReceive('refresh')->once()->andReturn(
            $session->fresh()->forceFill(['status' => 'disconnected', 'phone' => null])->tap->save(),
        );
    });

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/whatsapp/stop')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($session->fresh()->status)->toBe('disconnected');
});

it('deniega reinicio whatsapp sin permiso', function (): void {
    $user = \App\Models\User::factory()->create([
        'tenant_id' => null,
        'email' => 'limited@example.test',
    ]);
    $user->givePermissionTo('plataforma-tenants.view');

    $this->actingAs($user)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/whatsapp/restart')
        ->assertForbidden();
});
