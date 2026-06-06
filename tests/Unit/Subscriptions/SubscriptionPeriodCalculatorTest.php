<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionPeriodCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Cálculo de períodos requiere PostgreSQL.');
    }
});

it('conserva el día de ancla si paga antes del vencimiento', function (): void {
    $calculator = app(SubscriptionPeriodCalculator::class);

    $plan = Plan::query()->create([
        'codigo' => 'PER-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan period',
        'descripcion' => null,
        'precio_mensual' => '149',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 80,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'period-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Period',
        'email_admin' => Str::lower(Str::random(8)).'@period.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_start' => Carbon::parse('2026-06-05 00:00:00'),
            'current_period_end' => Carbon::parse('2026-07-05 00:00:00'),
            'proximo_cobro_at' => Carbon::parse('2026-07-05 00:00:00'),
            'precio_pactado' => '149',
        ]);
    });

    $paidAt = Carbon::parse('2026-07-01 10:00:00');
    $nextStart = $calculator->nextPeriodStart($subscription, $paidAt);
    $nextEnd = $calculator->nextPeriodEnd($nextStart, 'mensual');

    expect($nextStart->toDateString())->toBe('2026-07-05')
        ->and($nextEnd->toDateString())->toBe('2026-08-05');
});
