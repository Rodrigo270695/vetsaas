<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscriptions\ManualSubscriptionRenewalService;
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

it('registra una renovación manual procesada una sola vez y avanza el período', function (): void {
    Carbon::setTestNow('2026-07-01 10:00:00');

    $plan = Plan::query()->create([
        'codigo' => 'pro-manual',
        'nombre' => 'Pro manual',
        'descripcion' => null,
        'precio_mensual' => '59',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 82,
        'es_publico' => true,
        'activo' => true,
    ]);
    $tenant = Tenant::query()->create([
        'slug' => 'renewmanual-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Renovación Manual',
        'email_admin' => Str::lower(Str::random(8)).'@manual.test',
        'estado' => 'active',
    ]);
    $subscription = Subscription::withoutEvents(fn (): Subscription => Subscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'estado' => 'active',
        'ciclo' => 'mensual',
        'current_period_start' => Carbon::parse('2026-06-05'),
        'current_period_end' => Carbon::parse('2026-07-05'),
        'proximo_cobro_at' => Carbon::parse('2026-07-05'),
        'precio_pactado' => '59',
    ]));
    $actor = User::factory()->create([
        'tenant_id' => null,
        'email' => Str::lower(Str::random(8)).'@platform.test',
    ]);
    $key = (string) Str::uuid();

    $first = app(ManualSubscriptionRenewalService::class)->renew(
        subscriptionId: (string) $subscription->id,
        amount: 59,
        method: 'yape',
        idempotencyKey: $key,
        actor: $actor,
        reference: 'YAPE-123',
        note: 'Pago confirmado por WhatsApp.',
    );
    $retry = app(ManualSubscriptionRenewalService::class)->renew(
        subscriptionId: (string) $subscription->id,
        amount: 59,
        method: 'yape',
        idempotencyKey: (string) Str::uuid(),
        actor: $actor,
        reference: '  yape-123  ',
    );

    $renewed = $subscription->fresh();
    $payment = SubscriptionPayment::query()->sole();

    expect($first['already_processed'])->toBeFalse()
        ->and($retry['already_processed'])->toBeTrue()
        ->and($renewed?->current_period_start?->toDateString())->toBe('2026-07-05')
        ->and($renewed?->current_period_end?->toDateString())->toBe('2026-08-05')
        ->and($renewed?->proximo_cobro_at?->toDateString())->toBe('2026-08-05')
        ->and($payment->estado)->toBe('procesado')
        ->and($payment->pasarela)->toBe('manual')
        ->and($payment->pasarela_response['raw']['method'] ?? null)->toBe('yape')
        ->and($payment->internal_note)->toBe('Pago confirmado por WhatsApp.')
        ->and(SubscriptionPayment::query()->count())->toBe(1);
});
