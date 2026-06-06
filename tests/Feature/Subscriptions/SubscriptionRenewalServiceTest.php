<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionRenewalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Renovación de suscripción requiere PostgreSQL.');
    }
});

it('renueva desde el fin del período actual si paga antes del vencimiento', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'starter',
        'nombre' => 'Starter',
        'descripcion' => null,
        'precio_mensual' => '149',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 81,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'renewearly-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Renew Early',
        'email_admin' => Str::lower(Str::random(8)).'@early.test',
        'estado' => 'active',
    ]);

    Subscription::withoutEvents(function () use ($tenant, $plan): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_start' => Carbon::parse('2026-06-05'),
            'current_period_end' => Carbon::parse('2026-07-05'),
            'proximo_cobro_at' => Carbon::parse('2026-07-05'),
            'precio_pactado' => '149',
        ]);
    });

    $subscription = app(SubscriptionRenewalService::class)->renew($tenant, [
        'plan_slug' => 'starter',
        'ciclo' => 'mensual',
        'payment' => [
            'monto' => 149,
            'moneda' => 'PEN',
            'pasarela' => 'orvae',
            'transaction_id' => 'chg_early_'.Str::random(6),
            'pagado_at' => '2026-07-01 10:00:00',
        ],
    ]);

    expect($subscription->current_period_start?->toDateString())->toBe('2026-07-05')
        ->and($subscription->current_period_end?->toDateString())->toBe('2026-08-05')
        ->and($subscription->proximo_cobro_at?->toDateString())->toBe('2026-08-05');
});
