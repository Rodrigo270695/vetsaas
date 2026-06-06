<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionRenewalReminder;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Subscriptions\SubscriptionRenewalReminderScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Recordatorios de renovación requieren PostgreSQL.');
    }
});

it('envía recordatorio 7 días antes del próximo cobro', function (): void {
    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->andReturn(true);
    $messenger->shouldReceive('sendText')
        ->once()
        ->withArgs(fn (string $chatId, string $text): bool => str_contains($chatId, '@c.us')
            && str_contains($text, 'vence el'))
        ->andReturn(['id' => 'wa-msg-1']);
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'REM-7D-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan reminder',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 70,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'rem7d-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Reminder',
        'nombre_comercial' => 'Clínica Reminder',
        'telefono' => '987654321',
        'email_admin' => Str::lower(Str::random(8)).'@rem.test',
        'estado' => 'active',
    ]);

    $anchor = now()->addDays(7)->startOfDay();

    Subscription::withoutEvents(function () use ($tenant, $plan, $anchor): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => $anchor,
            'precio_pactado' => '59.90',
        ]);
    });

    $result = app(SubscriptionRenewalReminderScanner::class)->run();

    expect($result['sent'])->toBe(1)
        ->and(SubscriptionRenewalReminder::query()->count())->toBe(1);
});

it('omite si ya hay pago que cubre el período', function (): void {
    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->andReturn(true);
    $messenger->shouldReceive('sendText')->never();
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'REM-PAID-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan paid',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 71,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'rempaid-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Paid',
        'telefono' => '987654322',
        'email_admin' => Str::lower(Str::random(8)).'@paid.test',
        'estado' => 'active',
    ]);

    $anchor = now()->addDays(7)->startOfDay();

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan, $anchor): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => $anchor,
            'precio_pactado' => '59.90',
        ]);
    });

    SubscriptionPayment::query()->create([
        'subscription_id' => $subscription->id,
        'monto' => '59.90',
        'moneda' => 'PEN',
        'estado' => 'procesado',
        'pasarela' => 'manual',
        'pagado_at' => now(),
    ]);

    $result = app(SubscriptionRenewalReminderScanner::class)->run();

    expect($result['sent'])->toBe(0)
        ->and($result['skipped'])->toBeGreaterThan(0);
});

it('omite suscripciones del plan free', function (): void {
    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->andReturn(true);
    $messenger->shouldReceive('sendText')->never();
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'free',
        'nombre' => 'Free',
        'descripcion' => null,
        'precio_mensual' => '0',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 1,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'remfree-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Free',
        'telefono' => '987654323',
        'email_admin' => Str::lower(Str::random(8)).'@free.test',
        'estado' => 'active',
    ]);

    Subscription::withoutEvents(function () use ($tenant, $plan): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => now()->addDays(7)->startOfDay(),
            'precio_pactado' => '0',
        ]);
    });

    $result = app(SubscriptionRenewalReminderScanner::class)->run();

    expect($result['scanned'])->toBe(0)
        ->and($result['sent'])->toBe(0);
});
