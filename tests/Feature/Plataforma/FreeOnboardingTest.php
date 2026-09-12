<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\Tenancy\TenantOnboardingCheckInNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Seguimiento Free requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update([
        'telefono' => '947381241',
        'estado' => 'active',
    ]);
    $this->superadmin = $this->createTestSuperadmin();

    $plan = Plan::query()->firstOrCreate(
        ['codigo' => Plan::CODIGO_FREE],
        [
            'nombre' => 'Free',
            'descripcion' => null,
            'precio_mensual' => '0',
            'precio_anual' => null,
            'trial_days' => 0,
            'orden' => 1,
            'es_publico' => true,
            'activo' => true,
        ],
    );

    Subscription::withoutEvents(function () use ($plan): void {
        Subscription::query()->create([
            'tenant_id' => $this->testTenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'proximo_cobro_at' => now()->addMonth(),
            'precio_pactado' => '0',
        ]);
    });
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('lista el tenant free en el reporte de seguimiento', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/tenants/free-onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/tenants/free-onboarding')
            ->has('items.data', 1)
            ->where('items.data.0.tenant.slug', $this->testTenantSlug)
            ->where('items.data.0.stage', 'nunca_entro')
            ->where('stats.nunca_entro', 1)
        );
});

it('envía correo de seguimiento si no hay celular', function (): void {
    Notification::fake();
    $this->testTenant->update(['telefono' => null]);

    $this->actingAs($this->superadmin)
        ->from('http://127.0.0.1/plataforma/tenants/free-onboarding')
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/free-onboarding/send')
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(TenantOnboardingCheckInNotification::class);
});

it('envía el whatsapp de seguimiento al celular del tenant', function (): void {
    $this->mock(PlatformWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturn(true);
        $mock->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $chatId, string $message): bool {
                return $chatId === '51947381241@c.us'
                    && str_contains($message, 'plan Free');
            });
    });

    $this->actingAs($this->superadmin)
        ->from('http://127.0.0.1/plataforma/tenants/free-onboarding')
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/free-onboarding/send')
        ->assertRedirect()
        ->assertSessionHas('success');
});
