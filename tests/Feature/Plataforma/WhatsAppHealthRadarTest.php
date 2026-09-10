<?php

declare(strict_types=1);

use App\Models\TenantWhatsAppSession;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update(['estado' => 'active']);
    $this->superadmin = $this->createTestSuperadmin();

    TenantWhatsAppSession::query()->create([
        'tenant_id' => $this->testTenant->id,
        'openwa_session_id' => 'session-health-001',
        'openwa_session_name' => $this->testTenant->slug,
        'status' => 'failed',
        'last_error' => 'timeout',
        'last_synced_at' => now()->subHour(),
        'auto_reconnect' => true,
    ]);
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra el radar de salud whatsapp al superadmin', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/whatsapp-salud')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/whatsapp-salud/index')
            ->has('items.data', 0)
            ->where('stats.with_error', 1)
            ->where('stats.needs_qr', 1)
            ->where('filters.scope', 'listos'));
});

it('lista problemas y sesiones que piden QR', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/whatsapp-salud?scope=problemas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/whatsapp-salud/index')
            ->has('items.data', 1)
            ->where('filters.scope', 'problemas'));

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/whatsapp-salud?scope=needs_qr')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('filters.scope', 'needs_qr'));
});

it('rechaza el radar whatsapp a un admin de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://127.0.0.1/plataforma/whatsapp-salud')
        ->assertForbidden();
});
