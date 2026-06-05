<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionTenantSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Sincronización tenant/suscripción requiere PostgreSQL.');
    }
});

it('sincroniza tenant a active cuando la suscripción deja el trial', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'SYNC-PRO-'.Str::lower(Str::random(4)),
        'nombre' => 'Pro sync',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 14,
        'orden' => 50,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'sync-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Sync Test',
        'email_admin' => Str::lower(Str::random(8)).'@sync.test',
        'estado' => 'trial',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'trial',
            'ciclo' => 'mensual',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'precio_pactado' => '59.90',
        ]);
    });

    $subscription->update([
        'estado' => 'active',
        'trial_ends_at' => null,
    ]);

    $tenant->refresh();

    expect($tenant->estado)->toBe('active')
        ->and($tenant->trial_ends_at)->toBeNull();
});

it('repara desajustes con syncAllLiving', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'SYNC-ALL-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan sync all',
        'descripcion' => null,
        'precio_mensual' => '0.00',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 51,
        'es_publico' => false,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'syncall-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Sync All',
        'email_admin' => Str::lower(Str::random(8)).'@syncall.test',
        'estado' => 'trial',
        'trial_ends_at' => now()->addDays(10),
    ]);

    Subscription::withoutEvents(function () use ($tenant, $plan): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'precio_pactado' => '59.90',
        ]);
    });

    $updated = app(SubscriptionTenantSync::class)->syncAllLiving();

    $tenant->refresh();

    expect($updated)->toBeGreaterThanOrEqual(1)
        ->and($tenant->estado)->toBe('active')
        ->and($tenant->trial_ends_at)->toBeNull();
});
