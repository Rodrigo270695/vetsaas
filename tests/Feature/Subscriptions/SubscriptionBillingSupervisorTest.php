<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionBillingSupervisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Supervisor de cobros requiere PostgreSQL.');
    }
});

it('pasa active a grace cuando vence proximo_cobro sin pago', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'BILL-GRACE-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan bill grace',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 60,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'billgrace-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Bill Grace',
        'email_admin' => Str::lower(Str::random(8)).'@bill.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
            'proximo_cobro_at' => now()->subDay(),
            'precio_pactado' => '59.90',
        ]);
    });

    $result = app(SubscriptionBillingSupervisor::class)->run();

    $subscription->refresh();

    expect($result['active_to_grace'])->toBe(1)
        ->and($subscription->estado)->toBe('grace')
        ->and($subscription->grace_ends_at)->not->toBeNull()
        ->and($subscription->grace_ends_at?->equalTo(
            $subscription->proximo_cobro_at?->copy()->addDays((int) config('billing.grace_days', 3))
        ))->toBeTrue();
});

it('suspende tras vencer grace sin pago y sincroniza tenant', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'BILL-SUSP-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan bill susp',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 61,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'billsusp-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Bill Susp',
        'email_admin' => Str::lower(Str::random(8)).'@susp.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'grace',
            'ciclo' => 'mensual',
            'grace_ends_at' => now()->subHour(),
            'proximo_cobro_at' => now()->subDays(8),
            'precio_pactado' => '59.90',
        ]);
    });

    $result = app(SubscriptionBillingSupervisor::class)->run();

    $subscription->refresh();
    $tenant->refresh();

    expect($result['grace_to_suspended'])->toBe(1)
        ->and($subscription->estado)->toBe('suspended')
        ->and($tenant->estado)->toBe('suspended')
        ->and($tenant->suspension_reason)->toContain('impago');
});

it('no suspende si hay un pago procesado que cubre el vencimiento', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'BILL-PAID-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan bill paid',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 62,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'billpaid-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Bill Paid',
        'email_admin' => Str::lower(Str::random(8)).'@paid.test',
        'estado' => 'active',
    ]);

    $dueAt = now()->subDay();

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan, $dueAt): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => $dueAt,
            'precio_pactado' => '59.90',
        ]);
    });

    SubscriptionPayment::query()->create([
        'subscription_id' => $subscription->id,
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'monto' => '59.90',
        'moneda' => 'PEN',
        'total' => '59.90',
        'estado' => 'procesado',
        'pasarela' => 'manual',
        'pagado_at' => $dueAt->copy()->addHour(),
        'created_at' => now(),
    ]);

    $result = app(SubscriptionBillingSupervisor::class)->run();

    $subscription->refresh();

    expect($result['active_to_grace'])->toBe(0)
        ->and($subscription->estado)->toBe('active');
});
