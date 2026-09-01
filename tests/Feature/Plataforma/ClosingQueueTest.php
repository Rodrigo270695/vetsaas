<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update(['estado' => 'trial']);
    $this->superadmin = $this->createTestSuperadmin();

    $plan = Plan::query()->create([
        'codigo' => 'CIERRE-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan cola',
        'descripcion' => null,
        'precio_mensual' => '99.00',
        'precio_anual' => null,
        'trial_days' => 14,
        'orden' => 81,
        'es_publico' => true,
        'activo' => true,
    ]);

    Subscription::withoutEvents(function () use ($plan): void {
        Subscription::query()->create([
            'tenant_id' => $this->testTenant->id,
            'plan_id' => $plan->id,
            'estado' => 'trial',
            'ciclo' => 'mensual',
            'trial_ends_at' => now()->addDays(3),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'precio_pactado' => '99.00',
        ]);
    });
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra la cola de cierre al superadmin', function (): void {
    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/cola-cierre')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/cola-cierre/index')
            ->has('items.data', 1)
            ->where('stats.trials', 1)
            ->where('filters.scope', 'hoy')
            ->where('items.data.0.last_sent_at', null));
});

it('rechaza la cola de cierre a un admin de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://127.0.0.1/plataforma/cola-cierre')
        ->assertForbidden();
});
