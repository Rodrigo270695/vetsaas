<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Resume de tenant requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->superadmin = $this->createTestSuperadmin();
});

it('al reanudar un tenant suspendido otorga +1 dia de gracia solo en su suscripcion', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'RESUME-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan resume',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 70,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'resume-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Resume Grace',
        'email_admin' => Str::lower(Str::random(8)).'@resume.test',
        'estado' => 'suspended',
        'suspended_at' => now()->subDays(2),
        'suspension_reason' => 'Impago de prueba',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'suspended',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => now()->subDays(10),
            'grace_ends_at' => null,
            'precio_pactado' => '59.90',
        ]);
    });

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$tenant->id.'/resume')
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription->refresh();
    $tenant->refresh();

    expect($subscription->estado)->toBe('grace')
        ->and($subscription->grace_ends_at)->not->toBeNull()
        ->and($subscription->grace_ends_at?->between(
            now()->addDay()->subMinute(),
            now()->addDay()->addMinute(),
        ))->toBeTrue()
        ->and($tenant->estado)->toBe('active')
        ->and($tenant->suspended_at)->toBeNull();
});
