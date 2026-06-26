<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Notifications\Tenancy\TenantSubdomainChangedNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Cambio de subdominio requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update([
        'telefono' => '999888777',
        'estado' => 'active',
    ]);
    $this->superadmin = $this->createTestSuperadmin();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('cambia el subdominio de un tenant activo y notifica por correo y whatsapp', function (): void {
    Notification::fake();

    $this->mock(PlatformWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturn(true);
        $mock->shouldReceive('sendText')->once();
    });

    $newSlug = 'clinica-vetpets-test';

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/change-slug', [
            'slug' => $newSlug,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->testTenant->fresh()->slug)->toBe($newSlug);

    Notification::assertSentOnDemand(
        TenantSubdomainChangedNotification::class,
        function (string $channel, mixed $notifiable, TenantSubdomainChangedNotification $notification) use ($newSlug): bool {
            return $channel === 'mail'
                && $notifiable->routes['mail'] === $this->testTenant->email_admin
                && $notification->newSlug === $newSlug;
        },
    );
});

it('rechaza el mismo subdominio que el actual', function (): void {
    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/change-slug', [
            'slug' => $this->testTenant->slug,
        ])
        ->assertSessionHasErrors('slug');
});

it('rechaza cambiar subdominio de tenant cancelado', function (): void {
    $this->testTenant->update(['estado' => 'cancelled']);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/change-slug', [
            'slug' => 'otro-nombre',
        ])
        ->assertSessionHasErrors('slug');
});
