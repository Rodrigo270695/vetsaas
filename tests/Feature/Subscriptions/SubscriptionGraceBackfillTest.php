<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionGraceBackfillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Backfill de gracia requiere PostgreSQL.');
    }

    config(['billing.grace_days' => 3]);
});

it('aplica gracia a suscripciones de pago vencidas y omite free', function (): void {
    $paidPlan = Plan::query()->create([
        'codigo' => 'GRACE-PAID-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan pago',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 70,
        'es_publico' => true,
        'activo' => true,
    ]);

    $freePlan = Plan::query()->firstOrCreate(
        ['codigo' => Plan::CODIGO_FREE],
        [
            'nombre' => 'Free',
            'descripcion' => null,
            'precio_mensual' => '0',
            'precio_anual' => null,
            'trial_days' => 0,
            'orden' => 0,
            'es_publico' => true,
            'activo' => true,
        ],
    );

    $tenantPaid = Tenant::query()->create([
        'slug' => 'gracepaid-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Grace Paid',
        'email_admin' => Str::lower(Str::random(8)).'@grace.test',
        'estado' => 'active',
    ]);

    $tenantFree = Tenant::query()->create([
        'slug' => 'gracefree-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Grace Free',
        'email_admin' => Str::lower(Str::random(8)).'@freeg.test',
        'estado' => 'active',
    ]);

    $tenantOk = Tenant::query()->create([
        'slug' => 'graceok-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Grace Ok',
        'email_admin' => Str::lower(Str::random(8)).'@okg.test',
        'estado' => 'active',
    ]);

    $overdue = Subscription::withoutEvents(function () use ($tenantPaid, $paidPlan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenantPaid->id,
            'plan_id' => $paidPlan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_end' => now()->subDays(2),
            'proximo_cobro_at' => now()->subDays(2),
            'precio_pactado' => '59.90',
        ]);
    });

    $freeSub = Subscription::withoutEvents(function () use ($tenantFree, $freePlan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenantFree->id,
            'plan_id' => $freePlan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_end' => now()->subDays(2),
            'proximo_cobro_at' => now()->subDays(2),
            'precio_pactado' => '0',
        ]);
    });

    $current = Subscription::withoutEvents(function () use ($tenantOk, $paidPlan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenantOk->id,
            'plan_id' => $paidPlan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_end' => now()->addDays(10),
            'proximo_cobro_at' => now()->addDays(10),
            'precio_pactado' => '59.90',
        ]);
    });

    $result = app(SubscriptionGraceBackfillService::class)->run();

    $overdue->refresh();
    $freeSub->refresh();
    $current->refresh();

    expect($result['applied'])->toBe(1)
        ->and($overdue->estado)->toBe('grace')
        ->and($overdue->grace_ends_at)->not->toBeNull()
        ->and($freeSub->estado)->toBe('active')
        ->and($current->estado)->toBe('active');
});
